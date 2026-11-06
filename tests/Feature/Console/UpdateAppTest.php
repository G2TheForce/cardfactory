<?php

namespace Tests\Feature\Console;

use App\Models\CardTheme;
use App\Support\Themes\ThemeLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UpdateAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_installs_missing_themes_and_permissions_and_is_repeatable(): void
    {
        CardTheme::query()->forceDelete();
        Permission::query()->delete();

        $this->artisan('smartcard:update')->assertSuccessful();

        $library = count(ThemeLibrary::all());
        $this->assertSame($library, CardTheme::where('is_system', true)->count());
        $this->assertSame(count(config('smartcard.permissions')), Permission::count());

        $publishedAt = CardTheme::where('slug', 'artisan-bakery-cafe')->value('published_at');
        $this->assertNotNull($publishedAt);
        $this->travel(2)->days();

        $this->artisan('smartcard:update')->assertSuccessful();

        $this->assertSame($library, CardTheme::where('is_system', true)->count());
        $this->assertEquals($publishedAt, CardTheme::where('slug', 'artisan-bakery-cafe')->value('published_at'));
    }

    public function test_portrait_themes_are_on_the_first_gallery_page(): void
    {
        $first = collect(ThemeLibrary::all())->sortBy('sort_order')->take(8);

        $this->assertTrue($first->every(fn ($t) => (float) $t['height_mm'] > (float) $t['width_mm']));
    }
}
