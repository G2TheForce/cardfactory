@php use App\Support\ProfileThemes; @endphp
<x-layouts.app title="Digital profiles">
    <x-page-header title="Digital profiles" description="Public mobile business cards, each with its own link, QR code and NFC URL.">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('profiles.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New profile</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email, web address or card ID" class="input-base pl-9" aria-label="Search profiles">
            </div>
            <select name="status" class="select-base lg:w-36" aria-label="Status">
                <option value="">Any status</option>
                <option value="live" @selected(($filters['status'] ?? '') === 'live')>Live</option>
                <option value="off" @selected(($filters['status'] ?? '') === 'off')>Switched off</option>
            </select>
            <select name="theme" class="select-base lg:w-40" aria-label="Theme">
                <option value="">Any theme</option>
                @foreach (ProfileThemes::THEMES as $key => $theme)
                    <option value="{{ $key }}" @selected(($filters['theme'] ?? '') === $key)>{{ $theme['name'] }}</option>
                @endforeach
            </select>
            <button class="btn-secondary">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Profile</th><th>Web address</th><th>Theme</th><th>Views</th><th>NFC cards</th><th>Status</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($profiles as $profile)
                        @php $theme = ProfileThemes::get($profile->profile_theme); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('profiles.show', $profile) }}" class="flex items-center gap-3">
                                    @if ($profile->customer)<x-customer-avatar :customer="$profile->customer" size="sm" />@endif
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-900">{{ $profile->customer?->full_name ?? 'Deleted customer' }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ collect([$profile->customer?->professional_title, $profile->customer?->company?->name])->filter()->implode(' · ') }}</span>
                                    </span>
                                </a>
                            </td>
                            <td><span class="font-mono text-xs text-gray-700">/card/{{ $profile->slug }}</span><span class="block font-mono text-[11px] text-gray-400">{{ $profile->card_identifier }}</span></td>
                            <td><span class="inline-flex items-center gap-2 text-sm"><span class="h-3 w-3 rounded-full ring-1 ring-black/10" style="background: {{ $profile->settings['accent'] ?? $theme['accent'] }}"></span>{{ $theme['name'] }}</span></td>
                            <td class="tabular-nums">{{ number_format($profile->views_count) }}</td>
                            <td class="tabular-nums">{{ $profile->nfc_count }}</td>
                            <td><span class="{{ $profile->is_active ? 'badge-green' : 'badge-gray' }}">{{ $profile->is_active ? 'Live' : 'Off' }}</span></td>
                            <td class="text-right">
                                <a href="{{ route('profiles.public', $profile->slug) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm" title="Open public page"><x-lucide-external-link class="h-4 w-4" /><span class="sr-only">Open public page</span></a>
                                <a href="{{ route('profiles.show', $profile) }}" class="btn-secondary btn-sm">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">
                            <x-empty-state icon="smartphone" title="No digital profiles yet" description="A profile is a mobile page with contact buttons, a vCard download, a QR code and an NFC link.">
                                @if ($canCreate)<a href="{{ route('profiles.create') }}" class="btn-primary">Create a profile</a>@endif
                            </x-empty-state>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($profiles->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $profiles->links() }}</div>@endif
    </div>
</x-layouts.app>
