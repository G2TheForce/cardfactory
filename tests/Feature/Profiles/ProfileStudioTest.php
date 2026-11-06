<?php

namespace Tests\Feature\Profiles;

use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\User;
use App\Services\DigitalProfileService;
use App\Support\ProfileThemes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ProfileStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    private function profile(string $theme = 'ps-crimson', array $attributes = []): DigitalProfile
    {
        $customer = Customer::factory()->create($attributes + [
            'full_name' => 'Aarati Shrestha',
            'professional_title' => 'Founder & CEO',
            'email' => 'aarati@example.com',
            'introduction' => "I build companies in Nepal.\n\nTwenty years of hospitality and tech.",
            'custom_fields' => [['label' => 'Years in business', 'value' => '20+'], ['label' => 'Languages', 'value' => 'Nepali, English']],
            'social_links' => ['instagram' => 'https://instagram.com/aarati', 'youtube' => 'https://youtube.com/@aarati'],
            'business' => ['services' => ['Strategy', 'Hospitality', 'Investment']],
        ]);

        return app(DigitalProfileService::class)->create($customer, ['profile_theme' => $theme], $this->userWithRole(User::ROLE_ADMIN));
    }

    public function test_profile_studio_is_its_own_category_with_a_site_layout(): void
    {
        $groups = ProfileThemes::byCategory();
        $this->assertSame('Profile Studio', $groups['studio']['label']);
        $this->assertGreaterThanOrEqual(6, count($groups['studio']['themes']));
        foreach ($groups['studio']['themes'] as $key => $theme) {
            $this->assertTrue(ProfileThemes::isStudio($key), $key);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $theme['deep']);
        }
        $this->assertFalse(ProfileThemes::isStudio('classic'));

        // Story first, contact panel last, and pill buttons like the design.
        $profile = $this->profile();
        $this->assertSame(['actions', 'about', 'social'], array_slice(array_column($profile->layout, 'key'), 0, 3));
        $this->assertSame('contact', last($profile->layout)['key']);
        $this->assertSame('pill', $profile->settings['button_style']);
        $this->assertSame('profiles.public-studio', app(DigitalProfileService::class)->publicView($profile));
    }

    public function test_public_page_renders_the_site_with_every_profile_feature(): void
    {
        $profile = $this->profile();
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $this->actingAs($admin)->put(route('profiles.update', $profile), [
            'headline' => 'Open to partnerships', 'greeting' => 'Namaste, I’m', 'motto' => 'Build slowly. Build to last.',
            'social_manual' => ['youtube' => '12.4K'], 'ctas' => [['label' => 'Book a call', 'url' => 'https://cal.com/aarati', 'style' => 'solid']],
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('profiles.public', $profile->slug))->assertOk()
            ->assertSee('sx-hero', false)->assertDontSee('pf-card', false)
            ->assertSee('Aarati')->assertSee('Shrestha.')
            ->assertSee('Namaste, I’m')->assertSee('Open to partnerships')
            ->assertSee('Build slowly. Build to last.')
            // Number-like custom fields become count-up stats; the rest are facts.
            ->assertSee('data-count="20"', false)->assertSee('Years in business')->assertSee('Nepali, English')
            ->assertSee('12.4K')->assertSee('YouTube subscribers')
            ->assertSee('Strategy')->assertSee('Hospitality')
            ->assertSee('Book a call')->assertSee('data-target="cta-1"', false)
            ->assertSee('data-track="social"', false)->assertSee('Follow on Instagram')
            ->assertSee(route('profiles.vcard', $profile->slug))
            ->assertSee('Save contact')->assertSee('QR code')->assertSee('sendBeacon', false)
            ->assertSee('sx-cform', false)->assertSee('aarati@example.com');

        $this->assertSame(1, AnalyticsEvent::where('event_type', AnalyticsEvent::PROFILE_VIEW)->count());
    }

    public function test_layout_switches_and_style_options_apply(): void
    {
        $profile = $this->profile('ps-night');
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $layout = collect(ProfileThemes::defaultLayout('ps-night'))->map(fn ($r) => ['key' => $r['key'], 'enabled' => ! in_array($r['key'], ['business', 'contact'], true)])->all();

        $this->actingAs($admin)->put(route('profiles.update', $profile), [
            'layout' => $layout, 'card_style' => 'glass', 'effect' => 'grid', 'font' => 'mono', 'social_style' => 'icons', 'show_qr' => 0,
        ])->assertSessionHasNoErrors();

        $this->get(route('profiles.preview', $profile))->assertOk()
            ->assertSee('Preview')->assertDontSee('sendBeacon', false)
            ->assertDontSee('Send a message')->assertDontSee('Strategy')
            ->assertSee('sx-grid', false)->assertSee('backdrop-filter', false)->assertSee('ui-monospace', false)
            ->assertSee('sx-round', false)->assertDontSee('Follow on Instagram')->assertDontSee('QR code');
    }

    public function test_editor_offers_studio_themes_and_live_preview_uses_the_site(): void
    {
        $profile = $this->profile('classic');
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('profiles.edit', $profile))->assertOk()
            ->assertSee('Profile Studio themes')->assertSee('Himalayan Crimson')->assertSee('Motto');

        $this->put(route('profiles.draft-preview', $profile), ['profile_theme' => 'ps-tea', 'motto' => 'Grow quietly.'])
            ->assertOk()->assertSee('sx-hero', false)->assertSee('Grow quietly.');
        $this->assertSame('classic', $profile->fresh()->profile_theme);

        $this->put(route('profiles.update', $profile), ['greeting' => str_repeat('x', 61)])->assertSessionHasErrors('greeting');
    }

    public function test_studio_profile_exports_as_a_static_site(): void
    {
        $profile = $this->profile('ps-everest');
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->post(route('profiles.site.export', $profile), ['site_url' => 'https://aarati.netlify.app'])->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $html = $zip->getFromName('index.html');
        $zip->close();

        $this->assertStringContainsString('sx-hero', $html);
        $this->assertStringContainsString('href="contact.vcf"', $html);
        $this->assertStringContainsString('https://aarati.netlify.app/', $html);
        $this->assertStringNotContainsString('sendBeacon', $html);
        $this->assertStringNotContainsString('localhost', $html);
    }
}
