<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\CardDesign;
use App\Models\CardExport;
use App\Models\CardOrder;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\NfcCard;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates platform-wide (admin) and per-customer dashboard statistics.
 * All numbers come from the database; nothing is hard-coded.
 */
class DashboardService
{
    public function adminStats(): array
    {
        $now = now();
        $start = $now->copy()->subDays(30);
        $prevStart = $now->copy()->subDays(60);

        $qrScans = AnalyticsEvent::where('event_type', AnalyticsEvent::QR_SCAN);

        return [
            'customers' => [
                'value' => Customer::count(),
                'change' => $this->change(Customer::query(), 'created_at', $start, $prevStart),
            ],
            'cards' => [
                'value' => CardDesign::count(),
                'change' => $this->change(CardDesign::query(), 'created_at', $start, $prevStart),
            ],
            'active_profiles' => [
                'value' => DigitalProfile::where('is_active', true)->count(),
                'total' => DigitalProfile::count(),
            ],
            'nfc_cards' => [
                'value' => NfcCard::count(),
                'active' => NfcCard::where('status', 'active')->count(),
            ],
            'qr_scans' => [
                'value' => (clone $qrScans)->count(),
                'change' => $this->change(clone $qrScans, 'occurred_at', $start, $prevStart),
            ],
            'downloads' => [
                'value' => (int) CardExport::sum('downloads_count')
                    + AnalyticsEvent::where('event_type', AnalyticsEvent::VCARD_DOWNLOAD)->count(),
            ],
            'revenue' => [
                'value' => (float) CardOrder::whereIn('status', CardOrder::REVENUE_STATUSES)->sum('total'),
                'change' => $this->change(CardOrder::whereIn('status', CardOrder::REVENUE_STATUSES), 'paid_at', $start, $prevStart, 'total'),
                'mrr' => $this->monthlyRecurringRevenue(),
            ],
        ];
    }

    public function recentCustomers(int $limit = 6): Collection
    {
        return Customer::with('company:id,name')->latest()->limit($limit)->get();
    }

    public function recentDesigns(int $limit = 6): Collection
    {
        return CardDesign::with(['customer:id,ulid,full_name', 'theme:id,name'])->latest()->limit($limit)->get();
    }

    public function popularThemes(int $limit = 5): Collection
    {
        return CardTheme::withCount('cardDesigns')
            ->orderByDesc('card_designs_count')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->filter(fn (CardTheme $theme) => $theme->card_designs_count > 0 || $theme->usage_count > 0)
            ->values();
    }

    /** Card designs created per month for the last $months months. */
    public function monthlyCardGeneration(int $months = 12): array
    {
        return $this->monthlySeries(CardDesign::query(), 'created_at', $months);
    }

    /** Paid order revenue per month for the last $months months. */
    public function monthlyRevenue(int $months = 12): array
    {
        return $this->monthlySeries(CardOrder::whereIn('status', CardOrder::REVENUE_STATUSES), 'paid_at', $months, 'total');
    }

    /** Daily profile views, QR scans and NFC taps for the last $days days. */
    public function engagementTrend(int $days = 30, ?int $customerId = null): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $expr = $this->dateExpression('occurred_at', 'day');

        $rows = AnalyticsEvent::query()
            ->when($customerId, fn (Builder $q) => $q->where('customer_id', $customerId))
            ->whereIn('event_type', [AnalyticsEvent::PROFILE_VIEW, AnalyticsEvent::QR_SCAN, AnalyticsEvent::NFC_TAP])
            ->where('occurred_at', '>=', $from)
            ->selectRaw("$expr as bucket, event_type, COUNT(*) as aggregate")
            ->groupBy('bucket', 'event_type')
            ->get();

        $labels = [];
        $series = [AnalyticsEvent::PROFILE_VIEW => [], AnalyticsEvent::QR_SCAN => [], AnalyticsEvent::NFC_TAP => []];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('M j');
            foreach ($series as $type => $_) {
                $series[$type][] = (int) ($rows->first(fn ($r) => $r->bucket === $key && $r->event_type === $type)?->aggregate ?? 0);
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }

    public function customerStats(Customer $customer): array
    {
        $events = AnalyticsEvent::where('customer_id', $customer->id);

        return [
            'designs' => $customer->cardDesigns()->count(),
            'profiles' => $customer->digitalProfiles()->where('is_active', true)->count(),
            'views' => (clone $events)->where('event_type', AnalyticsEvent::PROFILE_VIEW)->count(),
            'scans' => (clone $events)->whereIn('event_type', [AnalyticsEvent::QR_SCAN, AnalyticsEvent::NFC_TAP])->count(),
            'contact_saves' => (clone $events)->where('event_type', AnalyticsEvent::VCARD_DOWNLOAD)->count(),
            'nfc_cards' => $customer->nfcAssignments()->whereNull('ended_at')->count(),
        ];
    }

    private function monthlyRecurringRevenue(): float
    {
        return (float) Subscription::where('status', 'active')->get(['amount', 'interval'])
            ->sum(fn (Subscription $s) => $s->interval === 'year' ? $s->amount / 12 : $s->amount);
    }

    /** Percentage change between the last 30 days and the 30 days before. */
    private function change(Builder $query, string $column, Carbon $start, Carbon $prevStart, ?string $sumColumn = null): float
    {
        $current = (clone $query)->where($column, '>=', $start);
        $previous = (clone $query)->whereBetween($column, [$prevStart, $start]);

        $a = (float) ($sumColumn ? $current->sum($sumColumn) : $current->count());
        $b = (float) ($sumColumn ? $previous->sum($sumColumn) : $previous->count());

        if ($b == 0.0) {
            return $a > 0 ? 100.0 : 0.0;
        }

        return round((($a - $b) / $b) * 100, 1);
    }

    private function monthlySeries(Builder $query, string $column, int $months, ?string $sumColumn = null): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $expr = $this->dateExpression($column, 'month');
        $aggregate = $sumColumn ? "SUM($sumColumn)" : 'COUNT(*)';

        $rows = $query->where($column, '>=', $from)
            ->selectRaw("$expr as bucket, $aggregate as aggregate")
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        $labels = $values = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $from->copy()->addMonths($i);
            $labels[] = $month->format('M y');
            $values[] = round((float) ($rows[$month->format('Y-m')] ?? 0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Portable "YYYY-MM" / "YYYY-MM-DD" bucket expression for MySQL, MariaDB, SQLite and PostgreSQL. */
    private function dateExpression(string $column, string $unit): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => $unit === 'month' ? "strftime('%Y-%m', $column)" : "strftime('%Y-%m-%d', $column)",
            'pgsql' => $unit === 'month' ? "to_char($column, 'YYYY-MM')" : "to_char($column, 'YYYY-MM-DD')",
            default => $unit === 'month' ? "DATE_FORMAT($column, '%Y-%m')" : "DATE_FORMAT($column, '%Y-%m-%d')",
        };
    }
}
