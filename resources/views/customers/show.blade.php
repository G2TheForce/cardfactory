@php
    use App\Support\CustomerFields;
    $statusBadges = ['active' => 'badge-green', 'inactive' => 'badge-gray', 'archived' => 'badge-amber'];
    $contacts = array_filter([
        ['phone', 'Mobile', $customer->mobile, $customer->mobile ? 'tel:'.preg_replace('/[^0-9+]/', '', $customer->mobile) : null],
        ['message-circle', 'WhatsApp', $customer->whatsapp, $customer->whatsapp ? 'https://wa.me/'.preg_replace('/\D/', '', $customer->whatsapp) : null],
        ['phone', 'Office', $customer->office_phone, $customer->office_phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $customer->office_phone) : null],
        ['mail', 'Email', $customer->email, $customer->email ? 'mailto:'.$customer->email : null],
        ['globe', 'Website', $customer->website ? preg_replace('#^https?://#', '', $customer->website) : null, $customer->website],
        ['map-pin', 'Address', collect([$customer->office_address, $customer->city, $customer->province, $customer->country])->filter()->implode(', ') ?: null, $customer->map_url],
    ], fn ($row) => $row[2]);
    $hidden = collect(CustomerFields::VISIBLE_FIELDS)->filter(fn ($label, $field) => ! CustomerFields::visible($customer->visibility, 'card', $field) || ! CustomerFields::visible($customer->visibility, 'profile', $field));
