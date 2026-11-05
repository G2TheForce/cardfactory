@php
    $money = fn ($v) => '$'.number_format($v, $v >= 1000 ? 0 : 2);
    $num = fn ($v) => number_format($v);
    $engagementChart = [
        'type' => 'line',
        'labels' => $engagement['labels'],
        'datasets' => [
            ['label' => 'Profile views', 'data' => $engagement['series']['profile_view'], 'color' => '#4f46e5'],
            ['label' => 'QR scans', 'data' => $engagement['series']['qr_scan'], 'color' => '#0ea5e9'],
            ['label' => 'NFC taps', 'data' => $engagement['series']['nfc_tap'], 'color' => '#10b981'],
        ],
    ];
    $generationChart = ['type' => 'bar', 'labels' => $cardGeneration['labels'], 'datasets' => [['label' => 'Cards', 'data' => $cardGeneration['values'], 'color' => '#6366f1']]];
    $revenueChart = ['type' => 'line', 'format' => 'currency', 'currency' => 'USD', 'labels' => $revenue['labels'], 'datasets' => [['label' => 'Revenue', 'data' => $revenue['values'], 'color' => '#10b981']]];
@endphp
<x-layouts.app title="Dashboard">
    <x-page-header :title="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening')).', '.str(auth()->user()->name)->before(' ')" description="Here's what is happening across SmartCard Studio today.">
        <x-slot:actions>
            @can('users.manage')
                <a href="{{ route('admin.users.create') }}" class="btn-secondary"><x-lucide-user-plus class="h-4 w-4" /> Add user</a>
            @endcan
            @if (Route::has('designs.create'))
                <a href="{{ route('designs.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New card design</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total customers" :value="$num($stats['customers']['value'])" icon="contact" :change="$stats['customers']['change']" />
        <x-stat-card label="Generated cards" :value="$num($stats['cards']['value'])" icon="pen-tool" tone="violet" :change="$stats['cards']['change']" />
        <x-stat-card label="Active digital cards" :value="$num($stats['active_profiles']['value'])" icon="smartphone" tone="sky" :hint="$num($stats['active_profiles']['total']).' profiles in total'" />
        <x-stat-card label="NFC cards" :value="$num($stats['nfc_cards']['value'])" icon="nfc" tone="emerald" :hint="$num($stats['nfc_cards']['active']).' activated'" />
        <x-stat-card label="QR scans" :value="$num($stats['qr_scans']['value'])" icon="qr-code" tone="amber" :change="$stats['qr_scans']['change']" />
        <x-stat-card label="Card & contact downloads" :value="$num($stats['downloads']['value'])" icon="download" tone="rose" hint="Exports plus vCard saves" />
        @if ($canSeeRevenue)
            <x-stat-card label="Revenue" :value="$money($stats['revenue']['value'])" icon="credit-card" tone="emerald" :change="$stats['revenue']['change']" />
            <x-stat-card label="Monthly recurring revenue" :value="$money($stats['revenue']['mrr'])" icon="trending-up" tone="brand" hint="Active subscriptions" />
        @endif
    </div>

    {{-- Charts --}}
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Engagement</h2>
                    <p class="text-xs text-gray-500">Profile views, QR scans and NFC taps, last 30 days</p>
                </div>
            </div>
            <div class="card-body"><div class="h-72"><canvas data-chart='@json($engagementChart)' aria-label="Engagement chart" role="img"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Popular themes</h2>
                @if (Route::has('themes.index'))<a href="{{ route('themes.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">View all</a>@endif
            </div>
            @if ($popularThemes->isEmpty())
                <x-empty-state icon="palette" title="No theme usage yet" description="Theme popularity appears once cards are designed from themes." />
            @else
                @php $max = max(1, $popularThemes->max('card_designs_count')); @endphp
                <ul class="space-y-4 p-5">
                    @foreach ($popularThemes as $theme)
                        <li>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-gray-800">{{ $theme->name }}</span>
                                <span class="text-gray-500">{{ $num($theme->card_designs_count) }} cards</span>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ round($theme->card_designs_count / $max * 100) }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 {{ $canSeeRevenue ? 'xl:grid-cols-2' : '' }}">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Monthly card generation</h2><span class="text-xs text-gray-500">Last 12 months</span></div>
            <div class="card-body"><div class="h-64"><canvas data-chart='@json($generationChart)' role="img" aria-label="Monthly card generation"></canvas></div></div>
        </div>
        @if ($canSeeRevenue)
            <div class="card">
                <div class="card-header"><h2 class="card-title">Revenue</h2><span class="text-xs text-gray-500">Paid orders, last 12 months</span></div>
                <div class="card-body"><div class="h-64"><canvas data-chart='@json($revenueChart)' role="img" aria-label="Monthly revenue"></canvas></div></div>
            </div>
        @endif
    </div>

    {{-- NFC stock --}}
    @if ($nfcStock)
        @php
            $stockChart = ['type' => 'doughnut', 'labels' => ['In stock', 'Assigned & active', 'Sold', 'Defective', 'Lost / replaced', 'Retired'], 'datasets' => [['label' => 'Cards',
                'data' => [$nfcStock['available'], $nfcStock['assigned'], $nfcStock['sold'], $nfcStock['defective'], $nfcStock['lost'], $nfcStock['retired']],
                'colors' => ['#10b981', '#4f46e5', '#0ea5e9', '#ef4444', '#f59e0b', '#9ca3af']]]];
        @endphp
        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="card">
                <div class="card-header"><h2 class="card-title">NFC cards</h2>@if (Route::has('nfc.index') && auth()->user()->can('nfc.manage'))<a href="{{ route('nfc.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Manage</a>@endif</div>
                <div class="card-body">
                    @if ($nfcStock['total'] > 0)
                        <div class="h-56"><canvas data-chart='@json($stockChart)' role="img" aria-label="NFC cards by status"></canvas></div>
                    @else
                        <x-empty-state icon="nfc" title="No NFC cards in stock" />
                    @endif
                </div>
            </div>
            <div class="card overflow-hidden xl:col-span-2">
                <div class="card-header"><h2 class="card-title">Stock alerts</h2>@if (Route::has('inventory.index') && auth()->user()->can('inventory.manage'))<a href="{{ route('inventory.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Inventory</a>@endif</div>
                @forelse ($lowStock as $product)
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 text-sm last:border-0">
                        <span><span class="font-mono text-xs text-gray-500">{{ $product->sku }}</span> <span class="font-medium text-gray-900">{{ $product->name }}</span></span>
                        <span class="badge-amber">{{ $product->stock['available'] }} left · reorder at {{ $product->reorder_level }}</span>
                    </div>
                @empty
                    <div class="card-body text-sm text-gray-500">{{ $nfcStock['available'] }} cards in stock. Every product is above its reorder level.</div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Recent activity --}}
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Recent customers</h2>
                @if (Route::has('customers.index'))<a href="{{ route('customers.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">View all</a>@endif
            </div>
            @forelse ($recentCustomers as $customer)
                <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3 last:border-0">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">{{ mb_strtoupper(mb_substr($customer->full_name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $customer->full_name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ collect([$customer->designation, $customer->company?->name])->filter()->implode(' · ') ?: $customer->email }}</p>
                    </div>
                    <span class="text-xs text-gray-400">{{ $customer->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <x-empty-state icon="contact" title="No customers yet" />
            @endforelse
        </div>
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Recent card designs</h2>
                @if (Route::has('designs.index'))<a href="{{ route('designs.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">View all</a>@endif
            </div>
            @forelse ($recentDesigns as $design)
                <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3 last:border-0">
                    <span class="grid h-9 w-14 shrink-0 place-items-center rounded-md bg-gradient-to-br from-gray-800 to-gray-600 text-white"><x-lucide-pen-tool class="h-4 w-4" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $design->name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ collect([$design->customer?->full_name, $design->theme?->name])->filter()->implode(' · ') ?: 'Blank design' }}</p>
                    </div>
                    <span @class(['badge-green' => $design->status === 'final', 'badge-gray' => $design->status !== 'final'])>{{ ucfirst($design->status) }}</span>
                </div>
            @empty
                <x-empty-state icon="pen-tool" title="No designs yet" />
            @endforelse
        </div>
    </div>

    {{-- Quick actions: only modules that exist are shown --}}
    @php
    $quick = collect([
        ['route' => 'designs.create', 'icon' => 'pen-tool', 'label' => 'Design a card', 'text' => 'Open the visual editor'],
        ['route' => 'customers.create', 'icon' => 'contact', 'label' => 'Add customer', 'text' => 'Create a customer record', 'can' => 'customers.manage'],
        ['route' => 'imports.create', 'icon' => 'layers', 'label' => 'Bulk generate', 'text' => 'Import a CSV or Excel file', 'can' => 'imports.manage'],
        ['route' => 'nfc.index', 'icon' => 'nfc', 'label' => 'Assign NFC card', 'text' => 'Link a tag to a profile', 'can' => 'nfc.manage'],
        ['route' => 'admin.users.create', 'icon' => 'user-plus', 'label' => 'Invite team member', 'text' => 'Add staff or customers', 'can' => 'users.manage'],
        ['route' => 'admin.activity.index', 'icon' => 'scroll-text', 'label' => 'Audit log', 'text' => 'Review recent activity', 'can' => 'activity.view'],
        ['route' => 'settings.tokens', 'icon' => 'key-round', 'label' => 'API tokens', 'text' => 'Connect other systems'],
    ])->filter(fn ($a) => Route::has($a['route']) && (! isset($a['can']) || auth()->user()->can($a['can'])));
    @endphp
    <h2 class="mb-3 mt-8 text-sm font-semibold text-gray-900">Quick actions</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($quick as $action)
            <a href="{{ route($action['route']) }}" class="card group flex items-center gap-4 p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white">
                    <x-dynamic-component :component="'lucide-'.$action['icon']" class="h-5 w-5" />
                </span>
                <span><span class="block text-sm font-semibold text-gray-900">{{ $action['label'] }}</span><span class="block text-xs text-gray-500">{{ $action['text'] }}</span></span>
            </a>
        @endforeach
    </div>
</x-layouts.app>
