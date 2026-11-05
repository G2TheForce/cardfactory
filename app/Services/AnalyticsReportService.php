<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\DigitalProfile;
use App\Models\User;
use App\Policies\DigitalProfilePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Engagement reports for digital profiles: views, QR scans, NFC taps,
 * saved contacts and every kind of click, by day, week or month, plus
 * countries, devices, traffic sources and referrers. Built only from the
 * anonymous events ProfileAnalytics records (no IPs or user agents).
 * Each user only sees events for the profiles they're allowed to view.
 */
class AnalyticsReportService
{
    public const PERIODS = [
        '7d' => ['label' => 'Last 7 days', 'days' => 7, 'group' => 'day'],
        '30d' => ['label' => 'Last 30 days', 'days' => 30, 'group' => 'day'],
        '90d' => ['label' => 'Last 90 days', 'days' => 90, 'group' => 'week'],
        '12m' => ['label' => 'Last 12 months', 'days' => 365, 'group' => 'month'],
    ];

    public const GROUPS = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'];

    public const CLICK_LABELS = [
        AnalyticsEvent::CLICK_WEBSITE => 'Website',
        AnalyticsEvent::CLICK_WHATSAPP => 'WhatsApp',
        AnalyticsEvent::CLICK_EMAIL => 'Email',
        AnalyticsEvent::CLICK_PHONE => 'Phone',
        AnalyticsEvent::CLICK_SOCIAL => 'Social media',
        AnalyticsEvent::CLICK_CUSTOM => 'Other links',
    ];

    /** Profiles whose analytics the user may see. */
    public function profilesFor(User $user): Builder
    {
        return DigitalProfilePolicy::visibleTo($user);
    }

    /**
     * @param  array{period?: string, group?: ?string, profile?: ?DigitalProfile}  $options
     */
    public function report(User $user, array $options): array
    {
        $period = self::PERIODS[$options['period'] ?? '30d'] ?? self::PERIODS['30d'];
        $group = isset(self::GROUPS[$options['group'] ?? '']) ? $options['group'] : $period['group'];
        $to = CarbonImmutable::now()->endOfDay();
        $from = $to->subDays($period['days'] - 1)->startOfDay();
        $prevFrom = $from->subDays($period['days']);

        $scope = fn () => $this->events($user, $options['profile'] ?? null);

        $current = $this->countsByType($scope()->whereBetween('occurred_at', [$from, $to]));
        $previous = $this->countsByType($scope()->whereBetween('occurred_at', [$prevFrom, $from->subSecond()]));

        $daily = $scope()->whereBetween('occurred_at', [$from, $to])
            ->selectRaw($this->dayExpression().' as day, event_type, COUNT(*) as aggregate')
            ->groupBy('day', 'event_type')->get();

        $clicks = array_sum(array_intersect_key($current, self::CLICK_LABELS));
        $prevClicks = array_sum(array_intersect_key($previous, self::CLICK_LABELS));
        $views = $current[AnalyticsEvent::PROFILE_VIEW] ?? 0;

        return [
            'from' => $from,
            'to' => $to,
            'group' => $group,
            'totals' => [
                'views' => $this->metric($views, $previous[AnalyticsEvent::PROFILE_VIEW] ?? 0),
                'visitors' => $this->metric(
                    (int) $scope()->whereBetween('occurred_at', [$from, $to])->where('event_type', AnalyticsEvent::PROFILE_VIEW)->whereNotNull('visitor_hash')->distinct()->count('visitor_hash'),
                    (int) $scope()->whereBetween('occurred_at', [$prevFrom, $from->subSecond()])->where('event_type', AnalyticsEvent::PROFILE_VIEW)->whereNotNull('visitor_hash')->distinct()->count('visitor_hash'),
                ),
                'qr_scans' => $this->metric($current[AnalyticsEvent::QR_SCAN] ?? 0, $previous[AnalyticsEvent::QR_SCAN] ?? 0),
                'nfc_taps' => $this->metric($current[AnalyticsEvent::NFC_TAP] ?? 0, $previous[AnalyticsEvent::NFC_TAP] ?? 0),
                'contacts' => $this->metric($current[AnalyticsEvent::VCARD_DOWNLOAD] ?? 0, $previous[AnalyticsEvent::VCARD_DOWNLOAD] ?? 0),
                'clicks' => $this->metric($clicks, $prevClicks),
            ],
            'click_rate' => $views > 0 ? round($clicks / $views * 100, 1) : 0.0,
            'clicks' => collect(self::CLICK_LABELS)->map(fn ($label, $type) => ['label' => $label, 'value' => $current[$type] ?? 0])->values()->all(),
            'series' => $this->series($daily, $from, $to, $group),
            'social' => $this->breakdown($scope()->whereBetween('occurred_at', [$from, $to])->where('event_type', AnalyticsEvent::CLICK_SOCIAL), 'target'),
            'countries' => $this->breakdown($scope()->whereBetween('occurred_at', [$from, $to])->whereIn('event_type', [AnalyticsEvent::PROFILE_VIEW]), 'country_code', 10),
            'devices' => $this->breakdown($scope()->whereBetween('occurred_at', [$from, $to])->where('event_type', AnalyticsEvent::PROFILE_VIEW), 'device_type'),
            'sources' => $this->breakdown($scope()->whereBetween('occurred_at', [$from, $to])->where('event_type', AnalyticsEvent::PROFILE_VIEW), 'source'),
            'referrers' => $this->breakdown($scope()->whereBetween('occurred_at', [$from, $to])->where('event_type', AnalyticsEvent::PROFILE_VIEW)->whereNotNull('referrer_host'), 'referrer_host', 8),
            'top_profiles' => ! empty($options['profile']) ? collect() : $this->topProfiles($scope()->whereBetween('occurred_at', [$from, $to])),
        ];
    }