@endphp
<x-layouts.app :title="$customer->full_name">
    @push('head')
        @if ($fontsUrl)<link rel="stylesheet" href="{{ $fontsUrl }}">@endif
    @endpush
    <x-slot:breadcrumbs>
        @can('viewAny', App\Models\Customer::class)<a href="{{ route('customers.index') }}" class="hover:text-gray-700">Customers</a><span>/</span>@endcan<span class="text-gray-900">{{ $customer->full_name }}</span>
    </x-slot:breadcrumbs>

    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <x-customer-avatar :customer="$customer" size="lg" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $customer->full_name }}</h1>
                <p class="text-sm text-gray-500">{{ collect([$customer->professional_title, $customer->company?->name])->filter()->implode(' · ') ?: 'No title yet' }}</p>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    <span class="{{ $statusBadges[$customer->status] ?? 'badge-gray' }}">{{ CustomerFields::STATUSES[$customer->status] ?? $customer->status }}</span>
                    @if ($customer->user_id)<span class="badge-brand"><x-lucide-key-round class="h-3 w-3" /> Has a login</span>@endif
                    @if ($customer->assignedDesigner)<span class="badge-gray"><x-lucide-pen-tool class="h-3 w-3" /> {{ $customer->assignedDesigner->name }}</span>@endif
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canCreateDesign)
                <a href="{{ route('designs.create', ['customer' => $customer->ulid]) }}" class="btn-primary"><x-lucide-pen-tool class="h-4 w-4" /> New card design</a>
            @endif
            @can('update', $customer)
                <a href="{{ route('customers.edit', $customer) }}" class="btn-secondary"><x-lucide-pencil class="h-4 w-4" /> Edit</a>
            @endcan
            @if (! $customer->user_id && $customer->email && auth()->user()->can('create', App\Models\Customer::class))
                <form method="POST" action="{{ route('customers.login', $customer) }}">@csrf
                    <button class="btn-secondary" title="Email {{ $customer->email }} a link to set a password"><x-lucide-key-round class="h-4 w-4" /> Create login</button>
                </form>
            @endif
            @can('delete', $customer)
                <form method="POST" action="{{ route('customers.destroy', $customer) }}" x-data @submit="if (! confirm('Delete {{ e($customer->full_name) }}? Designs and profiles are kept and the customer can be restored.')) $event.preventDefault()">
                    @csrf @method('DELETE')
                    <button class="btn-ghost text-red-600 hover:bg-red-50" aria-label="Delete customer"><x-lucide-trash-2 class="h-4 w-4" /></button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-6">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Card designs <span class="font-normal text-gray-400">({{ $designCount }})</span></h2>
                    @if ($designCount > 6 && Route::has('designs.index'))<a href="{{ route('designs.index', ['search' => $customer->full_name]) }}" class="text-xs font-semibold text-brand-600">View all</a>@endif
                </div>
                @if ($designs->isEmpty())
                    <x-empty-state icon="pen-tool" title="No designs yet" description="Create a card design for this customer; their details fill in automatically.">
                        @if ($canCreateDesign)<a href="{{ route('designs.create', ['customer' => $customer->ulid]) }}" class="btn-primary btn-sm">New card design</a>@endif
                    </x-empty-state>
                @else
                    <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($designs as $design)
                            @php
                                $w = min(100, round(63.1 * (float) $design->width_mm / max(1, (float) $design->height_mm), 1));
                                $editable = auth()->user()->can('update', $design);
                            @endphp
                            <{{ $editable ? 'a' : 'div' }} @if ($editable) href="{{ route('designs.edit', $design) }}" @endif class="group block">
                                <div class="mx-auto transition group-hover:-translate-y-0.5" style="width: {{ $w }}%"><x-card-preview :svg="$previews[$design->id]['front']" /></div>
                                <p class="mt-2 truncate text-sm font-medium text-gray-800 group-hover:text-brand-700">{{ $design->name }}</p>
                                <p class="text-xs text-gray-400">{{ ucfirst($design->status) }} · updated {{ $design->updated_at->diffForHumans() }}</p>
                            </{{ $editable ? 'a' : 'div' }}>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Digital profiles &amp; NFC cards</h2>
                    @can('createFor', [App\Models\DigitalProfile::class, $customer])
                        <a href="{{ route('profiles.create', ['customer' => $customer->ulid]) }}" class="btn-secondary btn-sm"><x-lucide-plus class="h-3.5 w-3.5" /> New profile</a>
                    @endcan
                </div>
                <div class="grid grid-cols-1 divide-y divide-gray-100 md:grid-cols-2 md:divide-x md:divide-y-0">
                    <div class="p-5">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Profiles</h3>
                        @forelse ($profiles as $profile)
                            <a href="{{ route('profiles.show', $profile) }}" class="-mx-2 flex items-center justify-between rounded-lg px-2 py-1.5 text-sm hover:bg-gray-50">
                                <span class="truncate font-mono text-xs text-gray-700">/card/{{ $profile->slug }}</span>
                                <span class="{{ $profile->is_active ? 'badge-green' : 'badge-gray' }}">{{ $profile->is_active ? 'Live' : 'Off' }}</span>
                            </a>
                        @empty
                            <p class="text-sm text-gray-400">No digital profile yet.</p>
                        @endforelse
                    </div>
                    <div class="p-5">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">NFC cards</h3>
                        @forelse ($nfc as $assignment)
                            <div class="flex items-center justify-between py-1.5 text-sm">
                                <span class="font-mono text-xs text-gray-700">{{ $assignment->nfcCard?->card_identifier ?? 'Card' }}</span>
                                <span class="{{ $assignment->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($assignment->status) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">No NFC card assigned.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            @can('orders.manage')
                @php $customerOrders = $customer->orders()->latest()->limit(10)->get(); @endphp
                <section class="card overflow-hidden">
                    <div class="card-header"><h2 class="card-title">Orders</h2><a href="{{ route('orders.create', ['customer' => $customer->ulid]) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">New order</a></div>
                    @forelse ($customerOrders as $o)
                        <a href="{{ route('orders.show', $o) }}" class="flex items-center justify-between border-b border-gray-100 px-5 py-3 text-sm last:border-0 hover:bg-gray-50">
                            <span class="font-mono font-medium text-gray-900">{{ $o->order_number }}</span>
                            <span class="text-gray-500">{{ $o->quantity }} cards · {{ Illuminate\Support\Number::currency((float) $o->total, $o->currency) }} · @include('orders._status', ['status' => $o->status])</span>
                        </a>
                    @empty
                        <p class="card-body text-sm text-gray-400">No orders yet.</p>
                    @endforelse
                </section>
            @endcan

            @if (! empty($customer->introduction) || ! empty($customer->business['description']) || ! empty($customer->business['services']) || ! empty($customer->custom_fields))
                <section class="card">
                    <div class="card-header"><h2 class="card-title">About</h2></div>
                    <div class="card-body space-y-4 text-sm text-gray-700">
                        @if ($customer->introduction)<p class="whitespace-pre-line">{{ $customer->introduction }}</p>@endif
                        @if (! empty($customer->business['description']))<p class="text-gray-500">{{ $customer->business['description'] }}</p>@endif
                        @if (! empty($customer->business['services']))
                            <div class="flex flex-wrap gap-1.5">@foreach ($customer->business['services'] as $service)<span class="badge-gray">{{ $service }}</span>@endforeach</div>
                        @endif
                        @if (! empty($customer->custom_fields))
                            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                                @foreach ($customer->custom_fields as $field)
                                    <div><dt class="text-xs text-gray-500">{{ $field['label'] }}</dt><dd>{{ $field['value'] ?: '—' }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </section>
            @endif

            @if ($activity->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Activity</h2></div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($activity as $log)
                            <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                                <span class="text-gray-700">{{ $log->description }}</span>
                                <span class="shrink-0 text-xs text-gray-400">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="card p-5">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="font-semibold text-gray-900">Profile completeness</span><span class="font-semibold text-brand-700">{{ $completeness }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $completeness }}%"></div></div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Contact</h2></div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($contacts as [$icon, $label, $value, $href])
                        <li class="flex items-start gap-3 px-5 py-3 text-sm">
                            <x-dynamic-component :component="'lucide-'.$icon" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                            <span class="min-w-0">
                                <span class="block text-xs text-gray-500">{{ $label }}</span>
                                @if ($href)<a href="{{ $href }}" class="break-words text-gray-800 hover:text-brand-700" @if (str_starts_with($href, 'http')) target="_blank" rel="noopener noreferrer" @endif>{{ $value }}</a>@else<span class="break-words text-gray-800">{{ $value }}</span>@endif
                            </span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-gray-400">No contact details yet.</li>
                    @endforelse
                </ul>
            </section>

            @if (! empty($customer->social_links) || ! empty($customer->custom_links) || $customer->portfolio_url || $customer->booking_url)
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Links</h2></div>
                    <ul class="space-y-1 p-3 text-sm">
                        @foreach ($customer->social_links ?? [] as $network => $url)
                            <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-gray-50"><span>{{ CustomerFields::SOCIAL[$network] ?? $network }}</span><x-lucide-external-link class="h-3.5 w-3.5 text-gray-400" /></a></li>
                        @endforeach
                        @foreach (array_filter(['Portfolio' => $customer->portfolio_url, 'Booking' => $customer->booking_url]) as $label => $url)
                            <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-gray-50"><span>{{ $label }}</span><x-lucide-external-link class="h-3.5 w-3.5 text-gray-400" /></a></li>
                        @endforeach
                        @foreach ($customer->custom_links ?? [] as $link)
                            <li><a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-gray-50"><span>{{ $link['label'] }}</span><x-lucide-external-link class="h-3.5 w-3.5 text-gray-400" /></a></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($customer->business['hours']))
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Opening hours</h2></div>
                    <dl class="space-y-1 p-5 text-sm">
                        @foreach (CustomerFields::DAYS as $day)
                            @if (! empty($customer->business['hours'][$day]))
                                <div class="flex justify-between"><dt class="capitalize text-gray-500">{{ $day }}</dt><dd class="text-gray-800">{{ $customer->business['hours'][$day] }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                </section>
            @endif

            @if ($hidden->isNotEmpty())
                <section class="card p-5 text-sm">
                    <h2 class="card-title mb-2">Hidden details</h2>
                    <ul class="space-y-1 text-gray-600">
                        @foreach ($hidden as $field => $label)
                            <li class="flex items-center justify-between">{{ $label }}
                                <span class="text-xs text-gray-400">
                                    {{ collect(['card' => 'card', 'profile' => 'profile'])->filter(fn ($c) => ! CustomerFields::visible($customer->visibility, $c, $field))->implode(', ') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <p class="px-1 text-xs text-gray-400">Added {{ $customer->created_at?->format('j M Y') }}{{ $customer->creator ? ' by '.$customer->creator->name : '' }}.@if ($customer->user?->last_login_at) Last sign-in {{ $customer->user->last_login_at->diffForHumans() }}.@endif</p>
        </aside>
    </div>
</x-layouts.app>
