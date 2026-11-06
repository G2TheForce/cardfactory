<?php

namespace Tests\Feature\Profiles;

use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\User;
use App\Services\DigitalProfileService;
use App\Services\Social\SocialHandle;
use App\Services\Social\SocialStatsService;
use App\Support\ProfileThemes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    private function profile(array $social = [], string $theme = 'creator'): DigitalProfile
    {
        $customer = Customer::factory()->create(['full_name' => 'Maya Rivers', 'social_links' => $social]);

        return app(DigitalProfileService::class)->create($customer, ['profile_theme' => $theme], $this->userWithRole(User::ROLE_ADMIN));
    }

    public function test_themes_come_in_personal_and_professional_groups(): void
    {
        $groups = ProfileThemes::byCategory();
        $this->assertSame(['personal', 'professional', 'studio'], array_keys($groups));
        $this->assertArrayHasKey('creator', $groups['personal']['themes']);
        $this->assertArrayHasKey('executive', $groups['professional']['themes']);
        foreach (ProfileThemes::THEMES as $key => $theme) {
            $this->assertArrayHasKey($theme['category'], ProfileThemes::CATEGORIES, $key);
        }

        // Personal pages lead with social buttons; professional ones keep the default order.
        $this->assertSame(['actions', 'social'], array_slice(array_column($this->profile([], 'creator')->layout, 'key'), 0, 2));
        $this->assertSame('ctas', $this->profile([], 'executive')->layout[1]['key']);
    }

    public function test_owner_customises_the_page_and_types_in_follower_counts(): void
    {
        $profile = $this->profile(['youtube' => 'https://youtube.com/@maya', 'tiktok' => 'https://www.tiktok.com/@maya']);
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('profiles.edit', $profile))->assertOk()
            ->assertSee('Personal themes')->assertSee('Live preview')->assertSee('TikTok has no public API');

        $this->put(route('profiles.update', $profile), [
            'background' => '#101010', 'header_style' => 'centered', 'font' => 'rounded', 'social_style' => 'buttons',
            'show_follower_counts' => 1, 'social_manual' => ['youtube' => '12.4K', 'tiktok' => '1,250,000', 'myspace' => '5'],
        ])->assertSessionHasNoErrors();

        $settings = $profile->fresh()->settings;
        $this->assertSame('#101010', $settings['background']);
        $this->assertSame(12400, $settings['social_counts']['youtube']['manual']);
        $this->assertSame(1250000, $settings['social_counts']['tiktok']['manual']);
        $this->assertArrayNotHasKey('myspace', $settings['social_counts']);

        $page = $this->get(route('profiles.public', $profile->slug))->assertOk();
        $page->assertSee('Subscribe on YouTube')->assertSee('12.4K subscribers')
            ->assertSee('Follow on TikTok')->assertSee('1.3M followers')
            ->assertSee('ui-rounded', false);

        // A dark custom background gets light text.
        $theme = ProfileThemes::resolve('creator', $settings);
        $this->assertSame('#f4f4f5', $theme['colors']['text']);
        $this->assertSame('centered', $theme['header']);

        $this->put(route('profiles.update', $profile), ['social_manual' => ['youtube' => 'lots']])->assertSessionHasErrors('social_manual.youtube');

        // Counts can be hidden, and icons used instead of buttons.
        $this->put(route('profiles.update', $profile), ['social_style' => 'icons', 'show_follower_counts' => 0]);
        $this->get(route('profiles.public', $profile->slug))->assertDontSee('Subscribe on YouTube')->assertDontSee('12.4K');
    }

    public function test_social_accounts_are_added_on_the_profile(): void
    {
        $profile = $this->profile();
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('profiles.update', $profile), ['social_links' => [
            'facebook' => '@maya.rivers', 'instagram' => 'instagram.com/maya', 'telegram' => 'https://t.me/mayachannel', 'linkedin' => '', 'myspace' => 'https://myspace.com/x',
        ]])->assertSessionHasNoErrors();

        $links = $profile->customer->fresh()->social_links;
        $this->assertSame(['facebook' => 'https://www.facebook.com/maya.rivers', 'instagram' => 'https://instagram.com/maya', 'telegram' => 'https://t.me/mayachannel'], $links);

        $this->get(route('profiles.public', $profile->slug))->assertSee('Follow on Facebook')->assertSee('Join on Telegram');
        $this->put(route('profiles.update', $profile), ['social_links' => ['x' => 'not a link at all']])->assertSessionHasErrors('social_links.x');
    }

    public function test_background_image_style_and_effects(): void
    {
        $profile = $this->profile();
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('profiles.update', $profile), [
            'background_image' => UploadedFile::fake()->image('beach.jpg', 1600, 1000),
            'background_overlay' => 60, 'background_blur' => 8, 'card_style' => 'glass', 'effect' => 'grid', 'glow' => '1',
        ])->assertSessionHasNoErrors();

        $settings = $profile->fresh()->settings;
        Storage::disk('public')->assertExists($settings['background_image_path']);
        $this->assertSame([60, 8, 'glass', 'grid', true], [$settings['background_overlay'], $settings['background_blur'], $settings['card_style'], $settings['effect'], $settings['glow']]);

        $this->get(route('profiles.public', $profile->slug))
            ->assertSee('pf-backdrop-img', false)->assertSee('blur(8px)', false)->assertSee('backdrop-filter', false)->assertSee('pf-grid', false);

        $this->put(route('profiles.update', $profile), ['background_overlay' => 95])->assertSessionHasErrors('background_overlay');

        $path = $settings['background_image_path'];
        $this->put(route('profiles.update', $profile), ['remove_background_image' => 1]);
        $this->assertNull($profile->fresh()->settings['background_image_path']);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_live_preview_renders_unsaved_changes_without_saving(): void
    {
        $profile = $this->profile(['github' => 'https://github.com/maya']);
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('profiles.draft-preview', $profile), [
            'profile_theme' => 'cyber', 'headline' => 'Building the future', 'social_links' => ['facebook' => '@maya'],
        ])->assertOk()->assertSee('Building the future')->assertSee('Follow on Facebook')->assertSee('pf-grid', false);

        $this->assertSame('creator', $profile->fresh()->profile_theme);
        $this->assertNull($profile->fresh()->settings['headline'] ?? null);
        $this->assertSame(['github' => 'https://github.com/maya'], $profile->customer->fresh()->social_links);

        $this->actingAs($this->userWithRole(User::ROLE_CUSTOMER))->put(route('profiles.draft-preview', $profile), [])->assertForbidden();
    }

    public function test_counts_are_fetched_from_official_apis(): void
    {
        config(['services.youtube.key' => 'yt-key']);
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['statistics' => ['subscriberCount' => '2340000', 'hiddenSubscriberCount' => false]]]]),
            'api.github.com/users/maya' => Http::response(['followers' => 812]),
        ]);
        $profile = $this->profile([
            'youtube' => 'https://www.youtube.com/@maya', 'github' => 'https://github.com/maya', 'linkedin' => 'https://linkedin.com/in/maya',
        ]);

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))
            ->post(route('profiles.social.refresh', $profile))
            ->assertRedirect(route('profiles.edit', [$profile, 'tab' => 'social']))->assertSessionHas('success');

        $counts = $profile->fresh()->settings['social_counts'];
        $this->assertSame(2340000, $counts['youtube']['count']);
        $this->assertSame(812, $counts['github']['count']);
        $this->assertNull($counts['linkedin']['count']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'forHandle=%40maya') && str_contains($r->url(), 'key=yt-key'));

        $this->get(route('profiles.public', $profile->slug))->assertSee('2.3M subscribers')->assertSee('812 followers');

        // A typed-in count wins over the fetched one.
        $this->assertSame(5, SocialStatsService::displayCount(['github' => ['count' => 812, 'manual' => 5]], 'github'));
    }

    public function test_a_failing_api_keeps_the_last_count(): void
    {
        Http::fake(['api.github.com/*' => Http::sequence()->push(['followers' => 40])->push('oops', 500)]);
        $profile = $this->profile(['github' => 'https://github.com/maya']);
        $stats = app(SocialStatsService::class);

        $stats->refresh($profile);
        $counts = $stats->refresh($profile->fresh());

        $this->assertSame(40, $counts['github']['count']);
        $this->assertNotNull($counts['github']['error']);

        $this->artisan('profiles:refresh-social-counts')->assertSuccessful();
    }

    public function test_handles_are_read_from_profile_links(): void
    {
        $this->assertSame(['type' => 'handle', 'value' => 'mkbhd'], SocialHandle::parse('youtube', 'https://www.youtube.com/@mkbhd'));
        $this->assertSame(['type' => 'id', 'value' => 'UCBJycsmduvYEL83R_U4JriQ'], SocialHandle::parse('youtube', 'youtube.com/channel/UCBJycsmduvYEL83R_U4JriQ'));
        $this->assertSame(['type' => 'username', 'value' => 'pewdiepie'], SocialHandle::parse('youtube', 'https://youtube.com/user/pewdiepie'));
        $this->assertSame(['type' => 'handle', 'value' => 'nasa'], SocialHandle::parse('instagram', 'https://www.instagram.com/nasa/?hl=en'));
        $this->assertSame(['type' => 'handle', 'value' => 'nasa'], SocialHandle::parse('x', '@nasa'));
        $this->assertSame(['type' => 'id', 'value' => '12345'], SocialHandle::parse('facebook', 'https://facebook.com/profile.php?id=12345'));
        $this->assertNull(SocialHandle::parse('youtube', 'https://youtube.com/watch?v=abc'));
        $this->assertNull(SocialHandle::parse('x', 'https://x.com/'));

        $this->assertSame('999', SocialStatsService::format(999));
        $this->assertSame('1.5K', SocialStatsService::format(1500));
        $this->assertSame('12K', SocialStatsService::format(12000));
        $this->assertSame('2M', SocialStatsService::format(2_000_000));
    }

    public function test_profile_downloads_as_a_static_website(): void
    {
        $profile = $this->profile(['instagram' => 'https://instagram.com/maya']);
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->post(route('profiles.site.export', $profile), ['site_url' => 'https://maya.netlify.app'])->assertOk();
        $this->assertStringContainsString('maya-rivers-site.zip', $response->headers->get('content-disposition'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $html = $zip->getFromName('index.html');
        $this->assertNotFalse($zip->getFromName('README.txt'));
        $this->assertStringContainsString('URL;TYPE=PROFILE:https://maya.netlify.app/', $zip->getFromName('contact.vcf'));
        $zip->close();

        $this->assertStringContainsString('Maya Rivers', $html);
        $this->assertStringContainsString('Follow on Instagram', $html);
        $this->assertStringContainsString('href="contact.vcf"', $html);
        $this->assertStringContainsString('https://maya.netlify.app/', $html);
        $this->assertStringNotContainsString('sendBeacon', $html);
        $this->assertStringNotContainsString('localhost', $html);

        $this->post(route('profiles.site.export', $profile), ['site_url' => 'not a url'])->assertSessionHasErrors('site_url');

        // Customers can't export someone else's page.
        $this->actingAs($this->userWithRole(User::ROLE_CUSTOMER))->post(route('profiles.site.export', $profile))->assertForbidden();
    }
}
