<?php

namespace Tests\Feature\LandingPages;

use App\Mail\LandingPageLeadReceived;
use App\Models\BrandKit;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LandingPage;
use App\Models\User;
use App\Services\LandingPageService;
use App\Services\LandingPageSiteExporter;
use App\Support\LandingPages\Sections;
use App\Support\LandingPages\Templates;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class LandingPageDesignerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
    }

    private function customer(array $attributes = []): Customer
    {
        $company = Company::factory()->create(['name' => 'Himalaya Coffee House']);

        return Customer::factory()->create($attributes + [
            'company_id' => $company->id,
            'full_name' => 'Sita Gurung',
            'email' => 'sita@example.com',
            'mobile' => '+977 9800000000',
            'whatsapp' => '+977 9800000000',
            'office_address' => 'Lakeside Road',
            'city' => 'Pokhara',
            'country' => 'Nepal',
            'status' => 'active',
        ]);
    }

    private function page(string $template = 'cafe', ?Customer $customer = null): LandingPage
    {
        return app(LandingPageService::class)->create($customer ?? $this->customer(), ['template' => $template], $this->userWithRole(User::ROLE_ADMIN));
    }

    public function test_every_template_builds_clean_sections_filled_with_customer_details(): void
    {
        $customer = $this->customer();
        foreach (array_keys(Templates::TEMPLATES) as $key) {
            $page = app(LandingPageService::class)->create($customer, ['template' => $key], $this->userWithRole(User::ROLE_ADMIN));

            $this->assertFalse($page->is_published, $key);
            $this->assertNotEmpty($page->sections, $key);
            $this->assertSame($page->sections, Sections::sanitize($page->sections, 'landing-pages/'.$page->ulid.'/'), "$key sections are already clean");
            $this->assertDoesNotMatchRegularExpression('/\{(company|name|email|phone|whatsapp|address)\}/', json_encode($page->sections), "$key has unfilled tokens");

            $contact = collect($page->sections)->firstWhere('type', 'contact');
            $this->assertSame('sita@example.com', $contact['data']['email'], $key);
        }

        $page = LandingPage::where('template', 'cafe')->first();
        $this->assertSame('himalaya-coffee-house', $page->slug);
        $this->assertStringContainsString('Himalaya Coffee House', $page->sections[0]['data']['heading']);
        $this->assertSame('Lakeside Road, Pokhara, Nepal', collect($page->sections)->firstWhere('type', 'contact')['data']['address']);
    }

    public function test_admin_creates_a_page_from_the_designer_screens(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $customer = $this->customer();
        $labels = collect(Navigation::for($admin))->pluck('items')->flatten(1)->pluck('label');
        $this->assertContains('Landing pages', $labels);

        $this->actingAs($admin)->get(route('landing-pages.index'))->assertOk()->assertSee('No landing pages yet');
        $this->get(route('landing-pages.create'))->assertOk()->assertSee('Café & restaurant')->assertSee('Product launch');

        $response = $this->post(route('landing-pages.store'), ['customer' => $customer->ulid, 'template' => 'real-estate', 'slug' => 'himalaya-homes']);
        $page = LandingPage::firstOrFail();
        $response->assertRedirect(route('landing-pages.edit', $page));

        $this->assertSame('himalaya-homes', $page->slug);
        $this->get(route('landing-pages.edit', $page))->assertOk()->assertSee('Live preview')->assertSee('Add section');
        $this->get(route('landing-pages.show', $page))->assertOk()->assertSee('Draft')->assertSee('/p/himalaya-homes');
        $this->get(route('landing-pages.preview', $page))->assertOk()->assertSee('Find the place you will call home')->assertSee('noindex', false);
        $this->get(route('landing-pages.index'))->assertSee('Real estate');
    }

    public function test_saving_sanitises_sections_and_style(): void
    {
        $page = $this->page();
        $other = 'landing-pages/01OTHERPAGE000000000000000/x.jpg';
        $sections = [
            ['id' => 'hero0001', 'type' => 'hero', 'data' => ['heading' => '  New headline  ', 'button_url' => 'javascript:alert(1)', 'variant' => 'bogus', 'image' => $other]],
            ['id' => 'nope0001', 'type' => 'script', 'data' => ['x' => 1]],
            ['id' => 'svc00001', 'type' => 'services', 'hidden' => true, 'data' => ['items' => [['title' => 'Espresso', 'icon' => 'not-an-icon', 'extra' => 'dropped']]]],
            ['id' => 'cta00001', 'type' => 'cta', 'data' => ['button_url' => 'https://example.com/book', 'heading' => str_repeat('a', 500)]],
        ];

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))->put(route('landing-pages.update', $page), [
            'sections_json' => json_encode($sections),
            'style' => ['palette' => 'ocean', 'primary' => '#0C4A6E', 'accent' => '#0ea5e9', 'mode' => 'dark', 'font' => 'editorial', 'buttons' => 'pill', 'animations' => '0'],
            'settings' => ['seo_title' => 'Best coffee in Pokhara', 'notify_email' => 'owner@example.com', 'show_nav' => '1', 'allow_indexing' => '0'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame(['hero', 'services', 'cta'], array_column($page->sections, 'type'));
        $hero = $page->sections[0]['data'];
        $this->assertSame('New headline', $hero['heading']);
        $this->assertSame('', $hero['button_url']);
        $this->assertSame('centered', $hero['variant']);
        $this->assertSame('', $hero['image'], 'images from another page are refused');
        $this->assertTrue($page->sections[1]['hidden']);
        $this->assertSame(['icon' => 'sparkles', 'title' => 'Espresso', 'text' => '', 'price' => ''], $page->sections[1]['data']['items'][0]);
        $this->assertSame('https://example.com/book', $page->sections[2]['data']['button_url']);
        $this->assertSame(120, mb_strlen($page->sections[2]['data']['heading']));
        $this->assertSame(['palette' => 'ocean', 'primary' => '#0c4a6e', 'accent' => '#0ea5e9', 'mode' => 'dark', 'font' => 'editorial', 'buttons' => 'pill', 'animations' => false], $page->style);
        $this->assertSame('Best coffee in Pokhara', $page->settings['seo_title']);
        $this->assertFalse($page->settings['allow_indexing']);

        $this->put(route('landing-pages.update', $page), ['style' => ['primary' => 'red']])->assertSessionHasErrors('style.primary');
        $this->put(route('landing-pages.update', $page), ['sections_json' => 'not json'])->assertSessionHasErrors('sections');
    }

    public function test_publishing_and_the_public_page(): void
    {
        $page = $this->page();
        $this->get('/p/'.$page->slug)->assertNotFound()->assertSee('This page isn', false);

        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $this->actingAs($admin)->post(route('landing-pages.publish', $page), ['published' => 1])->assertRedirect();
        $this->assertTrue($page->refresh()->is_published);
        $this->assertNotNull($page->published_at);

        // Staff views aren't counted.
        $this->get('/p/'.$page->slug)->assertOk();
        $this->assertSame(0, $page->refresh()->views_count);

        auth()->logout();
        $html = $this->get('/p/'.$page->slug)->assertOk()
            ->assertSee('Good food, slow mornings at Himalaya Coffee House')
            ->assertSee('Menu highlights')
            ->assertSee('https://wa.me/9779800000000', false)
            ->assertSee('maps.google.com/maps?q=', false)
            ->assertSee(route('landing-pages.contact', $page->slug), false)
            ->assertDontSee('noindex', false)
            ->getContent();
        $this->assertSame(1, $page->refresh()->views_count);
        // The empty gallery is left out until it has photos.
        $this->assertStringNotContainsString('A look around', $html);

        $page->customer->update(['status' => 'inactive']);
        $this->get('/p/'.$page->slug)->assertNotFound();
    }

    public function test_draft_preview_renders_unsaved_changes_without_saving(): void
    {
        $page = $this->page();
        $sections = $page->sections;
        $sections[0]['data']['heading'] = 'Unsaved headline';

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))
            ->put(route('landing-pages.draft-preview', $page), ['sections_json' => json_encode($sections), 'style' => ['palette' => 'gold', 'mode' => 'dark']])
            ->assertOk()->assertSee('Unsaved headline')->assertSee('Preview');

        $page->refresh();
        $this->assertNotSame('Unsaved headline', $page->sections[0]['data']['heading']);
        $this->assertSame('terracotta', $page->style['palette']);
    }

    public function test_visitors_send_enquiries_that_are_saved_and_emailed(): void
    {
        $page = $this->page();
        $page->update(['is_published' => true]);

        $this->post(route('landing-pages.contact', $page->slug), ['name' => 'Ram', 'message' => 'Hi'])
            ->assertSessionHasErrorsIn('lead', ['email', 'phone']);
        $this->post(route('landing-pages.contact', $page->slug), ['name' => 'Bot', 'email' => 'bot@example.com', 'company_website' => 'spam'])
            ->assertSessionHasErrorsIn('lead', ['company_website']);

        $this->post(route('landing-pages.contact', $page->slug), ['name' => 'Ram Thapa', 'email' => 'ram@example.com', 'message' => 'Table for 8 on Saturday?'])
            ->assertRedirect(route('landing-pages.public', $page->slug).'#contact')
            ->assertSessionHas('lead_sent');

        $this->assertSame(1, $page->leads()->count());
        Mail::assertQueued(LandingPageLeadReceived::class, fn ($mail) => $mail->hasTo('sita@example.com') && $mail->lead->name === 'Ram Thapa');

        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $this->actingAs($admin)->get(route('landing-pages.show', $page))->assertOk()->assertSee('Table for 8 on Saturday?')->assertSee('Mark all read');
        $this->post(route('landing-pages.leads.read', $page))->assertRedirect();
        $this->assertSame(0, $page->leads()->whereNull('read_at')->count());

        $lead = $page->leads()->first();
        $this->delete(route('landing-pages.leads.destroy', [$page, $lead]))->assertRedirect();
        $this->assertSame(0, $page->leads()->count());

        // Drafts don't take enquiries.
        $page->update(['is_published' => false]);
        $this->post(route('landing-pages.contact', $page->slug), ['name' => 'Ram', 'email' => 'ram@example.com'])->assertNotFound();
    }

    public function test_customers_only_reach_their_own_pages(): void
    {
        $owner = $this->userWithRole(User::ROLE_CUSTOMER);
        $page = $this->page('consultant', $this->customer(['user_id' => $owner->id]));
        $stranger = $this->userWithRole(User::ROLE_CUSTOMER);
        Customer::factory()->create(['user_id' => $stranger->id]);

        $this->actingAs($owner)->get(route('landing-pages.index'))->assertOk()->assertSee($page->name);
        $this->get(route('landing-pages.edit', $page))->assertOk();

        $this->actingAs($stranger)->get(route('landing-pages.index'))->assertOk()->assertDontSee($page->name);
        $this->get(route('landing-pages.show', $page))->assertForbidden();
        $this->get(route('landing-pages.edit', $page))->assertForbidden();
        $this->put(route('landing-pages.update', $page), ['name' => 'Hacked'])->assertForbidden();
        $this->post(route('landing-pages.store'), ['customer' => $page->customer->ulid, 'template' => 'blank'])->assertForbidden();
        $this->post(route('landing-pages.images.store', $page), ['image' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();

        // Designers see pages of customers assigned to them, but can't edit.
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $page->customer->update(['assigned_designer_id' => $designer->id]);
        $this->actingAs($designer)->get(route('landing-pages.show', $page))->assertOk()->assertDontSee('Open designer');
        $this->get(route('landing-pages.edit', $page))->assertForbidden();
    }

    public function test_images_upload_into_the_pages_folder_and_render(): void
    {
        $page = $this->page('salon');
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $json = $this->actingAs($admin)->postJson(route('landing-pages.images.store', $page), ['image' => UploadedFile::fake()->image('chair.jpg', 1200, 800)])
            ->assertOk()->json();
        $this->assertStringStartsWith('landing-pages/'.$page->ulid.'/', $json['path']);
        Storage::disk('public')->assertExists($json['path']);

        $this->postJson(route('landing-pages.images.store', $page), ['image' => UploadedFile::fake()->create('x.svg', 4, 'image/svg+xml')])->assertUnprocessable();

        $sections = $page->sections;
        $gallery = collect($sections)->search(fn ($s) => $s['type'] === 'gallery');
        $sections[$gallery]['data']['items'] = [['image' => $json['path'], 'caption' => 'Balayage']];
        $this->put(route('landing-pages.update', $page), ['sections_json' => json_encode($sections), 'is_published' => '1'])->assertSessionHasNoErrors();

        auth()->logout();
        $this->get('/p/'.$page->slug)->assertOk()->assertSee(Storage::disk('public')->url($json['path']), false)->assertSee('Balayage');
    }

    public function test_brand_kit_applies_colours_logo_and_details(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        Storage::disk('public')->put('brand-kits/logo.png', 'png');
        $kit = BrandKit::create([
            'user_id' => $admin->id, 'name' => 'Himalaya brand', 'primary_color' => '#123456', 'accent_color' => '#f97316',
            'logo_path' => 'brand-kits/logo.png', 'company_details' => ['phone' => '+977 61 000000'],
        ]);
        $page = $this->page('launch');

        $this->actingAs($admin)->post(route('landing-pages.brand-kit', $page), ['brand_kit' => $kit->ulid])->assertRedirect();

        $page->refresh();
        $this->assertSame($kit->id, $page->brand_kit_id);
        $this->assertSame('#123456', $page->style['primary']);
        $this->assertSame('#f97316', $page->style['accent']);
        $this->assertSame('brand-kits/logo.png', $page->settings['logo_path']);
        $this->assertSame('+977 61 000000', collect($page->sections)->firstWhere('type', 'contact')['data']['phone']);

        // A designer can't apply someone else's kit.
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $this->actingAs($designer)->post(route('landing-pages.brand-kit', $page), ['brand_kit' => $kit->ulid])->assertForbidden();
    }

    public function test_static_site_export_is_self_contained(): void
    {
        $page = $this->page();
        Storage::disk('public')->put('landing-pages/'.$page->ulid.'/hero.jpg', 'jpg');
        $sections = $page->sections;
        $sections[0]['data']['image'] = 'landing-pages/'.$page->ulid.'/hero.jpg';
        $page->update(['sections' => $sections]);

        $path = app(LandingPageSiteExporter::class)->export($page->refresh(), 'https://himalaya.netlify.app');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $html = $zip->getFromName('index.html');
        $this->assertNotFalse($zip->locateName('README.txt'));
        $this->assertNotFalse($zip->locateName('assets/'.$sections[0]['id'].'-image.jpg'));
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('assets/'.$sections[0]['id'].'-image.jpg', $html);
        $this->assertStringContainsString('mailto:sita@example.com', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString(url('/'), str_replace(url('/').'/storage', '', $html), 'nothing points back at the app');
        $this->assertStringContainsString('https://himalaya.netlify.app/', $html);

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))->post(route('landing-pages.site.export', $page))
            ->assertOk()->assertHeader('Content-Type', 'application/zip');
    }

    public function test_slugs_are_unique_and_safe(): void
    {
        $service = app(LandingPageService::class);
        $first = $this->page();
        $second = $this->page('blank', $first->customer);
        $this->assertSame($first->slug.'-2', $second->slug);
        $this->assertFalse($service->slugAvailable('admin'));
        $this->assertFalse($service->slugAvailable('-bad-'));

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))
            ->put(route('landing-pages.update', $second), ['slug' => $first->slug])->assertSessionHasErrors('slug');

        $this->assertTrue(Sections::safeUrl('#contact'));
        $this->assertTrue(Sections::safeUrl('tel:+977 980'));
        $this->assertFalse(Sections::safeUrl('javascript:alert(1)'));
        $this->assertFalse(Sections::safeUrl('data:text/html,hi'));
    }
}
