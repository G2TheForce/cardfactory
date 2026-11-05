<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\User;
use App\Services\AnalyticsReportService;
use App\Services\DigitalProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function profileFor(Customer $customer): DigitalProfile
    {
        return app(DigitalProfileService::class)->create($customer, [], $this->userWithRole(User::ROLE_ADMIN));
    }

    private function event(DigitalProfile $profile, string $type, array $attributes = []): void
    {
        AnalyticsEvent::create($attributes + [
            'customer_id' => $profile->customer_id, 'digital_profile_id' => $profile->id, 'event_type' => $type,
            'occurred_at' => now()->subDay(), 'device_type' => 'mobile', 'source' => 'qr', 'country_code' => 'GB',
        ]);
    }

    public function test_customers_see_only_their_own_profile_analytics(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $mine = $this->profileFor(Customer::factory()->create(['user_id' => $user->id]));
        $theirs = $this->profileFor(Customer::factory()->create());

        $this->event($mine, AnalyticsEvent::PROFILE_VIEW, ['visitor_hash' => str_repeat('a', 64)]);
        $this->event($mine, AnalyticsEvent::PROFILE_VIEW, ['visitor_hash' => str_repeat('a', 64)]);
        $this->event($mine, AnalyticsEvent::PROFILE_VIEW, ['visitor_hash' => str_repeat('b', 64)]);
        $this->event($mine, AnalyticsEvent::QR_SCAN);
        $this->event($mine, AnalyticsEvent::CLICK_SOCIAL, ['target' => 'linkedin']);
        $this->event($mine, AnalyticsEvent::CLICK_WHATSAPP);
        $this->event($mine, AnalyticsEvent::VCARD_DOWNLOAD);
        $this->event($mine, AnalyticsEvent::PROFILE_VIEW, ['occurred_at' => now()->subDays(40)]); // previous period
        foreach (range(1, 5) as $i) {
            $this->event($theirs, AnalyticsEvent::PROFILE_VIEW);
        }

        $report = app(AnalyticsReportService::class)->report($user, ['period' => '30d']);
        $this->assertSame(3, $report['totals']['views']['value']);
        $this->assertSame(1, $report['totals']['views']['previous']);
        $this->assertSame(200.0, $report['totals']['views']['change']);
        $this->assertSame(2, $report['totals']['visitors']['value']);
        $this->assertSame(1, $report['totals']['qr_scans']['value']);
        $this->assertSame(2, $report['totals']['clicks']['value']);
        $this->assertSame(1, $report['totals']['contacts']['value']);
        $this->assertSame([['label' => 'linkedin', 'value' => 1]], $report['social']);
        $this->assertSame([['label' => 'GB', 'value' => 3]], $report['countries']);
        $this->assertCount(30, $report['series']['labels']);
        $this->assertSame(3, array_sum($report['series']['views']));

        $this->actingAs($user)->get(route('analytics.index'))->assertOk()->assertSee('Engagement for your digital profile')->assertSee('LinkedIn');
        $this->get(route('analytics.index', ['profile' => $theirs->ulid]))->assertNotFound();
        $this->get(route('analytics.index', ['period' => 'forever']))->assertSessionHasErrors('period');
    }

    public function test_weekly_and_monthly_grouping(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $profile = $this->profileFor(Customer::factory()->create());
        $this->event($profile, AnalyticsEvent::PROFILE_VIEW, ['occurred_at' => now()->subMonths(3)]);
        $this->event($profile, AnalyticsEvent::NFC_TAP, ['occurred_at' => now()->subDays(2)]);

        $service = app(AnalyticsReportService::class);
        $monthly = $service->report($admin, ['period' => '12m']);
        $this->assertSame('month', $monthly['group']);
        $this->assertContains(count($monthly['series']['labels']), [12, 13]);
        $this->assertSame(1, array_sum($monthly['series']['views']));
        $this->assertSame(1, array_sum($monthly['series']['scans']));

        $weekly = $service->report($admin, ['period' => '90d', 'profile' => $profile]);
        $this->assertSame('week', $weekly['group']);
        $this->assertLessThanOrEqual(15, count($weekly['series']['labels']));

        $this->actingAs($admin)->get(route('analytics.index', ['period' => '90d', 'group' => 'day']))->assertOk()->assertSee('Daily traffic');
    }

    public function test_daily_csv_export(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $profile = $this->profileFor(Customer::factory()->create(['user_id' => $user->id]));
        $this->event($profile, AnalyticsEvent::PROFILE_VIEW);

        $csv = $this->actingAs($user)->get(route('analytics.export', ['period' => '7d']))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(8, $lines);
        $this->assertStringContainsString('Date,"Profile views"', $lines[0]);
        $this->assertStringContainsString(now()->subDay()->toDateString().',1,0', $csv);
    }
}
