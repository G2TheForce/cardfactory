<?php

namespace Tests\Feature\Designs;

use App\Models\UploadedAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_images_are_reencoded_and_stored(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);

        $response = $this->actingAs($user)->post(route('assets.store'), [
            'file' => UploadedFile::fake()->image('logo.png', 400, 200),
            'type' => 'logo',
        ], ['Accept' => 'application/json'])->assertCreated();

        $asset = UploadedAsset::first();
        $this->assertSame('logo', $asset->type);
        $this->assertSame(400, $asset->width);
        $this->assertSame('image/png', $asset->mime_type);
        Storage::disk('public')->assertExists($asset->path);
        $response->assertJsonPath('src', '/storage/'.$asset->path);
    }

    public function test_svg_and_non_images_are_rejected(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($user)->postJson(route('assets.store'), ['file' => $svg])->assertJsonValidationErrors('file');
        $this->actingAs($user)->postJson(route('assets.store'), ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('file');
        $this->assertSame(0, UploadedAsset::count());
    }

    public function test_fonts_are_checked_by_signature(): void
    {
        $user = $this->userWithRole(User::ROLE_DESIGNER);
        $font = UploadedFile::fake()->createWithContent('brand.ttf', "\x00\x01\x00\x00".str_repeat("\0", 64));

        $this->actingAs($user)->postJson(route('assets.fonts.store'), ['file' => $font, 'family' => 'Brand Sans'])
            ->assertCreated()->assertJsonPath('family', 'Brand Sans');
        $this->assertSame('font/ttf', UploadedAsset::first()->mime_type);

        $fake = UploadedFile::fake()->createWithContent('evil.ttf', '<?php echo 1;');
        $this->actingAs($user)->postJson(route('assets.fonts.store'), ['file' => $fake, 'family' => 'Evil'])->assertJsonValidationErrors('file');
        $this->actingAs($user)->postJson(route('assets.fonts.store'), ['file' => $font, 'family' => "Bad'); }"])->assertJsonValidationErrors('family');
    }

    public function test_users_only_see_and_delete_their_own_assets(): void
    {
        $owner = $this->userWithRole(User::ROLE_CUSTOMER);
        $this->actingAs($owner)->post(route('assets.store'), ['file' => UploadedFile::fake()->image('a.jpg', 50, 50)], ['Accept' => 'application/json'])->assertCreated();
        $asset = UploadedAsset::first();

        $other = $this->userWithRole(User::ROLE_CUSTOMER);
        $this->actingAs($other)->getJson(route('assets.index'))->assertOk()->assertJsonCount(0);
        $this->actingAs($other)->deleteJson(route('assets.destroy', $asset))->assertForbidden();

        $this->actingAs($owner)->getJson(route('assets.index'))->assertJsonCount(1);
        $this->actingAs($owner)->deleteJson(route('assets.destroy', $asset))->assertOk();
        $this->assertSoftDeleted($asset);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_editor_previews_do_not_use_up_the_upload_rate_limit(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        for ($i = 0; $i < 40; $i++) {
            $this->actingAs($user)->getJson(route('editor.icons', ['q' => 'star']))->assertOk();
        }

        $this->actingAs($user)->post(route('assets.store'), ['file' => UploadedFile::fake()->image('a.jpg', 20, 20)], ['Accept' => 'application/json'])->assertCreated();
    }
}
