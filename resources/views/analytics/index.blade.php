@php
    $s = $report['series'];
    $trafficChart = ['type' => $report['group'] === 'day' ? 'line' : 'bar', 'labels' => $s['labels'], 'datasets' => [
        ['label' => 'Profile views', 'data' => $s['views'], 'color' => '#4f46e5'],
        ['label' => 'QR & NFC scans', 'data' => $s['scans'], 'color' => '#0ea5e9'],
        ['label' => 'Clicks', 'data' => $s['clicks'], 'color' => '#10b981'],
        ['label' => 'Contacts saved', 'data' => $s['contacts'], 'color' => '#f59e0b'],
    ]];
    $clickChart = ['type' => 'bar', 'labels' => array_column($report['clicks'], 'label'), 'datasets' => [['label' => 'Clicks', 'data' => array_column($report['clicks'], 'value'), 'color' => '#10b981']]];
    $deviceChart = ['type' => 'doughnut', 'labels' => array_map('ucfirst', array_column($report['devices'], 'label')), 'datasets' => [['label' => 'Views', 'data' => array_column($report['devices'], 'value')]]];
    $t = $report['totals'];
    $cards = [
        ['Profile views', $t['views'], 'eye', 'brand'], ['Unique visitors', $t['visitors'], 'users', 'violet'],
        ['QR scans', $t['qr_scans'], 'qr-code', 'sky'], ['NFC taps', $t['nfc_taps'], 'nfc', 'emerald'],
        ['Contacts saved', $t['contacts'], 'contact', 'amber'], ['Link clicks', $t['clicks'], 'mouse-pointer-click', 'rose'],
    ];
    $sourceLabels = ['qr' => 'QR code', 'nfc' => 'NFC tap', 'direct' => 'Direct / shared link', 'link' => 'Other website', 'Unknown' => 'Unknown', 'Other' => 'Other'];
    $countryName = fn ($code) => strlen($code) === 2 && class_exists(\Locale::class) ? (\Locale::getDisplayRegion('-'.$code, 'en') ?: $code) : $code;
    $empty = array_sum(array_column($t, 'value')) === 0;
@endphp
<x-layouts.app title="Analytics">
    <x-page-header title="Analytics" :description="$profile ? 'Engagement for /card/'.$profile->slug.'.' : ($profiles->count() === 1 ? 'Engagement for your digital profile.' : 'Engagement across '.$profiles->count().' digital profiles.')">
        <x-slot:actions>
            <a href="{{ route('analytics.export', request()->query()) }}" class="btn-secondary"><x-lucide-download class="h-4 w-4" /> Daily CSV</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
        @if ($profiles->count() > 1)
            <div class="min-w-56 flex-1">
                <label for="profile" class="form-label">Profile</label>
                <select id="profile" name="profile" class="select-base" onchange="this.form.submit()">
                    <option value="">All profiles</option>
                    @foreach ($profiles as $p)<option value="{{ $p->ulid }}" @selected($profile?->is($p))>{{ $p->customer?->full_name }} · /card/{{ $p->slug }}</option>@endforeach
                </select>
            </div>
        @endif
        <div><label for="period" class="form-label">Period</label>
            <select id="period" name="period" class="select-base" onchange="this.form.submit()">@foreach ($periods as $key => $p)<option value="{{ $key }}" @selected($filters['period'] === $key)>{{ $p['label'] }}</option>@endforeach</select></div>
        <div><label for="group" class="form-label">Show</label>
            <select id="group" name="group" class="select-base" onchange="this.form.submit()">@foreach ($groups as $key => $label)<option value="{{ $key }}" @selected($report['group'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <noscript><button class="btn-secondary">Apply</button></noscript>
        <p class="ml-auto text-xs text-gray-500">{{ $report['from']->toFormattedDateString() }} – {{ $report['to']->toFormattedDateString() }} · compared with the period before</p>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ($cards as [$label, $m, $icon, $tone])
            <x-stat-card :label="$label" :value="number_format($m['value'])" :icon="$icon" :tone="$tone" :change="$m['change']" />
        @endforeach
    </div>

    @if ($empty)
        <div class="card"><x-empty-state icon="chart-line" title="No visits in this period yet" description="Share your profile link, QR code or NFC card. Views, scans and clicks show up here as they happen." /></div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card lg:col-span-2">
                <div class="card-header"><h2 class="font-semibold text-gray-900">{{ $groups[$report['group']] }} traffic</h2></div>
                <div class="card-body"><div class="h-72"><canvas data-chart='@json($trafficChart)' role="img" aria-label="Traffic over time"></canvas></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="font-semibold text-gray-900">Devices</h2></div>
                <div class="card-body"><div class="h-72"><canvas data-chart='@json($deviceChart)' role="img" aria-label="Views by device"></canvas></div></div>
            </div>

            <div class="card lg:col-span-2">
                <div class="card-header"><h2 class="font-semibold text-gray-900">Clicks</h2><span class="text-sm text-gray-500">{{ $report['click_rate'] }} clicks per 100 views</span></div>
                <div class="card-body"><div class="h-64"><canvas data-chart='@json($clickChart)' role="img" aria-label="Clicks by type"></canvas></div></div>
            </div>
            @include('analytics._list', ['title' => 'Social networks', 'rows' => $report['social'], 'labeler' => fn ($l) => \App\Support\CustomerFields::SOCIAL[$l] ?? ucfirst($l), 'empty' => 'No social clicks yet.'])

            @include('analytics._list', ['title' => 'Countries', 'rows' => $report['countries'], 'labeler' => $countryName, 'empty' => 'Country data appears when your site runs behind a CDN that reports it.'])
            @include('analytics._list', ['title' => 'How people arrived', 'rows' => $report['sources'], 'labeler' => fn ($l) => $sourceLabels[$l] ?? ucfirst($l), 'empty' => 'No views yet.'])
            @include('analytics._list', ['title' => 'Referring websites', 'rows' => $report['referrers'], 'labeler' => fn ($l) => $l, 'empty' => 'No visits from other websites yet.'])
        </div>

        @if ($report['top_profiles']->count() > 1)
            <div class="card mt-6 overflow-hidden">
                <div class="card-header"><h2 class="font-semibold text-gray-900">Most visited profiles</h2></div>
                <table class="table">
                    <thead><tr><th>Profile</th><th class="text-right">Views</th><th class="text-right">Scans</th><th class="text-right">All events</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($report['top_profiles'] as $row)
                            <tr>
                                <td><a href="{{ route('analytics.index', ['profile' => $row['profile']->ulid, 'period' => $filters['period']]) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $row['profile']->customer?->full_name }}</a><span class="block text-xs text-gray-500">/card/{{ $row['profile']->slug }}</span></td>
                                <td class="text-right text-sm">{{ number_format($row['views']) }}</td>
                                <td class="text-right text-sm">{{ number_format($row['scans']) }}</td>
                                <td class="text-right text-sm">{{ number_format($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    <p class="mt-6 text-xs text-gray-500">Visitors are counted without cookies or stored IP addresses. Unique visitors are counted per day, and people who ask not to be tracked are counted without even that.</p>
</x-layouts.app>
