@php
    use App\Support\LandingPages\Templates;
@endphp
<x-layouts.app :title="$page->name.' · Landing page'">
    <x-slot:breadcrumbs>
        <a href="{{ route('landing-pages.index') }}" class="hover:text-gray-700">Landing pages</a><span>/</span><span class="text-gray-900">{{ $page->name }}</span>
    </x-slot:breadcrumbs>

    <x-page-header :title="$page->name" :description="($page->customer?->full_name ?? 'Deleted customer').' · '.(Templates::TEMPLATES[$page->template]['name'] ?? 'Custom').' template'">
        <x-slot:actions>
            <span class="{{ $page->is_published ? 'badge-green' : 'badge-gray' }}">{{ $page->is_published ? 'Live' : 'Draft' }}</span>
            <a href="{{ $available ? $url : route('landing-pages.preview', $page) }}" target="_blank" rel="noopener" class="btn-secondary"><x-lucide-external-link class="h-4 w-4" /> {{ $available ? 'Open' : 'Preview' }}</a>
            @if ($canEdit)
                <a href="{{ route('landing-pages.edit', $page) }}" class="btn-primary"><x-lucide-pencil class="h-4 w-4" /> Open designer</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @unless ($available)
        <div class="mb-6 flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            <x-lucide-circle-alert class="mt-0.5 h-4 w-4 shrink-0" />
            <p>Visitors see a "page not available" message because {{ ! $page->is_published ? 'this page is still a draft' : 'the customer is not active' }}.@if ($canEdit && ! $page->is_published) Publish it when it's ready.@endif</p>
        </div>
    @endunless

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach (['Views' => $page->views_count, 'Enquiries' => $leads->total(), 'New enquiries' => $newLeads, 'Sections' => $sectionCount] as $label => $value)
            <div class="card p-4">
                <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Enquiries --}}
            <section class="card" id="leads">
                <div class="card-header flex items-center justify-between gap-3">
                    <h2 class="card-title">Enquiries</h2>
                    @if ($canEdit && $newLeads)
                        <form method="POST" action="{{ route('landing-pages.leads.read', $page) }}">
                            @csrf
                            <button class="btn-ghost btn-sm"><x-lucide-check-check class="h-4 w-4" /> Mark all read</button>
                        </form>
                    @endif
                </div>
                @if ($leads->isEmpty())
                    <x-empty-state icon="inbox" title="No enquiries yet" description="Messages sent through the page's contact form appear here{{ $notifyAddress ? ' and are emailed to '.$notifyAddress : '' }}." />
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($leads as $lead)
                            <li class="flex gap-4 px-5 py-4">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $lead->read_at ? 'bg-gray-200' : 'bg-brand-500' }}" title="{{ $lead->read_at ? 'Read' : 'New' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <p class="font-medium text-gray-900">{{ $lead->name }}</p>
                                        <p class="text-xs text-gray-400" title="{{ $lead->created_at }}">{{ $lead->created_at->diffForHumans() }}</p>
                                    </div>
                                    <p class="mt-0.5 flex flex-wrap gap-x-4 text-sm">
                                        @if ($lead->email)<a href="mailto:{{ $lead->email }}" class="text-brand-600 hover:text-brand-700">{{ $lead->email }}</a>@endif
                                        @if ($lead->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->phone) }}" class="text-gray-700">{{ $lead->phone }}</a>@endif
                                    </p>
                                    @if ($lead->message)<p class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ $lead->message }}</p>@endif
                                </div>
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('landing-pages.leads.destroy', [$page, $lead]) }}" x-data @submit="if (! confirm('Delete this enquiry?')) $event.preventDefault()">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm text-gray-400 hover:text-red-600" title="Delete enquiry"><x-lucide-trash-2 class="h-4 w-4" /><span class="sr-only">Delete enquiry</span></button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($leads->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $leads->fragment('leads')->links() }}</div>@endif
                @endif
            </section>

            {{-- Publish as a website --}}
            @if ($canEdit)
                <section class="card" id="export">
                    <div class="card-header"><h2 class="card-title">Download as a website</h2></div>
                    <form method="POST" action="{{ route('landing-pages.site.export', $page) }}" class="space-y-4 p-5">
                        @csrf
                        <p class="text-sm text-gray-600">
                            Get this page as a ready-made website to upload to a free host such as
                            <a href="https://app.netlify.com/drop" target="_blank" rel="noopener" class="font-semibold text-brand-600 hover:text-brand-700">Netlify Drop</a>,
                            GitHub Pages, Cloudflare Pages or any web hosting. In the download, the enquiry form becomes an email button.
                        </p>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <label for="site_url" class="form-label">Site address <span class="font-normal text-gray-400">(optional)</span></label>
                                <input id="site_url" name="site_url" type="url" value="{{ old('site_url') }}" placeholder="https://{{ $page->slug }}.netlify.app" class="input-base">
                                @error('site_url')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <button class="btn-secondary"><x-lucide-download class="h-4 w-4" /> Download ZIP</button>
                        </div>
                    </form>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Publishing</h2></div>
                <div class="space-y-4 p-5 text-sm">
                    <div x-data="copyToClipboard(@js($url))">
                        <p class="text-xs font-medium text-gray-500">Public address</p>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="min-w-0 flex-1 truncate font-mono text-xs text-gray-800">{{ $url }}</span>
                            <button type="button" class="btn-secondary btn-sm" @click="copy()"><x-lucide-copy class="h-3.5 w-3.5" /> <span x-text="copied ? 'Copied' : 'Copy'">Copy</span></button>
                        </div>
                    </div>
                    @if ($page->published_at)<p class="flex justify-between"><span class="text-gray-500">First published</span><span class="text-gray-900">{{ $page->published_at->toFormattedDateString() }}</span></p>@endif
                    <p class="flex justify-between"><span class="text-gray-500">Last edited</span><span class="text-gray-900">{{ $page->updated_at->diffForHumans() }}</span></p>
                    @if ($page->brandKit)<p class="flex justify-between"><span class="text-gray-500">Brand kit</span><span class="text-gray-900">{{ $page->brandKit->name }}</span></p>@endif
                    @if ($canEdit)
                        <form method="POST" action="{{ route('landing-pages.publish', $page) }}">
                            @csrf
                            <input type="hidden" name="published" value="{{ $page->is_published ? 0 : 1 }}">
                            @if ($page->is_published)
                                <button class="btn-secondary w-full"><x-lucide-eye-off class="h-4 w-4" /> Unpublish</button>
                            @else
                                <button class="btn-primary w-full"><x-lucide-rocket class="h-4 w-4" /> Publish now</button>
                            @endif
                        </form>
                        <form method="POST" action="{{ route('landing-pages.destroy', $page) }}" class="border-t border-gray-100 pt-3" x-data @submit="if (! confirm('Delete this landing page? Its address will stop working.')) $event.preventDefault()">
                            @csrf @method('DELETE')
                            <button class="btn-ghost btn-sm -ml-2 text-red-600 hover:bg-red-50 hover:text-red-700"><x-lucide-trash-2 class="h-4 w-4" /> Delete landing page</button>
                        </form>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-layouts.app>
