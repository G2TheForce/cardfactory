<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\ChromeRenderer;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeChromeRenderer;
use Tests\TestCase;

/**
 * Signs in as each demo role and follows every link the app shows that
 * user, starting at the dashboard. No link may lead to a missing page, a
 * page the user is not allowed to open, or a server error. With demo data
 * loaded, lists hold many rows, so lazy-loading (N+1) violations surface too.
 */
class LinkCrawlTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_PAGES = 250;

    /** Links that change state, stream large files or leave the app. */
    private const SKIP = ['#/logout$#', '#/(q|t|tap)/#', '#\.(svg|png|pdf|zip|vcf)(\?|$)#', '#/download$#', '#/preview/#', '#/storage/#', '#/exports/status#'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->app->instance(ChromeRenderer::class, new FakeChromeRenderer);
        $this->seed([ThemeSeeder::class, DemoUsersSeeder::class, DemoDataSeeder::class]);
    }

    public static function roles(): array
    {
        return [
            'super admin' => ['superadmin@smartcard.test'],
            'admin' => ['admin@smartcard.test'],
            'designer' => ['designer@smartcard.test'],
            'sales manager' => ['sales@smartcard.test'],
            'customer' => ['customer@smartcard.test'],
        ];
    }

    #[DataProvider('roles')]
    public function test_every_link_shown_to_the_role_works(string $email): void
    {
        $this->actingAs(User::where('email', $email)->firstOrFail());
        $base = rtrim(config('app.url'), '/');
        $queue = [$base.'/dashboard'];
        $seen = [];
        $broken = [];

        while ($queue && count($seen) < self::MAX_PAGES) {
            $url = array_shift($queue);
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $response = $this->get($url);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                $broken[] = "$status $url";

                continue;
            }
            if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                continue;
            }

            preg_match_all('/<a\s[^>]*href="([^"#]+)"/i', (string) $response->getContent(), $m);
            foreach ($m[1] as $href) {
                $href = html_entity_decode($href);
                if (! str_starts_with($href, $base.'/') || isset($seen[$href]) || $this->skipped($href)) {
                    continue;
                }
                // One page of each paginated list is enough.
                if (preg_match('/[?&]page=([2-9]|\d{2,})/', $href)) {
                    continue;
                }
                $queue[] = $href;
            }
        }

        $this->assertSame([], $broken, 'Broken links for '.$email);
        $this->assertGreaterThan(5, count($seen));
    }

    private function skipped(string $url): bool
    {
        foreach (self::SKIP as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }
}