    /** Daily counts per event type for the CSV export. */
    public function dailyRows(User $user, ?DigitalProfile $profile, CarbonImmutable $from, CarbonImmutable $to): \Generator
    {
        $types = array_merge([AnalyticsEvent::PROFILE_VIEW, AnalyticsEvent::QR_SCAN, AnalyticsEvent::NFC_TAP, AnalyticsEvent::VCARD_DOWNLOAD], array_keys(self::CLICK_LABELS));
        $rows = $this->events($user, $profile)->whereBetween('occurred_at', [$from, $to])
            ->selectRaw($this->dayExpression().' as day, event_type, COUNT(*) as aggregate')
            ->groupBy('day', 'event_type')->get()->groupBy('day');

        yield array_merge(['Date', 'Profile views', 'QR scans', 'NFC taps', 'Contacts saved'], array_values(self::CLICK_LABELS));
        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $counts = ($rows[$day->toDateString()] ?? collect())->pluck('aggregate', 'event_type');
            yield array_merge([$day->toDateString()], array_map(fn ($t) => (int) ($counts[$t] ?? 0), $types));
        }
    }

    private function events(User $user, ?DigitalProfile $profile): Builder
    {
        return AnalyticsEvent::query()
            ->when($profile, fn (Builder $q) => $q->where('digital_profile_id', $profile->id))
            ->unless($profile, fn (Builder $q) => $q->whereIn('digital_profile_id', $this->profilesFor($user)->select('digital_profiles.id')));
    }

    /** @return array<string, int> */
    private function countsByType(Builder $query): array
    {
        return $query->selectRaw('event_type, COUNT(*) as aggregate')->groupBy('event_type')
            ->pluck('aggregate', 'event_type')->map(fn ($v) => (int) $v)->all();
    }

    private function metric(int $value, int $previous): array
    {
        $change = $previous === 0 ? ($value > 0 ? 100.0 : 0.0) : round(($value - $previous) / $previous * 100, 1);

        return ['value' => $value, 'previous' => $previous, 'change' => $change];
    }

    /** Buckets daily rows into days, ISO weeks or months, filling gaps with zeros. */
    private function series(Collection $daily, CarbonImmutable $from, CarbonImmutable $to, string $group): array
    {
        $key = fn (CarbonImmutable $d) => match ($group) {
            'week' => $d->startOfWeek()->toDateString(),
            'month' => $d->format('Y-m'),
            default => $d->toDateString(),
        };
        $label = fn (CarbonImmutable $d) => match ($group) {
            'week' => 'Wk of '.$d->startOfWeek()->format('M j'),
            'month' => $d->format('M Y'),
            default => $d->format('M j'),
        };

        $buckets = [];
        for ($d = $from; $d <= $to; $d = $d->addDay()) {
            $buckets[$key($d)] ??= ['label' => $label($d), 'views' => 0, 'scans' => 0, 'clicks' => 0, 'contacts' => 0];
        }
        foreach ($daily as $row) {
            $k = $key(CarbonImmutable::parse($row->day));
            if (! isset($buckets[$k])) {
                continue;
            }
            $n = (int) $row->aggregate;
            match (true) {
                $row->event_type === AnalyticsEvent::PROFILE_VIEW => $buckets[$k]['views'] += $n,
                in_array($row->event_type, [AnalyticsEvent::QR_SCAN, AnalyticsEvent::NFC_TAP], true) => $buckets[$k]['scans'] += $n,
                $row->event_type === AnalyticsEvent::VCARD_DOWNLOAD => $buckets[$k]['contacts'] += $n,
                isset(self::CLICK_LABELS[$row->event_type]) => $buckets[$k]['clicks'] += $n,
                default => null,
            };
        }

        $b = collect($buckets)->values();

        return [
            'labels' => $b->pluck('label')->all(),
            'views' => $b->pluck('views')->all(),
            'scans' => $b->pluck('scans')->all(),
            'clicks' => $b->pluck('clicks')->all(),
            'contacts' => $b->pluck('contacts')->all(),
        ];
    }

    /** @return array<int, array{label: string, value: int}> */
    private function breakdown(Builder $query, string $column, int $limit = 6): array
    {
        $rows = $query->selectRaw("COALESCE($column, '') as bucket, COUNT(*) as aggregate")->groupBy('bucket')
            ->orderByDesc('aggregate')->get();
        $top = $rows->take($limit)->map(fn ($r) => ['label' => $r->bucket === '' ? 'Unknown' : (string) $r->bucket, 'value' => (int) $r->aggregate]);
        $rest = (int) $rows->slice($limit)->sum('aggregate');

        return $top->when($rest > 0, fn ($c) => $c->push(['label' => 'Other', 'value' => $rest]))->values()->all();
    }

    private function topProfiles(Builder $query): Collection
    {
        $rows = $query->selectRaw('digital_profile_id, SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as views, SUM(CASE WHEN event_type IN (?, ?) THEN 1 ELSE 0 END) as scans, COUNT(*) as total', [AnalyticsEvent::PROFILE_VIEW, AnalyticsEvent::QR_SCAN, AnalyticsEvent::NFC_TAP])
            ->groupBy('digital_profile_id')->orderByDesc('total')->limit(8)->get();
        $profiles = DigitalProfile::withTrashed()->with('customer:id,full_name')->whereIn('id', $rows->pluck('digital_profile_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => ['profile' => $profiles[$r->digital_profile_id] ?? null, 'views' => (int) $r->views, 'scans' => (int) $r->scans, 'total' => (int) $r->total])
            ->filter(fn ($r) => $r['profile'])->values();
    }

    private function dayExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', occurred_at)",
            'pgsql' => "to_char(occurred_at, 'YYYY-MM-DD')",
            default => "DATE_FORMAT(occurred_at, '%Y-%m-%d')",
        };
    }
}
