<?php

namespace Tests\Feature\Brands;

use App\Models\BrandKit;
use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\User;
use App\Services\CardDesignService;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandKitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(ThemeSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Acme Corp', 'primary_color' => '#0a2540', 'secondary_color' => '#334155', 'accent_color' => '#f97316',
            'heading_font' => 'Playfair Display', 'body_font' => 'Lato',
            'background_type' => 'gradient', 'background_color' => '#ffffff', 'background_color2' => '#e0e7ff', 'background_angle' => 90,
            'company' => ['name' => 'Acme Corporation', 'tagline' => 'Built to last', 'website' => 'https://acme.test', 'phone' => '+1 555 0100', 'email' => 'hello@acme.test', 'address' => '1 Main St'],
            'social_links' => ['linkedin' => 'https://linkedin.com/company/acme', 'x' => ''],
        ], $overrides);
    }

    private function theme(): CardTheme
    {
        return CardTheme::where('slug', 'corporate-executive')->firstOrFail();
    }

    public function test_designer_creates_edits_and_deletes_a_brand_kit(): void
    {
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $this->actingAs($designer)->get(route('brand-kits.index'))->assertOk()->assertSee('No brand kits yet');
        $this->get(route('brand-kits.create'))->assertOk();

        $response = $this->post(route('brand-kits.store'), $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 400, 200)]));
        $kit = BrandKit::firstOrFail();
        $response->assertRedirect(route('brand-kits.show', $kit));

        $this->assertSame('#0A2540', $kit->primary_color);
        $this->assertSame(['heading' => 'Playfair Display', 'body' => 'Lato'], $kit->typography);
        $this->assertSame('gradient', $kit->background['type']);
        $this->assertSame(['linkedin' => 'https://linkedin.com/company/acme'], $kit->social_links);
        $this->assertSame('Acme Corporation', $kit->company_details['name']);
        Storage::disk('public')->assertExists($kit->logo_path);
        $this->assertStringNotContainsString((string) $kit->id.'/', route('brand-kits.show', $kit));

        $this->get(route('brand-kits.show', $kit))->assertOk()->assertSee('Acme Corporation')->assertSee('#F97316');
        $this->get(route('brand-kits.edit', $kit))->assertOk();

        // An image background needs an image; removing the logo deletes the file.
        $logo = $kit->logo_path;
        $this->put(route('brand-kits.update', $kit), $this->payload(['name' => 'Acme', 'background_type' => 'image', 'remove_logo' => 1]))->assertSessionHasNoErrors();
        $kit->refresh();
        $this->assertSame('Acme', $kit->name);
        $this->assertSame('solid', $kit->background['type']);
        $this->assertNull($kit->logo_path);
        Storage::disk('public')->assertMissing($logo);

        $this->put(route('brand-kits.update', $kit), $this->payload(['background_type' => 'image', 'background_image' => UploadedFile::fake()->image('bg.jpg', 1200, 800)]))->assertSessionHasNoErrors();
        $this->assertSame('image', $kit->fresh()->background['type']);

        $this->delete(route('brand-kits.destroy', $kit))->assertRedirect(route('brand-kits.index'));
        $this->assertSoftDeleted($kit);
    }

    public function test_brand_kit_input_is_validated(): void
    {
        $user = $this->userWithRole(User::ROLE_DESIGNER);
        $this->actingAs($user)->post(route('brand-kits.store'), $this->payload([
            'name' => '', 'primary_color' => 'red', 'heading_font' => 'Comic Sans MS', 'background_type' => 'video',
            'company' => ['website' => 'javascript:alert(1)', 'email' => 'nope'], 'social_links' => ['linkedin' => 'not a url'],
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors(['name', 'primary_color', 'heading_font', 'background_type', 'company.website', 'company.email', 'social_links.linkedin', 'logo']);
        $this->assertSame(0, BrandKit::count());
    }

    public function test_users_only_see_and_use_their_own_kits(): void
    {
        $owner = $this->userWithRole(User::ROLE_DESIGNER);
        $other = $this->userWithRole(User::ROLE_CUSTOMER);
        $this->actingAs($owner)->post(route('brand-kits.store'), $this->payload());
        $kit = BrandKit::firstOrFail();

        $this->actingAs($other)->get(route('brand-kits.index'))->assertOk()->assertDontSee(route('brand-kits.show', $kit));
        $this->get(route('brand-kits.show', $kit))->assertForbidden();
        $this->get(route('brand-kits.edit', $kit))->assertForbidden();
        $this->put(route('brand-kits.update', $kit), $this->payload(['name' => 'Hijacked']))->assertForbidden();
        $this->delete(route('brand-kits.destroy', $kit))->assertForbidden();
        $this->post(route('brand-kits.designs.store', $kit), ['theme' => $this->theme()->ulid])->assertForbidden();

        // The owner cannot apply their kit to someone else's design.
        $foreign = app(CardDesignService::class)->create($other, ['name' => 'Theirs', 'theme' => $this->theme()]);
        $this->actingAs($owner)->post(route('brand-kits.apply', $kit), ['design' => $foreign->ulid])->assertForbidden();
        $this->assertNull($foreign->fresh()->brand_kit_id);

        // Admins (designs.manage) see every kit.
        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))->get(route('brand-kits.index'))->assertSee('Acme Corp');
        $this->get(route('brand-kits.show', $kit))->assertOk();
    }

    public function test_applying_a_kit_and_creating_brand_templates(): void
    {
        $user = $this->userWithRole(User::ROLE_DESIGNER);
        $this->actingAs($user)->post(route('brand-kits.store'), $this->payload());
        $kit = BrandKit::firstOrFail();

        // New design (template) for the brand from a theme.
        $this->post(route('brand-kits.designs.store', $kit), ['theme' => $this->theme()->ulid, 'name' => 'Acme standard'])->assertRedirect();
        $template = CardDesign::where('name', 'Acme standard')->firstOrFail();
        $this->assertSame($kit->id, $template->brand_kit_id);
        $this->assertSame('#0A2540', $template->settings['palette']['primary']);
        $this->assertSame(['heading' => 'Playfair Display', 'body' => 'Lato'], $template->settings['fonts']);
        $this->assertSame('gradient', $template->back['background']['type']);

        // Apply to an existing design, with the background on the front.
        $design = app(CardDesignService::class)->create($user, ['name' => 'Old card', 'theme' => $this->theme()]);
        $version = $design->version;
        $this->post(route('brand-kits.apply', $kit), ['design' => $design->ulid, 'background' => ['front']])->assertSessionHasNoErrors();
        $design->refresh();
        $this->assertSame($kit->id, $design->brand_kit_id);
        $this->assertSame('#F97316', $design->settings['palette']['accent']);
        $this->assertSame('gradient', $design->front['background']['type']);
        $this->assertSame($version + 1, $design->version);
        $this->post(route('brand-kits.apply', $kit), ['design' => $design->ulid, 'background' => ['sideways']])->assertSessionHasErrors('background.0');

        $this->get(route('brand-kits.show', $kit))->assertOk()->assertSee('Acme standard')->assertSee('Old card');
        $this->get(route('brand-kits.index'))->assertOk()->assertSee('2 designs');

        // Deleting the kit keeps the designs.
        $this->delete(route('brand-kits.destroy', $kit));
        $this->assertNull($template->fresh()->brand_kit_id);
        $this->assertSame('#0A2540', $template->fresh()->settings['palette']['primary']);
    }

    public function test_kit_details_fill_merge_fields_the_customer_left_empty(): void
    {
        $user = $this->userWithRole(User::ROLE_ADMIN);
        $this->actingAs($user)->post(route('brand-kits.store'), $this->payload());
        $kit = BrandKit::firstOrFail();
        $service = app(CardDesignService::class);

        $customer = Customer::factory()->create(['full_name' => 'Sam Lee', 'website' => 'https://sam.test', 'office_phone' => null, 'office_address' => null, 'company_id' => null]);
        $design = $service->create($user, ['name' => 'Sam', 'theme' => $this->theme(), 'customer' => $customer]);
        $design->update(['brand_kit_id' => $kit->id]);

        $data = $service->mergeData($design->fresh());
        $this->assertSame('Acme Corporation', $data['company']);
        $this->assertSame('sam.test', $data['website']); // the customer's own value wins
        $this->assertSame('+1 555 0100', $data['office_phone']);
        $this->assertSame('1 Main St', $data['address']);

        // Fields the customer hid from cards stay hidden.
        $customer->update(['visibility' => ['card' => ['office_phone' => false]]]);
        $this->assertSame('', $service->mergeData($design->fresh())['office_phone']);

        // Templates without a customer show the brand's details instead of sample ones.
        $template = $service->create($user, ['name' => 'T', 'theme' => $this->theme()]);
        $template->update(['brand_kit_id' => $kit->id]);
        $this->assertSame('Built to last', $service->mergeData($template->fresh())['tagline']);
    }
}
