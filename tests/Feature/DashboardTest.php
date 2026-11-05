<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\CardDesign;
use App\Models\CardOrder;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_real_counts(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        Customer::factory()->count(3)->create();
        CardDesign::factory()->count(2)->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Total customers')
            ->assertSee('Revenue')
            ->assertViewHas('stats', fn ($stats) => $stats['customers']['value'] === 3 && $stats['cards']['value'] === 2);
    }

    public function test_designer_dashboard_hides_revenue(): void
    {
        $designer = $this->userWithRole(User::ROLE_DESIGNER);

        $this->actingAs($designer)->get('/dashboard')->assertOk()->assertViewHas('canSeeRevenue', false)->assertDontSee('Monthly recurring revenue');
    }

    public function test_customer_dashboard_only_counts_their_own_data(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $mine = Customer::factory()->create(['user_id' => $user->id]);
        $other = Customer::factory()->create();

        foreach ([$mine, $other] as $customer) {
            $profile = DigitalProfile::factory()->create(['customer_id' => $customer->id]);
            AnalyticsEvent::factory()->count(3)->create(['customer_id' => $customer->id, 'digital_profile_id' => $profile->id]);
        }

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard.customer')
            ->assertViewHas('stats', fn ($stats) => $stats['views'] === 3 && $stats['profiles'] === 1);
    }

    public function test_service_aggregates_revenue_and_monthly_series(): void
    {
        CardOrder::factory()->create(['status' => 'paid', 'total' => 100, 'paid_at' => now()]);
        CardOrder::factory()->create(['status' => 'completed', 'total' => 50, 'paid_at' => now()->subMonth()]);
        CardOrder::factory()->create(['status' => 'cancelled', 'total' => 999, 'paid_at' => null]);

        $service = app(DashboardService::class);

        $this->assertSame(150.0, $service->adminStats()['revenue']['value']);
        $series = $service->monthlyRevenue(12);
        $this->assertCount(12, $series['values']);
        $this->assertSame(100.0, end($series['values']));
        $this->assertSame(50.0, $series['values'][10]);
    }

    public function test_engagement_trend_has_one_point_per_day(): void
    {
        AnalyticsEvent::factory()->create(['event_type' => AnalyticsEvent::QR_SCAN, 'occurred_at' => now()]);

        $trend = app(DashboardService::class)->engagementTrend(30);

        $this->assertCount(30, $trend['labels']);
        $this->assertSame(1, end($trend['series']['qr_scan']));
    }
}
