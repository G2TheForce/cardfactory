<?php

namespace Tests\Feature\Themes;

use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Models\User;
use App\Services\CardDesignService;
use App\Services\ThemeManagementService;
use App\Support\Themes\PremiumThemes;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiumThemesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemeSeeder::class);
    }

    public function test_premium_themes_are_seeded_as_portrait_cards_with_bundled_artwork(): void
    {
        $definitions = PremiumThemes::definitions();
        $this->assertCount(8, $definitions);

        foreach (PremiumThemes::all() as $data) {
            $theme = CardTheme::where('slug', $data['slug'])->firstOrFail();

            $this->assertSame('portrait', $theme->orientation);
            $this->assertEquals(54.0, (float) $theme->width_mm);
            $this->assertEquals(85.6, (float) $theme->height_mm);
            $this->assertTrue($theme->is_premium);

            // The validator keeps the built-in artwork, and the files exist.
            $this->assertSame('image', $theme->front['background']['type']);
            $this->assertFileExists(public_path(ltrim($theme->front['background']['src'], '/')));
            $this->assertFileExists(public_path(ltrim($theme->layout['sample']['logo'], '/')));

            $types = collect($theme->back['elements'])->pluck('type');
            $this->assertContains('qr', $types);
            $this->assertContains('logo', $types);
        }
    }

    public function test_previews_use_the_theme_sample_business(): void
    {
        $theme = CardTheme::where('slug', 'artisan-bakery-cafe')->firstOrFail();
        $preview = app(ThemeManagementService::class)->preview($theme);

        $this->assertStringContainsString('Golden Crumb', $preview['front']);
        $this->assertStringContainsString('/images/card-themes/bakery-cafe/front.svg', $preview['front']);
        $this->assertStringContainsString('/images/card-themes/bakery-cafe/emblem.svg', $preview['back']);
        $this->assertNotFalse(@simplexml_load_string($preview['back']));
        $this->assertContains('Kaushan Script', $preview['fonts']);
    }

    public function test_designs_from_premium_themes_are_portrait_and_use_the_sample_until_a_customer_is_attached(): void
    {
        $user = $this->userWithRole(User::ROLE_DESIGNER);
        $theme = CardTheme::where('slug', 'fitness-studio-neon')->firstOrFail();

        $this->actingAs($user)->post('/designs', ['name' => 'Gym card', 'theme' => $theme->ulid])->assertRedirect();

        $design = CardDesign::where('name', 'Gym card')->firstOrFail();
        $this->assertSame('portrait', $design->orientation);
        $this->assertSame('IronPulse', app(CardDesignService::class)->mergeData($design)['company']);

        $this->actingAs($user)->get("/designs/{$design->ulid}/edit")->assertOk();
    }

    public function test_gallery_shows_portrait_themes_side_by_side(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);

        $this->actingAs($user)->get('/themes?category=fitness')->assertOk()
            ->assertSee('Fitness Studio Neon')
            ->assertSee('w-[39.8%]', false)
            ->assertDontSee('Corporate Executive');
    }
}
