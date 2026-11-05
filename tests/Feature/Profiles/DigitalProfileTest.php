<?php

namespace Tests\Feature\Profiles;

use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\NfcCard;
use App\Models\User;
use App\Services\CardDesignService;
use App\Services\DigitalProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitalProfileTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function customerWithLogin(array $attributes = []): array
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $customer = Customer::factory()->create(['user_id' => $user->id] + $attributes);

        return [$user, $customer];
    }

    private function profileFor(Customer $customer, array $data = []): DigitalProfile
    {
        return app(DigitalProfileService::class)->create($customer, $data, $this->userWithRole(User::ROLE_ADMIN));
    }

    public function test_staff_create_a_profile_with_identifier_and_qr_code(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $customer = Customer::factory()->create(['full_name' => 'Ada Lovelace']);

        $this->actingAs($admin)->post(route('profiles.store'), ['customer' => $customer->ulid, 'profile_theme' => 'midnight'])
            ->assertRedirect();

        $profile = DigitalProfile::firstOrFail();
        $this->assertSame('ada-lovelace', $profile->slug);
        $this->assertMatchesRegularExpression('/^P-[2-9A-Z]{8}$/', $profile->card_identifier);
        $this->assertSame('midnight', $profile->profile_theme);
        $this->assertCount(1, $profile->qrCodes);

        // A second profile for the same name gets a different address.
        $this->assertSame('ada-lovelace-2', app(DigitalProfileService::class)->suggestSlug('Ada Lovelace'));
    }

    public function test_public_page_respects_profile_visibility_and_records_a_view(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Grace Hopper', 'mobile' => '+1 555 0100', 'email' => 'grace@example.com',
            'visibility' => ['profile' => ['mobile' => false]],
        ]);
        $profile = $this->profileFor($customer);

        $this->withHeader('User-Agent', self::PHONE_UA)->get('/card/'.$profile->slug)
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertSee('grace@example.com')
            ->assertDontSee('+1 555 0100')
            ->assertSee('noindex', false);

        $event = AnalyticsEvent::firstOrFail();
        $this->assertSame(AnalyticsEvent::PROFILE_VIEW, $event->event_type);
        $this->assertSame('mobile', $event->device_type);
        $this->assertSame(1, $profile->fresh()->views_count);

        // Reloading doesn't count again; bots are never counted.
        $this->withHeader('User-Agent', self::PHONE_UA)->get('/card/'.$profile->slug)->assertOk();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/card/'.$profile->slug)->assertOk();
        $this->assertSame(1, AnalyticsEvent::count());
    }

    public function test_inactive_profiles_and_customers_are_not_public(): void
    {
        $profile = $this->profileFor(Customer::factory()->create());
        $profile->update(['is_active' => false]);
        $this->get('/card/'.$profile->slug)->assertNotFound()->assertSee('This card isn');

        $profile->update(['is_active' => true]);
        $profile->customer->update(['status' => 'inactive']);
        $this->get('/card/'.$profile->slug)->assertNotFound();
        $this->get(route('qr.scan', $profile->qrCodes()->first()->code))->assertNotFound();
    }

    public function test_qr_link_redirects_and_counts_a_scan(): void
    {
        $profile = $this->profileFor(Customer::factory()->create());
        $qr = $profile->qrCodes()->first();

        $this->withHeader('User-Agent', self::PHONE_UA)->get('/q/'.$qr->code)
            ->assertRedirect(route('profiles.public', $profile->slug).'?via=qr');

        $this->assertSame(1, $qr->fresh()->scans_count);
        $this->assertDatabaseHas('analytics_events', ['event_type' => 'qr_scan', 'qr_code_id' => $qr->id, 'source' => 'qr']);
    }

    public function test_analytics_store_no_ip_address_and_respect_do_not_track(): void
    {
        $profile = $this->profileFor(Customer::factory()->create());

        $this->withHeaders(['User-Agent' => self::PHONE_UA, 'DNT' => '1'])->get('/card/'.$profile->slug);
        $event = AnalyticsEvent::firstOrFail();
        $this->assertNull($event->visitor_hash);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($event->toArray()));
    }

    public function test_vcard_contains_only_profile_visible_details(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Ada King Lovelace', 'email' => 'ada@example.com', 'mobile' => '+44 20 7946 0000',
            'professional_title' => 'Analyst; Writer', 'visibility' => ['profile' => ['email' => false]],
        ]);
        $profile = $this->profileFor($customer);

        $response = $this->withHeader('User-Agent', self::PHONE_UA)->get(route('profiles.vcard', $profile->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/vcard; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="ada-king-lovelace.vcf"');

        $vcf = $response->getContent();
        $this->assertStringContainsString("BEGIN:VCARD\r\nVERSION:3.0\r\n", $vcf);
        $this->assertStringContainsString('N:Lovelace;Ada King;;;', $vcf);
        $this->assertStringContainsString('TITLE:Analyst\; Writer', $vcf);
        $this->assertStringContainsString('TEL;TYPE=CELL,VOICE:+44 20 7946 0000', $vcf);
        $this->assertStringNotContainsString('ada@example.com', $vcf);
        $this->assertDatabaseHas('analytics_events', ['event_type' => 'vcard_download']);
    }

    public function test_click_beacons_are_validated_and_recorded_without_csrf(): void
    {
        $profile = $this->profileFor(Customer::factory()->create());

        $this->withHeader('User-Agent', self::PHONE_UA)->post(route('profiles.track', $profile->slug), ['type' => 'social', 'target' => 'linkedin'])->assertNoContent();
        $this->assertDatabaseHas('analytics_events', ['event_type' => 'click_social', 'target' => 'linkedin']);

        $this->post(route('profiles.track', $profile->slug), ['type' => 'evil'])->assertSessionHasErrors('type');
    }

    public function test_customer_edits_own_profile_but_not_someone_elses(): void
    {
        [$user, $customer] = $this->customerWithLogin();
        $mine = $this->profileFor($customer);
        $other = $this->profileFor(Customer::factory()->create());

        $this->actingAs($user)->put(route('profiles.update', $mine), [
            '_form' => 1,
            'profile_theme' => 'elegant',
            'accent' => '#0F766E',
            'headline' => 'Hello there',
            'layout' => [['key' => 'about', 'enabled' => 1], ['key' => 'map', 'enabled' => 0], ['key' => 'bogus', 'enabled' => 1]],
            'ctas' => [['label' => 'Book', 'url' => 'https://example.com/book', 'style' => 'outline'], ['label' => '', 'url' => '']],
            'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 600),
            'portfolio' => [UploadedFile::fake()->image('a.jpg', 800, 800)],
        ])->assertSessionHasErrors('layout.2.key');

        $this->actingAs($user)->put(route('profiles.update', $mine), [
            '_form' => 1,
            'profile_theme' => 'elegant',
            'accent' => '#0F766E',
            'headline' => 'Hello there',
            'layout' => [['key' => 'about', 'enabled' => 1], ['key' => 'map', 'enabled' => 0]],
            'ctas' => [['label' => 'Book', 'url' => 'https://example.com/book', 'style' => 'outline'], ['label' => '', 'url' => '']],
            'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 600),
            'portfolio' => [UploadedFile::fake()->image('a.jpg', 800, 800)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $mine->refresh();
        $this->assertSame('elegant', $mine->profile_theme);
        $this->assertSame('#0f766e', $mine->settings['accent']);
        $this->assertSame([['label' => 'Book', 'url' => 'https://example.com/book', 'style' => 'outline']], $mine->settings['ctas']);
        $this->assertSame(['about', 'map'], array_slice(array_column($mine->layout, 'key'), 0, 2));
        $this->assertFalse($mine->layout[1]['enabled']);
        Storage::disk('public')->assertExists($mine->settings['cover_path']);
        $this->assertCount(1, $mine->settings['portfolio']);

        $this->actingAs($user)->get(route('profiles.show', $other))->assertForbidden();
        $this->actingAs($user)->put(route('profiles.update', $other), ['headline' => 'x'])->assertForbidden();
        $this->actingAs($user)->delete(route('profiles.destroy', $other))->assertForbidden();
        $this->actingAs($user)->post(route('profiles.store'), ['customer' => $other->customer->ulid, 'profile_theme' => 'classic'])->assertForbidden();
    }

    public function test_slugs_must_be_unique_and_not_reserved(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $a = $this->profileFor(Customer::factory()->create(), ['slug' => 'taken-name']);
        $b = $this->profileFor(Customer::factory()->create());

        $this->actingAs($admin)->put(route('profiles.update', $b), ['slug' => 'taken-name'])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->put(route('profiles.update', $b), ['slug' => 'admin'])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->put(route('profiles.update', $b), ['slug' => 'Bad Slug!'])->assertSessionHasErrors('slug');

        // Changing the slug keeps the QR link working.
        $this->actingAs($admin)->put(route('profiles.update', $b), ['slug' => 'new-name'])->assertSessionHasNoErrors();
        $this->get('/q/'.$b->qrCodes()->first()->code)->assertRedirect(route('profiles.public', 'new-name').'?via=qr');
        $this->assertNotNull($a);
    }

    public function test_qr_downloads_in_png_svg_and_pdf(): void
    {
        [$user, $customer] = $this->customerWithLogin();
        $profile = $this->profileFor($customer);

        $png = $this->actingAs($user)->get(route('profiles.qr.download', [$profile, 'png']).'?size=512')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame("\x89PNG", substr($png->getContent(), 0, 4));
        $this->actingAs($user)->get(route('profiles.qr.download', [$profile, 'svg']))->assertOk()->assertSee('<svg', false);
        $pdf = $this->actingAs($user)->get(route('profiles.qr.download', [$profile, 'pdf']).'?size=50')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString('/MediaBox [0 0 141.732 141.732]', $pdf->getContent());
        $this->actingAs($user)->get(route('profiles.qr.download', [$profile, 'png']).'?size=7')->assertStatus(422);

        // Low-contrast styles are refused.
        $this->actingAs($user)->put(route('profiles.qr.update', $profile), ['color' => '#eeeeee', 'background' => '#ffffff', 'margin' => 2, 'level' => 'M'])
            ->assertSessionHasErrors('color');
        $this->actingAs($user)->put(route('profiles.qr.update', $profile), ['color' => '#1e3a8a', 'transparent' => 1, 'margin' => 4, 'level' => 'H'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['color' => '#1e3a8a', 'background' => 'transparent', 'margin' => 4, 'level' => 'H'], $profile->qrCodes()->first()->style);
    }

    public function test_nfc_assign_tap_activates_reassign_and_replace(): void
    {
        $sales = $this->userWithRole(User::ROLE_SALES_MANAGER);
        $first = $this->profileFor(Customer::factory()->create());
        $second = $this->profileFor(Customer::factory()->create());

        $this->actingAs($sales)->post(route('profiles.nfc.store', $first), ['identifier' => 'sc-abc123', 'uid' => '04a23b1c5d8000', 'tag_type' => 'NTAG216'])
            ->assertSessionHasNoErrors();
        $card = NfcCard::where('card_identifier', 'SC-ABC123')->firstOrFail();
        $this->assertSame('04:A2:3B:1C:5D:80:00', $card->uid);
        $this->assertSame('assigned', $card->status);

        // First tap activates the card and opens the profile.
        $this->withHeader('User-Agent', self::PHONE_UA)->get('/n/SC-ABC123')->assertRedirect(route('profiles.public', $first->slug).'?via=nfc');
        $this->assertSame('active', $card->fresh()->status);
        $this->assertDatabaseHas('nfc_assignments', ['nfc_card_id' => $card->id, 'status' => 'active']);
        $this->assertDatabaseHas('analytics_events', ['event_type' => 'nfc_tap', 'nfc_card_id' => $card->id]);

        // Moving needs explicit confirmation; then the same tag opens the new profile, no rewrite needed.
        $this->actingAs($sales)->post(route('profiles.nfc.store', $second), ['identifier' => 'SC-ABC123'])->assertSessionHasErrors('identifier', null, 'nfc');
        $this->actingAs($sales)->post(route('profiles.nfc.store', $second), ['identifier' => 'SC-ABC123', 'move' => 1])->assertSessionHasNoErrors();
        $this->get('/n/SC-ABC123')->assertRedirect(route('profiles.public', $second->slug).'?via=nfc');

        // Lost card: replaced, and the old one stops working.
        $this->actingAs($sales)->post(route('profiles.nfc.replace', [$second, $card]), ['identifier' => 'SC-NEW999', 'reason' => 'lost'])->assertSessionHasNoErrors();
        $this->assertSame('lost', $card->fresh()->status);
        $this->get('/n/SC-ABC123')->assertNotFound();
        $new = NfcCard::where('card_identifier', 'SC-NEW999')->firstOrFail();
        $this->assertDatabaseHas('nfc_assignments', ['nfc_card_id' => $new->id, 'digital_profile_id' => $second->id, 'status' => 'assigned']);
        $this->assertNotNull($new->assignments()->first()->replaces_assignment_id);

        // Mark encoded, then unassign.
        $this->actingAs($sales)->post(route('profiles.nfc.encoded', [$second, $new]), ['uid' => '04:11:22:33:44:55:66'])->assertSessionHasNoErrors();
        $this->assertSame('active', $new->fresh()->status);
        $this->actingAs($sales)->delete(route('profiles.nfc.destroy', [$second, $new]))->assertSessionHasNoErrors();
        $this->assertSame('in_stock', $new->fresh()->status);
        $this->get('/n/SC-NEW999')->assertNotFound();
    }

    public function test_customers_cannot_manage_nfc_cards(): void
    {
        [$user, $customer] = $this->customerWithLogin();
        $profile = $this->profileFor($customer);

        $this->actingAs($user)->post(route('profiles.nfc.store', $profile), ['identifier' => 'SC-HACK01'])->assertForbidden();
        $this->assertDatabaseMissing('nfc_cards', ['card_identifier' => 'SC-HACK01']);
    }

    public function test_profile_nfc_link_and_designs_use_the_tracked_qr_link(): void
    {
        $customer = Customer::factory()->create();
        $profile = $this->profileFor($customer);

        $this->get('/c/'.strtolower($profile->card_identifier))->assertRedirect(route('profiles.public', $profile->slug).'?via=nfc');

        $data = app(CardDesignService::class)->customerData($customer);
        $this->assertSame(route('profiles.public', $profile->slug), $data['profile_url']);
        $this->assertSame(route('qr.scan', $profile->qrCodes()->first()->code), $data['qr_url']);
    }

    public function test_deleting_a_profile_releases_its_nfc_cards(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $profile = $this->profileFor(Customer::factory()->create());
        $this->actingAs($admin)->post(route('profiles.nfc.store', $profile), ['identifier' => 'SC-REL001']);

        $this->actingAs($admin)->delete(route('profiles.destroy', $profile))->assertRedirect(route('profiles.index'));

        $this->assertSoftDeleted($profile);
        $this->assertSame('in_stock', NfcCard::where('card_identifier', 'SC-REL001')->value('status'));
        $this->get('/card/'.$profile->slug)->assertNotFound();
    }

    public function test_api_lists_creates_and_updates_profiles(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $customer = Customer::factory()->create(['full_name' => 'Api Person']);
        $token = $admin->createToken('t', ['read', 'write'])->plainTextToken;

        $id = $this->withToken($token)->postJson('/api/v1/profiles', ['customer' => $customer->ulid, 'profile_theme' => 'bold'])
            ->assertCreated()->assertJsonPath('data.slug', 'api-person')->json('data.id');

        $this->withToken($token)->patchJson('/api/v1/profiles/'.$id, ['headline' => 'From the API', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.headline', 'From the API')->assertJsonPath('data.is_active', false);

        $this->withToken($token)->getJson('/api/v1/profiles')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.customer_id');
        $this->withToken($token)->get('/api/v1/profiles/'.$id.'/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }
}
