<?php

namespace Tests\Feature\Themes;

use App\Models\CardTheme;
use App\Models\User;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemeSeeder::class);
    }

    private function theme(string $slug = 'corporate-executive'): CardTheme
    {
        return CardTheme::where('slug', $slug)->firstOrFail();
    }

    public function test_seeder_creates_twenty_published_editable_themes(): void
    {
        $this->assertSame(20, CardTheme::published()->where('is_system', true)->count());
        $this->assertSame(17, CardTheme::distinct('category')->count('category'));

        $theme = $this->theme();
        $this->assertNotEmpty($theme->front['elements']);
        $this->assertNotEmpty($theme->back['elements']);
        $this->assertArrayHasKey('primary', $theme->palette);
        $this->assertArrayHasKey('heading', $theme->fonts);

        $this->seed(ThemeSeeder::class); // idempotent
        $this->assertSame(20, CardTheme::count());
    }

    public function test_gallery_lists_published_themes_and_filters_by_category(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        CardTheme::where('slug', 'minimalist')->update(['status' => 'draft']);

        $this->actingAs($user)->get('/themes')->assertOk()
            ->assertSee('Corporate Executive')->assertDontSee('>Minimalist<', false);

        $this->actingAs($user)->get('/themes?category=legal')->assertOk()
            ->assertSee('Lawyer and Legal')->assertDontSee('Corporate Executive');
    }

    public function test_customers_cannot_see_drafts_or_other_users_private_themes(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $other = $this->userWithRole(User::ROLE_CUSTOMER);
        $draft = tap($this->theme('minimalist'))->update(['status' => 'draft']);
        $private = CardTheme::factory()->create(['status' => 'private', 'created_by' => $other->id]);

        $this->actingAs($user)->get("/themes/{$draft->ulid}")->assertForbidden();
        $this->actingAs($user)->get("/themes/{$private->ulid}")->assertForbidden();
        $this->actingAs($user)->post("/themes/{$private->ulid}/duplicate")->assertForbidden();
    }

    public function test_user_can_customise_and_save_as_new_private_theme(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $theme = $this->theme();

        $this->actingAs($user)->post("/themes/{$theme->ulid}/customize", [
            'name' => 'My red card',
            'palette' => ['primary' => '#b91c1c'],
            'fonts' => ['heading' => 'Playfair Display'],
            'corner_radius_mm' => 2,
        ])->assertRedirect();

        $copy = CardTheme::where('name', 'My red card')->firstOrFail();
        $this->assertSame('private', $copy->status);
        $this->assertSame($user->id, $copy->created_by);
        $this->assertSame($theme->id, $copy->parent_id);
        $this->assertSame('#b91c1c', $copy->palette['primary']);
        $this->assertSame('Playfair Display', $copy->fonts['heading']);
        $this->assertEquals(2, $copy->layout['corner_radius_mm']);
        $name = collect($copy->front['elements'])->firstWhere('id', 'name');
        $this->assertSame('Playfair Display', $name['fontFamily']);
        $this->assertSame('#0f2747', $theme->fresh()->palette['primary'], 'original is untouched');
        $this->assertSame(1, $theme->fresh()->usage_count);
    }

    public function test_owner_can_update_and_reset_private_theme_but_not_system_theme(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $theme = $this->theme();

        $this->actingAs($user)->put("/themes/{$theme->ulid}/customize", ['palette' => ['primary' => '#000000']])->assertForbidden();

        $this->actingAs($user)->post("/themes/{$theme->ulid}/duplicate")->assertRedirect();
        $copy = CardTheme::where('parent_id', $theme->id)->firstOrFail();

        $this->actingAs($user)->put("/themes/{$copy->ulid}/customize", ['palette' => ['accent' => '#00ff00'], 'name' => 'ignored'])->assertRedirect();
        $this->assertSame('#00ff00', $copy->fresh()->palette['accent']);

        $this->actingAs($user)->post("/themes/{$copy->ulid}/reset")->assertRedirect();
        $this->assertSame($theme->palette['accent'], $copy->fresh()->palette['accent']);
    }

    public function test_live_preview_returns_svg_for_unsaved_changes(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $theme = $this->theme();

        $this->actingAs($user)->postJson("/themes/{$theme->ulid}/preview", ['palette' => ['primary' => '#abcdef']])
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('front', fn ($svg) => str_contains($svg, '#abcdef'))->etc());

        $this->actingAs($user)->postJson("/themes/{$theme->ulid}/preview", ['palette' => ['primary' => 'red;}']])->assertUnprocessable();
    }

    public function test_standalone_svg_preview(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $theme = $this->theme();

        $this->actingAs($user)->get("/themes/{$theme->ulid}/preview/back.svg")
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('@import url(', false);
        $this->actingAs($user)->get("/themes/{$theme->ulid}/preview/inside.svg")->assertNotFound();
    }

    public function test_favourites_toggle(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $theme = $this->theme();

        $this->actingAs($user)->postJson("/themes/{$theme->ulid}/favorite")->assertJson(['favorite' => true]);
        $this->actingAs($user)->get('/themes?scope=favorites')->assertSee('Corporate Executive')->assertDontSee('Minimalist');
        $this->actingAs($user)->postJson("/themes/{$theme->ulid}/favorite")->assertJson(['favorite' => false]);
    }

    public function test_only_publishers_can_publish_unpublish_and_delete(): void
    {
        $designer = $this->userWithRole(User::ROLE_DESIGNER); // themes.manage only
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $theme = $this->theme();

        $this->actingAs($designer)->post("/themes/{$theme->ulid}/unpublish")->assertForbidden();
        $this->actingAs($designer)->delete("/themes/{$theme->ulid}")->assertForbidden();

        $this->actingAs($admin)->post("/themes/{$theme->ulid}/unpublish")->assertRedirect();
        $this->assertSame('draft', $theme->fresh()->status);
        $this->actingAs($admin)->post("/themes/{$theme->ulid}/publish")->assertRedirect();
        $this->assertSame('published', $theme->fresh()->status);

        $this->actingAs($admin)->delete("/themes/{$theme->ulid}")->assertRedirect('/themes');
        $this->assertSoftDeleted($theme);
    }

    public function test_designer_can_create_and_edit_theme_with_scene_json(): void
    {
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $payload = [
            'name' => 'Studio Mono', 'category' => 'minimal', 'orientation' => 'landscape', 'width_mm' => 85.6, 'height_mm' => 54,
            'palette' => ['primary' => '#111111', 'secondary' => '#222222', 'accent' => '#ff0000', 'background' => '#ffffff', 'text' => '#111111', 'muted' => '#777777'],
            'fonts' => ['heading' => 'Inter', 'body' => 'Inter'],
            'layout' => ['bleed_mm' => 3, 'safe_mm' => 3, 'corner_radius_mm' => 0],
            'front' => json_encode(['background' => ['type' => 'solid', 'color' => '#ffffff'], 'elements' => [['type' => 'heading', 'text' => '{{full_name}}', 'x' => 5, 'y' => 5, 'width' => 60]]]),
            'back' => json_encode(['elements' => []]),
        ];

        $this->actingAs($designer)->post('/themes', $payload)->assertRedirect();
        $theme = CardTheme::where('name', 'Studio Mono')->firstOrFail();
        $this->assertSame('draft', $theme->status);
        $this->assertSame('{{full_name}}', $theme->front['elements'][0]['text']);

        $this->actingAs($designer)->put("/themes/{$theme->ulid}", array_merge($payload, ['front' => '{not json']))
            ->assertSessionHasErrors('front');

        $this->actingAs($designer)->put("/themes/{$theme->ulid}", array_merge($payload, ['front' => json_encode(['elements' => [['type' => 'text', 'color' => 'javascript']]])]))
            ->assertSessionHasErrors('front.elements.0.color');
    }

    public function test_customers_cannot_open_admin_theme_editor(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);

        $this->actingAs($user)->get('/themes/create')->assertForbidden();
        $this->actingAs($user)->get('/themes/'.$this->theme()->ulid.'/edit')->assertForbidden();
    }

    public function test_api_lists_and_shows_themes(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $token = $user->createToken('t', ['read', 'write'])->plainTextToken;
        $theme = $this->theme();

        $this->withToken($token)->getJson('/api/v1/themes?category=corporate')
            ->assertOk()->assertJsonPath('data.0.id', $theme->ulid)->assertJsonMissingPath('data.0.front');

        $this->withToken($token)->getJson("/api/v1/themes/{$theme->ulid}")
            ->assertOk()->assertJsonPath('data.palette.primary', '#0f2747')->assertJsonStructure(['data' => ['front' => ['elements'], 'back', 'preview']]);

        $this->withToken($token)->postJson("/api/v1/themes/{$theme->ulid}/favorite")->assertJsonPath('data.favorite', true);
    }
}
