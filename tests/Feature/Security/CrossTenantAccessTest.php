<?php

namespace Tests\Feature\Security;

use App\Models\BrandKit;
use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\NfcAssignment;
use App\Models\NfcCard;
use App\Models\User;
use App\Services\CardDesignService;
use App\Services\DigitalProfileService;
use App\Services\ThemeManagementService;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One customer's designs, profiles, NFC cards, brand kits, themes and
 * customer record must be out of reach for every other customer, and for
 * staff whose role does not cover them. Each route is tried directly with
 * the other customer's public ids, and nothing may change.
 */
class CrossTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $victim;

    private Customer $customer;

    private CardDesign $design;

    private DigitalProfile $profile;

    private NfcCard $card;

    private BrandKit $kit;

    private CardTheme $privateTheme;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(ThemeSeeder::class);

        $this->victim = $this->userWithRole(User::ROLE_CUSTOMER);
        $this->customer = Customer::factory()->create(['user_id' => $this->victim->id, 'full_name' => 'Victoria Victim']);
        $this->design = app(CardDesignService::class)->create($this->victim, ['name' => 'Private card', 'theme' => CardTheme::where('status', 'published')->first(), 'customer' => $this->customer]);
        $this->profile = app(DigitalProfileService::class)->create($this->customer, [], $this->victim);
        $this->card = NfcCard::factory()->create(['status' => 'active']);
        NfcAssignment::create(['nfc_card_id' => $this->card->id, 'customer_id' => $this->customer->id, 'digital_profile_id' => $this->profile->id, 'status' => 'active', 'assigned_at' => now(), 'profile_url' => route('nfc.tap', $this->card->card_identifier)]);
        $this->kit = BrandKit::create(['user_id' => $this->victim->id, 'name' => 'Victim brand', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'accent_color' => '#333333']);
        $this->privateTheme = app(ThemeManagementService::class)->duplicate(CardTheme::where('status', 'published')->first(), $this->victim, 'Victim private theme');
    }

    /** Every route that takes one of the victim's resources, with any extra input it needs. */
    private function attempts(): array
    {
        $d = $this->design;
        $p = $this->profile;
        $c = $this->card;
        $k = $this->kit;
        $t = $this->privateTheme;
        $cu = $this->customer;

        return [
            ['GET', route('designs.edit', $d)], ['PUT', route('designs.update', $d), ['name' => 'x']], ['DELETE', route('designs.destroy', $d)],
            ['POST', route('designs.duplicate', $d)], ['POST', route('designs.render', $d), ['side' => 'front']],
            ['GET', route('designs.export', $d)], ['POST', route('designs.export.store', $d), ['format' => 'png']],
            ['GET', route('profiles.show', $p)], ['GET', route('profiles.edit', $p)], ['PUT', route('profiles.update', $p), ['slug' => 'stolen']],
            ['DELETE', route('profiles.destroy', $p)], ['GET', route('profiles.preview', $p)], ['PUT', route('profiles.qr.update', $p), []],
            ['GET', route('profiles.qr.download', [$p, 'svg'])], ['POST', route('profiles.nfc.store', $p), ['identifier' => $c->card_identifier]],
            ['DELETE', route('profiles.nfc.destroy', [$p, $c])], ['POST', route('profiles.nfc.replace', [$p, $c])],
            ['GET', route('customers.show', $cu)], ['GET', route('customers.edit', $cu)], ['PUT', route('customers.update', $cu), ['full_name' => 'Hacked']],
            ['DELETE', route('customers.destroy', $cu)],
            ['GET', route('brand-kits.show', $k)], ['GET', route('brand-kits.edit', $k)], ['DELETE', route('brand-kits.destroy', $k)],
            ['POST', route('brand-kits.apply', $k), ['design' => $d->ulid]],
            ['GET', route('themes.show', $t)], ['GET', route('themes.edit', $t)], ['GET', route('themes.customize', $t)], ['DELETE', route('themes.destroy', $t)],
            ['GET', route('nfc.show', $c)], ['POST', route('nfc.assign', $c), ['profile' => $p->ulid]], ['DELETE', route('nfc.unassign', $c)],
            ['GET', route('analytics.index', ['profile' => $p->ulid])],
        ];
    }

    public static function intruders(): array
    {
        return [
            'another customer' => [User::ROLE_CUSTOMER],
            'unassigned designer' => [User::ROLE_DESIGNER],
        ];
    }

    #[DataProvider('intruders')]
    public function test_other_users_cannot_read_or_change_a_customers_resources(string $role): void
    {
        $intruder = $this->userWithRole($role);
        Customer::factory()->create(['user_id' => $role === User::ROLE_CUSTOMER ? $intruder->id : null]);
        $before = $this->snapshot();

        foreach ($this->attempts() as $attempt) {
            [$method, $url] = $attempt;
            $status = $this->actingAs($intruder)->json($method, $url, $attempt[2] ?? [])->getStatusCode();
            $this->assertContains($status, [403, 404], "$role got $status for $method $url");
        }

        $this->assertSame($before, $this->snapshot(), 'Something changed');
    }

    public function test_guests_are_sent_to_login_from_every_app_page(): void
    {
        foreach ($this->attempts() as [$method, $url]) {
            $response = $this->call($method, $url);
            $this->assertTrue($response->isRedirect(route('login')) || $response->getStatusCode() === 419, "Guest reached $method $url ({$response->getStatusCode()})");
        }
        foreach (['dashboard', 'designs.index', 'customers.index', 'profiles.index', 'brand-kits.index', 'nfc.index', 'orders.index', 'analytics.index', 'imports.index', 'admin.users.index'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_no_route_binds_a_numeric_id(): void
    {
        $numeric = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->parameterNames() as $param) {
                if (in_array($param, ['tokenId'], true)) {
                    continue; // Sanctum token ids belong to the signed-in user only.
                }
                if ($field = $route->bindingFieldFor($param)) {
                    $this->assertNotSame('id', $field);
                }
            }
            if (preg_match('/\{(id|\w+_id)\}/', $route->uri())) {
                $numeric[] = $route->uri();
            }
        }
        $this->assertSame([], $numeric);

        foreach ([CardDesign::class, DigitalProfile::class, Customer::class, BrandKit::class, CardTheme::class, NfcCard::class, User::class] as $model) {
            $this->assertNotSame('id', (new $model)->getRouteKeyName(), $model);
        }
    }

    private function snapshot(): array
    {
        return [
            $this->design->fresh()?->only(['name', 'version', 'brand_kit_id', 'deleted_at']),
            CardDesign::count(),
            $this->profile->fresh()?->only(['slug', 'is_active', 'deleted_at']),
            $this->customer->fresh()?->only(['full_name', 'deleted_at']),
            $this->kit->fresh()?->only(['name', 'deleted_at']),
            CardTheme::whereKey($this->privateTheme->id)->exists(),
            $this->card->fresh()->status,
            NfcAssignment::where('status', 'active')->count(),
        ];
    }
}
