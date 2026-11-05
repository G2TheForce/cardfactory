<x-layouts.app title="Dashboard">
    <x-page-header :title="'Welcome, '.str(auth()->user()->name)->before(' ')" description="Your smart cards, digital profile and engagement at a glance." />

    @if (! $customer)
        <div class="card">
            <x-empty-state icon="contact" title="Your customer profile is not set up yet" description="Ask your account manager to link your customer record to this login." />
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-stat-card label="Card designs" :value="number_format($stats['designs'])" icon="pen-tool" tone="violet" />
            <x-stat-card label="Active digital profiles" :value="number_format($stats['profiles'])" icon="smartphone" tone="sky" />
            <x-stat-card label="NFC cards linked" :value="number_format($stats['nfc_cards'])" icon="nfc" tone="emerald" />
            <x-stat-card label="Profile views" :value="number_format($stats['views'])" icon="eye" />
            <x-stat-card label="QR scans & NFC taps" :value="number_format($stats['scans'])" icon="scan-line" tone="amber" />
            <x-stat-card label="Contacts saved" :value="number_format($stats['contact_saves'])" icon="download" tone="rose" />
        </div>

        @php
            $engagementChart = ['type' => 'line', 'labels' => $engagement['labels'], 'datasets' => [
                ['label' => 'Profile views', 'data' => $engagement['series']['profile_view'], 'color' => '#4f46e5'],
                ['label' => 'QR scans', 'data' => $engagement['series']['qr_scan'], 'color' => '#0ea5e9'],
                ['label' => 'NFC taps', 'data' => $engagement['series']['nfc_tap'], 'color' => '#10b981'],
            ]];
        @endphp
        <div class="card mt-6">
            <div class="card-header"><h2 class="card-title">Last 30 days</h2></div>
            <div class="card-body"><div class="h-72"><canvas data-chart='@json($engagementChart)' role="img" aria-label="Your engagement"></canvas></div></div>
        </div>

        <div class="card mt-6 overflow-hidden">
            <div class="card-header"><h2 class="card-title">Your latest designs</h2></div>
            @forelse ($designs as $design)
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 last:border-0">
                    <span class="text-sm font-medium text-gray-900">{{ $design->name }}</span>
                    <span class="text-xs text-gray-500">Updated {{ $design->updated_at->diffForHumans() }}</span>
                </div>
            @empty
                <x-empty-state icon="pen-tool" title="No card designs yet" description="Your designs will appear here once they are created." />
            @endforelse
        </div>
    @endif
</x-layouts.app>
