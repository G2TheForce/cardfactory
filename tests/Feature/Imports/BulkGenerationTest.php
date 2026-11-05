<?php

namespace Tests\Feature\Imports;

use App\Models\CardDesign;
use App\Models\CardExport;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\CustomerImport;
use App\Models\DigitalProfile;
use App\Models\User;
use App\Services\CardDesignService;
use App\Services\Export\ChromeRenderer;
use App\Support\BatchFields;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\FakeChromeRenderer;
use Tests\TestCase;
use ZipArchive;

class BulkGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = "Name,Job Title,Company,Email,Phone,Website,Slug\n"
        ."Priya Raman,Head of Design,Northwind,priya@example.com,+44 7700 900111,northwind.example,priya-raman\n"
        ."Tomás García,Engineer,Northwind,tomas@example.com,+34 600 123 456,,\n"
        .",Intern,Northwind,intern@example.com,,,\n"
        ."Lars Berg,PM,Northwind,not-an-email,,,\n"
        ."Amara Okafor,Ops,Northwind,PRIYA@example.com,,,\n"
        ."Jon Doe,Sales,Northwind,jon@example.com,abc,,\n"
        ."Ana Lima,Sales,Northwind,ana@example.com,,,admin\n";

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

    private function upload(User $user, string $csv = self::CSV, string $name = 'team.csv'): CustomerImport
    {
        $this->actingAs($user)->post(route('imports.store'), ['file' => UploadedFile::fake()->createWithContent($name, $csv)])
            ->assertRedirect();

        return CustomerImport::latest('id')->firstOrFail();
    }

    private function check(User $user, CustomerImport $import, array $overrides = []): CustomerImport
    {
        $this->actingAs($user)->post(route('imports.check', $import), $overrides + [
            'mapping' => $import->column_mapping,
            'source' => 'theme',
            'card_theme' => CardTheme::published()->firstOrFail()->ulid,
            'create_profiles' => 1,
            'profile_theme' => 'midnight',
            'skip_existing' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $import->fresh();
    }

    public function test_only_staff_with_imports_permission_can_use_the_generator(): void
    {
        foreach ([User::ROLE_CUSTOMER, User::ROLE_DESIGNER] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('imports.index'))->assertForbidden();
            $this->actingAs($user)->post(route('imports.store'), ['file' => UploadedFile::fake()->createWithContent('a.csv', self::CSV)])->assertForbidden();
        }

        $sales = $this->userWithRole(User::ROLE_SALES_MANAGER);
        $this->actingAs($sales)->get(route('imports.index'))->assertOk();
        $this->actingAs($sales)->get(route('imports.create'))->assertOk()->assertSee('Excel template');
    }

    public function test_upload_reads_headers_and_guesses_the_mapping(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $import = $this->upload($admin);

        $this->assertSame('uploaded', $import->status);
        $this->assertSame(7, $import->total_rows);
        $this->assertSame(['full_name' => 0, 'professional_title' => 1, 'company_name' => 2, 'email' => 3, 'mobile' => 4, 'website' => 5, 'profile_slug' => 6], $import->column_mapping);
        Storage::disk('local')->assertExists($import->file_path);

        $this->get(route('imports.show', $import))->assertOk()->assertSee('Match your columns')->assertSee('Priya Raman');
    }

    public function test_bad_files_are_rejected(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        config(['smartcard.imports.max_rows' => 3]);

        $this->actingAs($admin)->post(route('imports.store'), ['file' => UploadedFile::fake()->createWithContent('team.csv', self::CSV)])
            ->assertSessionHasErrors(['file' => 'The file has more than 3 rows. Split it into smaller files.']);
        $this->actingAs($admin)->post(route('imports.store'), ['file' => UploadedFile::fake()->createWithContent('only-header.csv', "Name,Email\n")])
            ->assertSessionHasErrors('file');
        $this->actingAs($admin)->post(route('imports.store'), ['file' => UploadedFile::fake()->create('photo.png', 10, 'image/png')])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, CustomerImport::count());
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    public function test_rows_are_validated_with_a_detailed_error_report(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        Customer::factory()->create(['email' => 'jon@example.com']);
        $import = $this->check($admin, $this->upload($admin));

        $this->assertSame('validated', $import->status);
        $this->assertSame(2, $import->valid_rows);
        $this->assertSame(5, $import->failed_rows);

        $problems = collect($import->errors)->map(fn ($e) => $e['line'].':'.$e['column'].':'.$e['level'])->all();
        $this->assertContains('4:Name:error', $problems);
        $this->assertContains('5:Email:error', $problems);
        $this->assertContains('6:Email:error', $problems); // same email as row 2 (case-insensitive)
        $this->assertContains('7:Email:skipped', $problems); // existing customer
        $this->assertContains('8:Slug:error', $problems); // reserved address
        $this->assertStringContainsString('Same email as row 2', collect($import->errors)->firstWhere('line', 6)['message']);

        $this->get(route('imports.show', $import))->assertOk()->assertSee('Generate 2 cards')->assertSee('Problems found');

        $csv = $this->get(route('imports.errors', $import))->assertOk()->streamedContent();
        $this->assertStringContainsString('Row,Column,Field,Problem,Result', $csv);
        $this->assertStringNotContainsString('not-an-email', $csv, 'error report must not repeat submitted values');
    }

    public function test_mapping_requires_a_name_column_and_a_card_source(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $import = $this->upload($admin);

        $this->actingAs($admin)->post(route('imports.check', $import), ['mapping' => ['email' => 3], 'source' => 'theme'])
            ->assertSessionHasErrors(['mapping.full_name', 'card_theme']);
        $this->actingAs($admin)->post(route('imports.check', $import), ['mapping' => ['full_name' => 0], 'source' => 'theme', 'card_theme' => 'nope'])
            ->assertSessionHasErrors('card_theme');
        $this->actingAs($admin)->post(route('imports.check', $import), ['mapping' => ['full_name' => 99], 'source' => 'theme'])
            ->assertSessionHasErrors('mapping.full_name');
    }

    public function test_generation_creates_customers_designs_profiles_and_qr_codes(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $import = $this->check($admin, $this->upload($admin));
        $source = $import->file_path;

        $this->actingAs($admin)->post(route('imports.generate', $import))->assertRedirect(route('imports.show', $import));
        $import->refresh();

        $this->assertSame('completed', $import->status);
        $this->assertSame(2, $import->generated_rows);
        $this->assertNull($import->file_path);
        Storage::disk('local')->assertMissing($source);

        $priya = Customer::where('email', 'priya@example.com')->firstOrFail();
        $this->assertSame($import->id, $priya->customer_import_id);
        $this->assertSame('https://northwind.example', $priya->website);
        $this->assertSame('Northwind', $priya->company->name);

        $design = CardDesign::where('customer_id', $priya->id)->firstOrFail();
        $this->assertSame($import->id, $design->customer_import_id);
        $profile = DigitalProfile::where('customer_id', $priya->id)->firstOrFail();
        $this->assertSame('priya-raman', $profile->slug);
        $this->assertSame('midnight', $profile->profile_theme);
        $this->assertSame($design->id, $profile->card_design_id);
        $this->assertCount(1, $profile->qrCodes);
        $this->assertSame('tomas-garcia', DigitalProfile::whereHas('customer', fn ($q) => $q->where('email', 'tomas@example.com'))->value('slug'));

        $this->get(route('imports.show', $import))->assertOk()->assertSee('/card/priya-raman')->assertSee('Download all cards');
        $csv = $this->get(route('imports.info', $import))->assertOk()->streamedContent();
        $this->assertStringContainsString(route('profiles.public', 'priya-raman'), $csv);
        $this->assertStringContainsString($profile->card_identifier, $csv);

        // Generating twice is not possible.
        $this->post(route('imports.generate', $import))->assertSessionHasErrors('import');
        $this->assertSame(2, Customer::whereNotNull('customer_import_id')->count());
    }

    public function test_a_template_design_is_copied_for_every_customer(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $template = app(CardDesignService::class)->create($admin, ['name' => 'Northwind house style', 'theme' => CardTheme::first()]);
        $import = $this->check($admin, $this->upload($admin), ['source' => 'design', 'card_design' => $template->ulid, 'create_profiles' => 0]);

        $this->post(route('imports.generate', $import));

        $designs = CardDesign::where('customer_import_id', $import->id)->get();
        // Without profiles the reserved "admin" address doesn't matter, so three rows are valid.
        $this->assertCount(3, $designs);
        $this->assertTrue($designs->every(fn ($d) => $d->customer_id && str_contains($d->name, 'Northwind house style')));
        $this->assertSame(0, DigitalProfile::count());
    }

    public function test_excel_files_keep_phone_numbers_and_the_template_round_trips(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $xlsx = $this->actingAs($admin)->get(route('imports.template', 'xlsx'))->assertOk()->getContent();
        $import = $this->upload($admin, $xlsx, 'template.xlsx');
        $this->assertCount(count(BatchFields::FIELDS), $import->column_mapping);

        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Full name', 'Mobile', 'Email'], ['Ada Lovelace', 447700900123, 'ada@example.com']]);
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        (new Xlsx($book))->save($path);
        $import = $this->check($admin, $this->upload($admin, (string) file_get_contents($path), 'people.xlsx'));
        unlink($path);

        $this->assertSame(1, $import->valid_rows);
        $this->post(route('imports.generate', $import));
        $this->assertSame('447700900123', Customer::where('email', 'ada@example.com')->value('mobile'));

        $this->assertStringContainsString('"Full name","Professional title"', $this->get(route('imports.template', 'csv'))->assertOk()->getContent());
    }

    public function test_imports_are_private_to_their_owner_and_administrators(): void
    {
        $owner = $this->userWithRole(User::ROLE_SALES_MANAGER);
        $import = $this->upload($owner);
        $otherSales = $this->userWithRole(User::ROLE_SALES_MANAGER);
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($otherSales)->get(route('imports.show', $import))->assertForbidden();
        $this->actingAs($otherSales)->get(route('imports.errors', $import))->assertForbidden();
        $this->actingAs($otherSales)->delete(route('imports.destroy', $import))->assertForbidden();
        $this->actingAs($otherSales)->get(route('imports.index'))->assertDontSee('team.csv');
        $this->actingAs($admin)->get(route('imports.show', $import))->assertOk();

        $this->actingAs($owner)->delete(route('imports.destroy', $import))->assertRedirect(route('imports.index'));
        $this->assertModelMissing($import);
        Storage::disk('local')->assertMissing($import->file_path);
    }

    public function test_batch_zip_and_combined_pdf_exports(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $import = $this->check($admin, $this->upload($admin));
        $this->post(route('imports.generate', $import));

        $this->post(route('imports.export', $import), ['kind' => 'zip', 'format' => 'svg', 'sides' => 'both', 'bleed_mm' => 2])
            ->assertRedirect(route('exports.index'));
        $zipExport = CardExport::latest('id')->firstOrFail();
        $this->assertSame('completed', $zipExport->status, (string) $zipExport->error);

        $tmp = tempnam(sys_get_temp_dir(), 'z');
        file_put_contents($tmp, Storage::disk('local')->get($zipExport->file_path));
        $zip = new ZipArchive;
        $zip->open($tmp);
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
        $csv = $zip->getFromName('cards.csv');
        $zip->close();
        unlink($tmp);
        $this->assertSame(['001-priya-raman/back.svg', '001-priya-raman/front.svg', '002-tomas-garcia/back.svg', '002-tomas-garcia/front.svg', 'cards.csv'], $names);
        $this->assertStringContainsString('priya-raman', $csv);

        $this->post(route('imports.export', $import), ['kind' => 'combined', 'sides' => 'both', 'bleed_mm' => 3, 'crop_marks' => 1])
            ->assertRedirect(route('exports.index'));
        $pdfExport = CardExport::latest('id')->firstOrFail();
        $this->assertSame('completed', $pdfExport->status, (string) $pdfExport->error);
        $this->assertStringEndsWith('team-2-cards.pdf', $pdfExport->file_path);
        $this->assertSame(4, substr_count(Storage::disk('local')->get($pdfExport->file_path), '/TrimBox'));
        $this->assertSame(4, substr_count(end($this->chrome->html), 'class="page '));

        $this->get(route('exports.index'))->assertOk()->assertSee('Combined PDF')->assertSee('ZIP of SVG files');

        // Others can't export someone else's batch.
        $this->actingAs($this->userWithRole(User::ROLE_SALES_MANAGER))
            ->post(route('imports.export', $import), ['kind' => 'combined', 'sides' => 'both', 'bleed_mm' => 0])->assertForbidden();
    }

    public function test_csv_downloads_neutralise_formulas_but_keep_phone_numbers(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $import = $this->check($admin, $this->upload($admin, "Name,Phone\n=HYPERLINK(\"x\"),+44 20 7946 0000\n"));
        $this->post(route('imports.generate', $import));

        $csv = $this->get(route('imports.info', $import))->streamedContent();
        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertStringContainsString('"+44 20 7946 0000"', $csv);
    }

    public function test_prune_removes_stale_unfinished_imports(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $stale = $this->upload($admin);
        $stale->forceFill(['updated_at' => now()->subDays(8)])->saveQuietly();
        CustomerImport::whereKey($stale->id)->update(['updated_at' => now()->subDays(8)]);
        $fresh = $this->upload($admin);

        $this->artisan('imports:prune')->assertSuccessful();

        $this->assertModelMissing($stale);
        Storage::disk('local')->assertMissing($stale->file_path);
        $this->assertModelExists($fresh);
    }
}
