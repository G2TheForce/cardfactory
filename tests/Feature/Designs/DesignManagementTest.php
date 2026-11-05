<?php

namespace Tests\Feature\Designs;

use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\User;
use App\Services\CardDesignService;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemeSeeder::class);
    }

    private function customerUser(): User
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        Customer::factory()->create(['user_id' => $user->id, 'full_name' => 'Jordan Rivers']);

        return $user;
    }

    private function designFor(User $user, array $attributes = []): CardDesign
    {
        return app(CardDesignService::class)->create($user, [
            'name' => 'Test card',
            'theme' => CardTheme::where('slug', 'corporate-executive')->first(),
            'customer' => $user->customerProfile,
        ] + $attributes);
    }

    private function textElement(array $overrides = []): array
    {
        return array_merge([
            'id' => 'el_test', 'type' => 'text', 'name' => 'Greeting', 'x' => 5, 'y' => 5, 'width' => 40, 'height' => 4,
            'text' => 'Hello {{full_name}}', 'fontFamily' => 'Inter', 'fontSize' => 9, 'fontWeight' => 600, 'color' => 'palette.primary',
        ], $overrides);
    }

    public function test_customer_creates_a_design_from_a_theme(): void
    {
        $user = $this->customerUser();
        $theme = CardTheme::where('slug', 'corporate-executive')->first();

        $response = $this->actingAs($user)->post(route('designs.store'), ['name' => 'My card', 'theme' => $theme->ulid]);

        $design = CardDesign::first();
        $response->assertRedirect(route('designs.edit', $design));
        $this->assertSame($user->id, $design->user_id);
        $this->assertSame($user->customerProfile->id, $design->customer_id);
        $this->assertSame($theme->id, $design->card_theme_id);
        $this->assertEquals($theme->palette, $design->settings['palette']);
        $this->assertCount(count($theme->front['elements']) + count($theme->back['elements']), $design->elements);
        $this->assertSame(1, $theme->fresh()->usage_count);
    }

    public function test_blank_design_uses_selected_size_and_orientation(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user)->post(route('designs.store'), ['name' => 'Tall', 'size' => 'us', 'orientation' => 'portrait'])->assertRedirect();

        $design = CardDesign::first();
        $this->assertEquals(50.8, (float) $design->width_mm);
        $this->assertEquals(88.9, (float) $design->height_mm);
        $this->assertSame('portrait', $design->orientation);
        $this->assertSame([], $design->front['elements']);
    }

    public function test_create_validates_input(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user)->post(route('designs.store'), ['name' => '', 'size' => 'huge', 'theme' => 'nope'])
            ->assertSessionHasErrors(['name', 'size', 'theme']);
    }

    public function test_customer_cannot_create_designs_for_another_customer(): void
    {
        $user = $this->customerUser();
        $other = Customer::factory()->create();

        $this->actingAs($user)->post(route('designs.store'), ['name' => 'X', 'size' => 'iso', 'customer' => $other->ulid])->assertForbidden();
        $this->assertSame(0, CardDesign::count());
    }

    public function test_editor_loads_for_owner_and_is_forbidden_for_others(): void
    {
        $owner = $this->customerUser();
        $design = $this->designFor($owner);

        $this->actingAs($owner)->get(route('designs.edit', $design))
            ->assertOk()->assertSee('cardEditor', false)->assertSee($design->name);

        $this->actingAs($this->customerUser())->get(route('designs.edit', $design))->assertForbidden();
        $this->actingAs($this->userWithRole(User::ROLE_SALES_MANAGER))->get(route('designs.edit', $design))->assertForbidden();
    }

    public function test_saving_validates_scenes_syncs_elements_and_bumps_version(): void
    {
        $user = $this->customerUser();
        $design = $this->designFor($user);

        $this->actingAs($user)->putJson(route('designs.update', $design), [
            'version' => 1,
            'name' => 'Renamed',
            'front' => ['background' => ['type' => 'solid', 'color' => '#ffffff'], 'elements' => [$this->textElement(['evil' => '<script>'])]],
            'palette' => ['primary' => '#ff0000'],
            'settings' => ['show_guides' => false],
        ])->assertOk()->assertJsonPath('version', 2);

        $design->refresh();
        $this->assertSame('Renamed', $design->name);
        $this->assertSame(2, $design->version);
        $this->assertArrayNotHasKey('evil', $design->front['elements'][0]);
        $this->assertSame('#ff0000', $design->settings['palette']['primary']);
        $this->assertFalse($design->settings['show_guides']);
        $this->assertSame(1, $design->elements()->where('side', 'front')->count());
        $this->assertSame('Greeting', $design->elements()->where('side', 'front')->first()->name);
    }

    public function test_saving_rejects_invalid_scenes_palettes_and_stale_versions(): void
    {
        $user = $this->customerUser();
        $design = $this->designFor($user);

        $this->actingAs($user)->putJson(route('designs.update', $design), [
            'front' => ['elements' => [$this->textElement(['color' => 'url(javascript:alert(1))'])]],
        ])->assertUnprocessable()->assertJsonValidationErrors('front.elements.0.color');

        $this->actingAs($user)->putJson(route('designs.update', $design), ['palette' => ['primary' => 'red;}']])
            ->assertUnprocessable()->assertJsonValidationErrors('palette.primary');

        $this->actingAs($user)->putJson(route('designs.update', $design), ['version' => 7, 'name' => 'Late'])->assertStatus(409);
        $this->assertSame('Test card', $design->fresh()->name);
    }

    public function test_render_endpoint_returns_svg_with_customer_data(): void
    {
        $user = $this->customerUser();
        $design = $this->designFor($user);

        $response = $this->actingAs($user)->postJson(route('designs.render', $design), [
            'side' => ['elements' => [$this->textElement()]],
            'fields' => 'customer',
            'guides' => true,
        ])->assertOk();

        $this->assertStringContainsString('<svg', $response->json('svg'));
        $this->assertStringContainsString('Hello Jordan Rivers', $response->json('svg'));
        $this->assertStringContainsString('data-guides', $response->json('svg'));

        $raw = $this->actingAs($user)->postJson(route('designs.render', $design), ['side' => ['elements' => [$this->textElement()]], 'fields' => 'raw']);
        $this->assertStringContainsString('Hello {{full_name}}', $raw->json('svg'));

        $this->actingAs($this->customerUser())->postJson(route('designs.render', $design), ['side' => []])->assertForbidden();
    }

    public function test_duplicate_and_delete(): void
    {
        $user = $this->customerUser();
        $design = $this->designFor($user);

        $this->actingAs($user)->post(route('designs.duplicate', $design))->assertRedirect();
        $copy = CardDesign::where('id', '!=', $design->id)->first();
        $this->assertSame('Test card (copy)', $copy->name);
        $this->assertEquals($design->front, $copy->front);
        $this->assertSame($design->elements()->count(), $copy->elements()->count());

        $this->actingAs($this->customerUser())->delete(route('designs.destroy', $design))->assertForbidden();
        $this->actingAs($user)->delete(route('designs.destroy', $design))->assertRedirect(route('designs.index'));
        $this->assertSoftDeleted($design);
    }

    public function test_designers_edit_only_assigned_designs_and_admins_edit_all(): void
    {
        $owner = $this->customerUser();
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $assigned = $this->designFor($owner, ['designer_id' => $designer->id]);
        $other = $this->designFor($owner, ['designer_id' => null]);

        $this->actingAs($designer)->get(route('designs.edit', $assigned))->assertOk();
        $this->actingAs($designer)->get(route('designs.edit', $other))->assertForbidden();
        $this->actingAs($designer)->get(route('designs.index'))->assertOk()->assertSee(route('designs.edit', $assigned))->assertDontSee(route('designs.edit', $other));

        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $this->actingAs($admin)->get(route('designs.edit', $other))->assertOk();
        $this->actingAs($admin)->putJson(route('designs.update', $other), ['status' => 'final'])->assertOk();
        $this->assertSame('final', $other->fresh()->status);
    }

    public function test_index_lists_only_visible_designs_and_create_page_renders(): void
    {
        $user = $this->customerUser();
        $mine = $this->designFor($user);
        $theirs = $this->designFor($this->customerUser(), ['name' => 'Someone else']);

        $this->actingAs($user)->get(route('designs.index'))->assertOk()->assertSee($mine->name)->assertDontSee('Someone else');
        $this->actingAs($user)->get(route('designs.create'))->assertOk()->assertSee('Blank card');
    }

    public function test_icon_search(): void
    {
        $this->actingAs($this->customerUser())->getJson(route('editor.icons', ['q' => 'rocket']))
            ->assertOk()->assertJsonFragment(['name' => 'rocket']);
    }
}
