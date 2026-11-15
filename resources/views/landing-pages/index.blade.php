@php use App\Support\LandingPages\Templates; @endphp
<x-layouts.app title="Landing pages">
    <x-page-header title="Landing pages" description="Full marketing pages for your customers' businesses, built from sections and published at their own address.">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('landing-pages.create') }}" class="btn-primary"><x-lucide-plus class="h-4 w-4" /> New landing page</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search page, web address or customer" class="input-base pl-9" aria-label="Search landing pages">
            </div>
            <select name="status" class="select-base lg:w-36" aria-label="Status">
                <option value="">Any status</option>
                <option value="live" @selected(($filters['status'] ?? '') === 'live')>Live</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
            </select>
            <button class="btn-secondary">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Page</th><th>Web address</th><th>Template</th><th>Views</th><th>Enquiries</th><th>Status</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pages as $page)
                        <tr>
                            <td>
                                <a href="{{ route('landing-pages.show', $page) }}" class="flex items-center gap-3">
                                    <span class="h-9 w-9 shrink-0 rounded-lg ring-1 ring-black/10" style="background: linear-gradient(135deg, {{ $page->style['primary'] ?? '#1e293b' }}, {{ $page->style['accent'] ?? '#6366f1' }})"></span>
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-900">{{ $page->name }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ collect([$page->customer?->full_name, $page->customer?->company?->name])->filter()->unique()->implode(' · ') ?: 'Deleted customer' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td><span class="font-mono text-xs text-gray-700">/p/{{ $page->slug }}</span></td>
                            <td class="text-sm text-gray-700">{{ Templates::TEMPLATES[$page->template]['name'] ?? 'Custom' }}</td>
                            <td class="tabular-nums">{{ number_format($page->views_count) }}</td>
                            <td class="tabular-nums">{{ $page->leads_count }}@if ($page->new_leads_count) <span class="badge-amber ml-1">{{ $page->new_leads_count }} new</span>@endif</td>
                            <td><span class="{{ $page->is_published ? 'badge-green' : 'badge-gray' }}">{{ $page->is_published ? 'Live' : 'Draft' }}</span></td>
                            <td class="whitespace-nowrap text-right">
                                @if ($page->is_published)<a href="{{ route('landing-pages.public', $page->slug) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm" title="Open public page"><x-lucide-external-link class="h-4 w-4" /><span class="sr-only">Open public page</span></a>@endif
                                <a href="{{ route('landing-pages.show', $page) }}" class="btn-secondary btn-sm">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">
                            <x-empty-state icon="layout-template" title="No landing pages yet" description="Start from a template (café, real estate, consultant, salon, fitness or product launch), then edit the sections and publish.">
                                @if ($canCreate)<a href="{{ route('landing-pages.create') }}" class="btn-primary">Create a landing page</a>@endif
                            </x-empty-state>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($pages->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $pages->links() }}</div>@endif
    </div>
</x-layouts.app>
