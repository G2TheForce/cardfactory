<?php

namespace Tests\Feature\Customers;

use App\Models\CardDesign;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\CardDesignService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'full_name' => 'Ada Lovelace',
            'professional_title' => 'Analyst',
            'mobile' => '+44 20 7946 0000',
            'email' => 'ada@example.com',
            'website' => 'https://ada.example.com',
            'company_name' => 'Analytical Engines Ltd',
            'social_links' => ['linkedin' => 'https://linkedin.com/in/ada', 'x' => ''],
            'business' => ['description' => 'Computing', 'services' => "Programs\nNotes\n\nNotes", 'hours' => ['monday' => '9-5', 'funday' => 'x']],
            'custom_fields' => [['label' => 'Languages', 'value' => 'English, French'], ['label' => '', 'value' => '']],
            'custom_links' => [['label' => '', 'url' => 'https://example.org/notes']],
            'visibility' => ['card' => ['mobile' => '0', 'email' => '1'], 'profile' => ['mobile' => '1']],
        ], $overrides);
    }

    public function test_staff_create_a_customer_with_all_groups(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->post(route('customers.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
            'company_logo' => UploadedFile::fake()->image('logo.png', 300, 100),
        ]));

        $customer = Customer::firstOrFail();
        $response->assertRedirect(route('customers.show', $customer));
        $this->assertSame('Analytical Engines Ltd', $customer->company->name);
        Storage::disk('public')->assertExists($customer->profile_photo_path);
        Storage::disk('public')->assertExists($customer->company->logo_path);
        $this->assertSame(['linkedin' => 'https://linkedin.com/in/ada'], $customer->social_links);
        $this->assertSame(['Programs', 'Notes'], $customer->business['services']);
        $this->assertSame(['monday' => '9-5'], $customer->business['hours']);
        $this->assertCount(1, $customer->custom_fields);
        $this->assertSame('example.org', $customer->custom_links[0]['label']);
        $this->assertFalse($customer->visibility['card']['mobile']);
        $this->assertTrue($customer->visibility['card']['website']);
        $this->assertSame($admin->id, $customer->created_by);

        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()->assertSee('Ada Lovelace')->assertSee('Analytical Engines Ltd');
        $this->actingAs($admin)->get(route('customers.index', ['search' => 'Engines']))->assertOk()->assertSee('Ada Lovelace');
    }

    public function test_validation_errors(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('customers.store'), [
            'full_name' => '',
            'email' => 'not-an-email',
            'mobile' => 'call me <maybe>',
            'website' => 'javascript:alert(1)',
            'social_links' => ['linkedin' => 'ftp://x'],
            'latitude' => '95',
            'custom_links' => [['label' => 'x', 'url' => 'nope']],
            'photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['full_name', 'email', 'mobile', 'website', 'social_links.linkedin', 'latitude', 'longitude', 'custom_links.0.url', 'photo']);

        $this->assertSame(0, Customer::count());
    }

    public function test_customer_edits_only_their_own_record_and_cannot_change_staff_fields(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $mine = Customer::factory()->create(['user_id' => $user->id]);
        $other = Customer::factory()->create();

        $this->actingAs($user)->get(route('my-details'))->assertRedirect(route('customers.edit', $mine));
        $this->actingAs($user)->get(route('customers.edit', $mine))->assertOk()->assertSee('My card details');
        $this->actingAs($user)->put(route('customers.update', $mine), $this->payload(['full_name' => 'Me Myself']))->assertRedirect();
        $this->assertSame('Me Myself', $mine->fresh()->full_name);

        $this->actingAs($user)->put(route('customers.update', $mine), $this->payload(['status' => 'archived']))->assertSessionHasErrors('status');
        $this->actingAs($user)->get(route('customers.edit', $other))->assertForbidden();
        $this->actingAs($user)->put(route('customers.update', $other), $this->payload())->assertForbidden();
        $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($user)->delete(route('customers.destroy', $mine))->assertForbidden();
        $this->actingAs($user)->get(route('customers.create'))->assertForbidden();
    }

    public function test_customers_cannot_hijack_a_shared_company(): void
    {
        $shared = Company::factory()->create(['name' => 'Acme', 'logo_path' => 'companies/acme.png']);
        $colleague = Customer::factory()->create(['company_id' => $shared->id]);
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $mine = Customer::factory()->create(['user_id' => $user->id, 'company_id' => $shared->id]);

        $this->actingAs($user)->put(route('customers.update', $mine), $this->payload([
            'company_name' => 'Acme', 'company_logo' => UploadedFile::fake()->image('evil.png', 50, 50),
        ]))->assertRedirect();

        $this->assertSame('companies/acme.png', $shared->fresh()->logo_path);
        $this->assertNotSame($shared->id, $mine->fresh()->company_id);
        $this->assertSame($shared->id, $colleague->fresh()->company_id);
        $this->assertSame($user->id, $mine->fresh()->company->owner_id);
    }

    public function test_designers_see_only_assigned_customers(): void
    {
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $assigned = Customer::factory()->create(['assigned_designer_id' => $designer->id, 'full_name' => 'Assigned Person']);
        $other = Customer::factory()->create(['full_name' => 'Hidden Person']);

        $this->actingAs($designer)->get(route('customers.index'))->assertOk()->assertSee('Assigned Person')->assertDontSee('Hidden Person');
        $this->actingAs($designer)->get(route('customers.show', $assigned))->assertOk();
        $this->actingAs($designer)->get(route('customers.show', $other))->assertForbidden();
        $this->actingAs($designer)->get(route('customers.edit', $assigned))->assertForbidden();
    }

    public function test_staff_assign_designers_and_create_logins(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $sales = $this->userWithRole(User::ROLE_SALES_MANAGER);

        $this->actingAs($admin)->post(route('customers.store'), $this->payload(['assigned_designer' => $sales->ulid]))->assertSessionHasErrors('assigned_designer');

        $this->actingAs($admin)->post(route('customers.store'), $this->payload(['assigned_designer' => $designer->ulid, 'create_login' => '1']))->assertRedirect();
        $customer = Customer::firstOrFail();
        $this->assertSame($designer->id, $customer->assigned_designer_id);
        $this->assertNotNull($customer->user_id);
        $this->assertTrue($customer->user->hasRole(User::ROLE_CUSTOMER));
        Notification::assertSentTo($customer->user, ResetPassword::class);

        $duplicate = Customer::factory()->create(['email' => 'ada@example.com', 'user_id' => null]);
        $this->actingAs($admin)->post(route('customers.login', $duplicate))->assertSessionHasErrors('create_login');
    }

    public function test_delete_and_restore(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted($customer);
        $this->actingAs($admin)->get(route('customers.index', ['status' => 'deleted']))->assertSee($customer->full_name);

        $this->actingAs($admin)->post(route('customers.restore', $customer->ulid))->assertRedirect(route('customers.show', $customer));
        $this->assertNotSoftDeleted($customer);
    }

    public function test_hidden_fields_are_blank_on_cards(): void
    {
        $customer = Customer::factory()->create(['mobile' => '+1 555 0100', 'visibility' => ['card' => ['mobile' => false]]]);
        $data = app(CardDesignService::class)->customerData($customer);

        $this->assertSame('', $data['mobile']);
        $this->assertSame($customer->email, $data['email']);
    }

    public function test_api_crud_respects_abilities_and_policies(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $write = $admin->createToken('w', ['read', 'write'])->plainTextToken;
        $read = $admin->createToken('r', ['read'])->plainTextToken;

        $created = $this->withToken($write)->postJson('/api/v1/customers', $this->payload())->assertCreated()
            ->assertJsonPath('data.full_name', 'Ada Lovelace')->assertJsonPath('data.company.name', 'Analytical Engines Ltd');
        $id = $created->json('data.id');
        $this->assertDoesNotMatchRegularExpression('/^\d+$/', $id);

        $this->app['auth']->forgetGuards(); // each request authenticates its own token
        $this->withToken($read)->getJson('/api/v1/customers')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->withToken($read)->patchJson("/api/v1/customers/$id", ['professional_title' => 'X'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($write)->patchJson("/api/v1/customers/$id", ['professional_title' => 'Countess'])->assertOk()
            ->assertJsonPath('data.professional_title', 'Countess')->assertJsonPath('data.full_name', 'Ada Lovelace')
            ->assertJsonPath('data.custom_fields.0.label', 'Languages');
        $this->withToken($write)->deleteJson("/api/v1/customers/$id")->assertNoContent();

        $customerUser = $this->userWithRole(User::ROLE_CUSTOMER);
        $token = $customerUser->createToken('c', ['read', 'write'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/customers')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/customers', $this->payload())->assertForbidden();
    }

    public function test_designs_page_offers_customer_shortcut(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $customer = Customer::factory()->create();
        CardDesign::factory()->create(['user_id' => $admin->id, 'customer_id' => $customer->id, 'name' => 'Their card']);

        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()
            ->assertSee('Their card')->assertSee(route('designs.create', ['customer' => $customer->ulid]), false);
    }
}
