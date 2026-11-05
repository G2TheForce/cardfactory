<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\BrandKit;
use App\Models\CardDesign;
use App\Models\CardExport;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\NfcCard;
use App\Models\User;
use App\Services\AnalyticsReportService;
use App\Services\ChromeRenderer;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeChromeRenderer;
use Tests\TestCase;

/**
 * The whole job, end to end, through HTTP only: add a customer, brand and
 * design their card, publish a digital profile, ship an NFC card, export
 * print files, then tap, scan and read the analytics.
 */
class GoldenPathTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemeSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Http::fake(['fonts.googleapis.com/*' => Http::response('', 404)]);
        $this->app->instance(ChromeRenderer::class, new FakeChromeRenderer);
    }

    public function test_from_new_customer_to_tapped_card(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $this->actingAs($admin);

        // 1. Customer
        $this->post(route('customers.store'), ['full_name' => 'Priya Raman', 'professional_title' => 'Architect', 'email' => 'priya@example.com', 'mobile' => '+44 20 7946 0000'])->assertRedirect();
        $customer = Customer::where('email', 'priya@example.com')->firstOrFail();

        // 2. Brand kit
        $this->post(route('brand-kits.store'), [
            'name' => 'Raman Studio', 'primary_color' => '#0F766E', 'secondary_color' => '#1F2937', 'accent_color' => '#F59E0B',
            'heading_font' => 'Montserrat', 'body_font' => 'Inter', 'background_type' => 'solid', 'background_color' => '#FFFFFF',
            'company' => ['name' => 'Raman Studio', 'website' => 'https://raman.example'],
        ])->assertRedirect();
        $kit = BrandKit::firstOrFail();

        // 3. Design from a theme, branded, edited and finalised
        $this->post(route('designs.store'), ['name' => 'Priya card', 'theme' => CardTheme::where('slug', 'corporate-executive')->value('ulid'), 'customer' => $customer->ulid])->assertRedirect();
        $design = CardDesign::where('name', 'Priya card')->firstOrFail();
        $this->get(route('designs.edit', $design))->assertOk();
        $this->post(route('brand-kits.apply', $kit), ['design' => $design->ulid])->assertSessionHasNoErrors();
        $this->putJson(route('designs.update', $design), ['version' => 2, 'status' => 'final'])->assertOk()->assertJsonPath('version', 3);
        $render = $this->postJson(route('designs.render', $design), ['side' => $design->fresh()->front])->assertOk();
        $this->assertStringContainsString('Priya Raman', $render->json('svg'));
        $this->assertStringContainsString('RAMAN STUDIO', $render->json('svg')); // brand kit company, theme uppercases it

        // 4. Digital profile
        $this->post(route('profiles.store'), ['customer' => $customer->ulid, 'profile_theme' => 'midnight'])->assertRedirect();
        $profile = DigitalProfile::where('customer_id', $customer->id)->firstOrFail();

        // 5. NFC card from stock, assigned to the profile
        $this->post(route('nfc.store'), ['mode' => 'generate', 'count' => 1, 'tag_type' => 'NTAG215', 'prefix' => 'GP'])->assertRedirect();
        $card = NfcCard::firstOrFail();
        $this->post(route('nfc.assign', $card), ['profile' => $profile->ulid])->assertSessionHasNoErrors();

        // 6. Print-ready export
        $this->post(route('designs.export.store', $design), ['format' => 'pdf', 'sides' => 'both', 'bleed_mm' => 3])->assertRedirect(route('exports.index'));
        $export = CardExport::firstOrFail();
        $this->assertSame('completed', $export->fresh()->status, (string) $export->fresh()->error);
        $this->get(route('exports.download', $export))->assertOk();

        // 7. The public side: tap, scan, view, save contact (as an anonymous phone)
        auth()->logout();
        $this->withHeader('User-Agent', self::PHONE_UA)->get(route('nfc.tap', $card->card_identifier))->assertRedirect(route('profiles.public', $profile->slug).'?via=nfc');
        $this->withHeader('User-Agent', self::PHONE_UA)->get(route('qr.scan', $profile->qrCodes()->first()->code))->assertRedirect();
        $this->withHeader('User-Agent', self::PHONE_UA)->get(route('profiles.public', $profile->slug))->assertOk()->assertSee('Priya Raman');
        $vcard = $this->withHeader('User-Agent', self::PHONE_UA)->get(route('profiles.vcard', $profile->slug))->assertOk();
        $this->assertStringContainsString('FN:Priya Raman', $vcard->getContent());

        // 8. Analytics, without storing who the visitor was
        $report = app(AnalyticsReportService::class)->report($admin, ['period' => '7d']);
        $this->assertSame(1, $report['totals']['nfc_taps']['value']);
        $this->assertSame(1, $report['totals']['qr_scans']['value']);
        $this->assertSame(1, $report['totals']['contacts']['value']);
        $this->assertSame('active', $card->fresh()->status);
        $event = AnalyticsEvent::firstOrFail();
        $this->assertArrayNotHasKey('ip_address', $event->getAttributes());
        $this->assertArrayNotHasKey('user_agent', $event->getAttributes());

        $this->actingAs($admin)->get(route('analytics.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }
}
