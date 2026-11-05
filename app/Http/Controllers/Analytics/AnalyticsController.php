<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\DigitalProfile;
use App\Services\AnalyticsReportService;
use App\Services\CardExportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Engagement analytics for the profiles the signed-in user may see (customers see their own). */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsReportService $reports) {}

    public function index(Request $request): View
    {
        [$filters, $profile] = $this->filters($request);
        $profiles = $this->reports->profilesFor($request->user())->with('customer:id,full_name')->orderBy('slug')->limit(500)->get(['id', 'ulid', 'slug', 'customer_id']);

        return view('analytics.index', [
            'report' => $this->reports->report($request->user(), ['period' => $filters['period'], 'group' => $filters['group'] ?? null, 'profile' => $profile]),
            'profiles' => $profiles,
            'profile' => $profile,
            'filters' => $filters,
            'periods' => AnalyticsReportService::PERIODS,
            'groups' => AnalyticsReportService::GROUPS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$filters, $profile] = $this->filters($request);
        $days = AnalyticsReportService::PERIODS[$filters['period']]['days'];
        $to = CarbonImmutable::now()->endOfDay();
        $rows = $this->reports->dailyRows($request->user(), $profile, $to->subDays($days - 1)->startOfDay(), $to);

        return response()->streamDownload(fn () => print (app(CardExportService::class)->csv($rows)),
            'analytics-'.($profile?->slug ?? 'all').'-'.$filters['period'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: array, 1: ?DigitalProfile} */
    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(array_keys(AnalyticsReportService::PERIODS))],
            'group' => ['nullable', Rule::in(array_keys(AnalyticsReportService::GROUPS))],
            'profile' => ['nullable', 'string', 'max:40'],
        ]) + ['period' => '30d'];
        $filters['period'] ??= '30d';

        $profile = null;
        if (! empty($filters['profile'])) {
            $profile = DigitalProfile::where('ulid', $filters['profile'])->first();
            abort_unless($profile && $request->user()->can('view', $profile), 404);
        }

        return [$filters, $profile];
    }
}
