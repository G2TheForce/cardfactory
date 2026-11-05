<?php

namespace Tests\Feature\Designs;

use App\Jobs\ProcessCardExport;
use App\Models\CardDesign;
use App\Models\CardExport;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\User;
use App\Services\CardDesignService;
use App\Services\CardExportService;
use App\Services\Export\ChromeRenderer;
use App\Services\Export\PdfBoxes;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeChromeRenderer;
use Tests\TestCase;
use ZipArchive;

class CardExportTest extends TestCase
{
    use RefreshDatabase;

    private FakeChromeRenderer $chrome;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemeSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Http::fake(['fonts.googleapis.com/*' => Http::response('', 404)]);
        $this->chrome = new FakeChromeRenderer;
        $this->app->instance(ChromeRenderer::class, $this->chrome);
    }

    private function customerWithDesign(): array
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        Customer::factory()->create(['user_id' => $user->id, 'full_name' => 'Jordan Rivers']);
        $design = app(CardDesignService::class)->create($user, [
            'name' => 'Rivers card',
            'theme' => CardTheme::where('slug', 'corporate-executive')->first(),
            'customer' => $user->customerProfile,
        ]);

        return [$user, $design];
    }

    private function exportOf(User $user, CardDesign $design, array $options): CardExport
    {
        $this->actingAs($user)->post(route('designs.export.store', $design), $options + ['sides' => 'front', 'bleed_mm' => 3])
            ->assertRedirect(route('exports.index'));

        return CardExport::latest('id')->firstOrFail();
    }

    public function test_export_page_renders_for_owner_and_is_forbidden_for_others(): void
    {
        [$user, $design] = $this->customerWithDesign();
        $other = $this->userWithRole(User::ROLE_CUSTOMER);

        $this->actingAs($user)->get(route('designs.export', $design))->assertOk()->assertSee('Print-ready PDF');
        $this->actingAs($other)->get(route('designs.export', $design))->assertForbidden();
        $this->actingAs($other)->post(route('designs.export.store', $design), ['format' => 'svg', 'sides' => 'front', 'bleed_mm' => 0])->assertForbidden();
        $this->assertSame(0, CardExport::count());
    }

    public function test_export_options_are_validated(): void
    {
        [$user, $design] = $this->customerWithDesign();

        $this->actingAs($user)->post(route('designs.export.store', $design), ['format' => 'jpg', 'sides' => 'middle', 'bleed_mm' => 9, 'dpi' => 1200])
            ->assertSessionHasErrors(['format', 'sides', 'bleed_mm', 'dpi']);
        $this->assertSame(0, CardExport::count());
    }

    public function test_exports_are_queued_on_the_exports_queue(): void
    {
        Queue::fake();
        [$user, $design] = $this->customerWithDesign();

        $export = $this->exportOf($user, $design, ['format' => 'pdf']);

        $this->assertSame('queued', $export->status);
        Queue::assertPushedOn('exports', ProcessCardExport::class);
    }

    public function test_svg_export_is_self_contained_with_bleed(): void
    {
        [$user, $design] = $this->customerWithDesign();

        $export = $this->exportOf($user, $design, ['format' => 'svg', 'bleed_mm' => 2]);

        $this->assertSame('completed', $export->status);
        $svg = Storage::disk('local')->get($export->file_path);
        $this->assertStringStartsWith('<?xml', $svg);
        // 85 × 55 card plus 2 mm bleed on each side.
        $this->assertMatchesRegularExpression('/width="'.((float) $design->width_mm + 4).'mm"/', $svg);
        $this->assertStringContainsString('Jordan Rivers', $svg);
        $this->assertDoesNotMatchRegularExpression('/href="\/storage/', $svg);
    }

    public function test_pdf_export_has_print_boxes_and_crop_marks(): void
    {
        [$user, $design] = $this->customerWithDesign();

        $export = $this->exportOf($user, $design, ['format' => 'pdf', 'sides' => 'both', 'crop_marks' => '1']);

        $this->assertSame('completed', $export->status, (string) $export->error);
        $this->assertStringEndsWith('.pdf', $export->file_path);
        $pdf = Storage::disk('local')->get($export->file_path);

        $pt = fn ($mm) => round($mm * 72 / 25.4, 2);
        $w = (float) $design->width_mm;
        $h = (float) $design->height_mm;
        preg_match_all('/\/TrimBox \[([^\]]+)\]/', $pdf, $trim);
        preg_match_all('/\/BleedBox \[([^\]]+)\]/', $pdf, $bleed);
        $this->assertCount(2, $trim[1], 'one TrimBox per side');
        $t = array_map('floatval', explode(' ', $trim[1][0]));
        $b = array_map('floatval', explode(' ', $bleed[1][0]));
        $this->assertEqualsWithDelta($pt(8 + 3), $t[0], 0.05);
        $this->assertEqualsWithDelta($pt($w), $t[2] - $t[0], 0.05);
        $this->assertEqualsWithDelta($pt($h), $t[3] - $t[1], 0.05);
        $this->assertEqualsWithDelta($pt($w + 6), $b[2] - $b[0], 0.05);

        // The page sent to Chrome is sized for card + bleed + crop mark margin.
        $html = $this->chrome->html[0];
        $this->assertStringContainsString('size:'.($w + 6 + 16).'mm '.($h + 6 + 16).'mm', $html);
        $this->assertStringContainsString('crop-marks', $html);
    }

    public function test_png_export_has_exact_pixels_and_dpi_and_both_sides_are_zipped(): void
    {
        [$user, $design] = $this->customerWithDesign();

        $export = $this->exportOf($user, $design, ['format' => 'png', 'sides' => 'both', 'dpi' => 300, 'bleed_mm' => 0]);

        $this->assertSame('completed', $export->status, (string) $export->error);
        $this->assertStringEndsWith('.zip', $export->file_path);

        $zipPath = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($zipPath, Storage::disk('local')->get($export->file_path));
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $this->assertCount(2, $names);
        $front = $zip->getFromName(collect($names)->first(fn ($n) => str_contains($n, 'front')));
        $zip->close();
        unlink($zipPath);

        $img = imagecreatefromstring($front);
        $this->assertSame((int) round($design->width_mm / 25.4 * 300), imagesx($img));
        $this->assertSame((int) round($design->height_mm / 25.4 * 300), imagesy($img));
        $this->assertSame([300, 300], imageresolution($img));
    }

    public function test_png_and_pdf_are_refused_without_chrome_but_svg_works(): void
    {
        $this->app->instance(ChromeRenderer::class, new class extends ChromeRenderer
        {
            public function available(): bool
            {
                return false;
            }
        });
        [$user, $design] = $this->customerWithDesign();

        $this->actingAs($user)->post(route('designs.export.store', $design), ['format' => 'png', 'sides' => 'front', 'bleed_mm' => 0])
            ->assertSessionHasErrors('format');
        $this->assertSame(0, CardExport::count());

        $this->exportOf($user, $design, ['format' => 'svg']);
        $this->assertSame('completed', CardExport::first()->status);
    }

    public function test_only_the_owner_or_export_managers_can_download_or_delete(): void
    {
        [$user, $design] = $this->customerWithDesign();
        $export = $this->exportOf($user, $design, ['format' => 'svg']);
        $other = $this->userWithRole(User::ROLE_CUSTOMER);
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($other)->get(route('exports.download', $export))->assertForbidden();
        $this->actingAs($other)->delete(route('exports.destroy', $export))->assertForbidden();
        $this->actingAs($other)->getJson(route('exports.status', ['ids' => [$export->ulid]]))->assertExactJson([]);
        $this->actingAs($other)->get(route('exports.index'))->assertOk()->assertDontSee('Rivers card');

        $this->actingAs($user)->get(route('exports.download', $export))->assertOk()->assertDownload('rivers-card.svg');
        $this->actingAs($user)->getJson(route('exports.status', ['ids' => [$export->ulid]]))
            ->assertJsonPath($export->ulid.'.status', 'completed');
        $this->assertSame(1, $export->fresh()->downloads_count);

        $this->actingAs($admin)->get(route('exports.index', ['all' => 1]))->assertOk()->assertSee('Rivers card');
        $this->actingAs($admin)->delete(route('exports.destroy', $export))->assertRedirect();
        $this->assertModelMissing($export);
        Storage::disk('local')->assertMissing($export->file_path);
    }

    public function test_prune_removes_old_files_and_times_out_stuck_exports(): void
    {
        [$user, $design] = $this->customerWithDesign();
        $old = $this->exportOf($user, $design, ['format' => 'svg']);
        $fresh = $this->exportOf($user, $design, ['format' => 'svg']);
        $old->forceFill(['created_at' => now()->subDays(8)])->save();
        $stuck = CardExport::create(['user_id' => $user->id, 'card_design_id' => $design->id, 'format' => 'pdf', 'sides' => 'front', 'status' => 'queued', 'disk' => 'local']);
        $stuck->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->artisan('exports:prune')->assertSuccessful();

        Storage::disk('local')->assertMissing($old->file_path);
        $this->assertSame('expired', $old->fresh()->status);
        $this->assertNull($old->fresh()->file_path);
        Storage::disk('local')->assertExists($fresh->file_path);
        $this->assertSame('failed', $stuck->fresh()->status);
    }

    public function test_pdf_boxes_are_appended_as_an_incremental_update(): void
    {
        $pdf = FakeChromeRenderer::minimalPdf(2, 300, 200);
        $box = ['media' => [300, 200], 'bleed' => [10, 10, 290, 190], 'trim' => [20, 20, 280, 180]];

        $out = PdfBoxes::apply($pdf, [$box, $box]);

        $this->assertStringStartsWith($pdf, $out);
        $this->assertSame(2, substr_count($out, '/TrimBox [20 20 280 180]'));
        $this->assertSame(2, substr_count($out, '/BleedBox [10 10 290 190]'));
        $this->assertMatchesRegularExpression('/\/Prev \d+/', $out);
        $this->assertSame('garbage', PdfBoxes::apply('garbage', [$box]));
    }

    public function test_options_are_normalised(): void
    {
        $o = app(CardExportService::class)->normalise(['format' => 'svg', 'crop_marks' => true, 'cmyk' => true, 'bleed_mm' => 50, 'dpi' => 72]);

        $this->assertFalse($o['crop_marks']);
        $this->assertFalse($o['cmyk']);
        $this->assertSame(5.0, $o['bleed_mm']);
        $this->assertSame(300, $o['dpi']);
    }

    public function test_real_chrome_renders_a_pdf_when_installed(): void
    {
        $real = new ChromeRenderer;
        if (! $real->available()) {
            $this->markTestSkipped('Headless Chrome is not installed.');
        }
        $this->app->instance(ChromeRenderer::class, $real);
        [$user, $design] = $this->customerWithDesign();

        $export = $this->exportOf($user, $design, ['format' => 'pdf', 'crop_marks' => '1']);

        $this->assertSame('completed', $export->status, (string) $export->error);
        $pdf = Storage::disk('local')->get($export->file_path);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/TrimBox', $pdf);
    }
}
