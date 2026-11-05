@php
    $badges = ['queued' => 'badge-gray', 'processing' => 'badge-amber', 'completed' => 'badge-green', 'failed' => 'badge-red', 'expired' => 'badge-gray'];
    $pending = $exports->getCollection()->whereIn('status', ['queued', 'processing'])->pluck('ulid')->values();
@endphp
<x-layouts.app title="Exports">
    <x-page-header title="Exports" :description="'Print and image files you have exported. Files are kept for '.config('smartcard.export.retention_days').' days.'">
        <x-slot:actions>
            @if ($canSeeAll)
                <a href="{{ route('exports.index', $showAll ? [] : ['all' => 1]) }}" class="btn-secondary">{{ $showAll ? 'Only mine' : 'Everyone\'s exports' }}</a>
            @endif
            <a href="{{ route('designs.index') }}" class="btn-primary"><x-lucide-pen-tool class="h-4 w-4" /> Designs</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden"
         x-data="{
             pending: @js($pending),
             rows: {},
             async poll() {
                 if (! this.pending.length) return;
                 const q = this.pending.map(id => 'ids[]=' + encodeURIComponent(id)).join('&');
                 try {
                     const r = await fetch(@js(route('exports.status')) + '?' + q, { headers: { Accept: 'application/json' } });
                     const data = await r.json();
                     Object.assign(this.rows, data);
                     this.pending = this.pending.filter(id => ! data[id] || ['queued', 'processing'].includes(data[id].status));
                 } catch (e) {}
                 if (this.pending.length) setTimeout(() => this.poll(), 2000);
             },
         }" x-init="setTimeout(() => poll(), 1500)">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Design</th><th>Format</th><th>Settings</th>@if ($showAll)<th>By</th>@endif<th>Status</th><th>Created</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($exports as $export)
                        @php $o = $export->options ?? []; @endphp
                        <tr>
                            <td class="font-medium text-gray-900">
                                @if ($export->cardDesign)<a href="{{ route('designs.export', $export->cardDesign) }}" class="hover:text-brand-700">{{ $export->cardDesign->name }}</a>@else<span class="text-gray-400">Deleted design</span>@endif
                            </td>
                            <td><span class="badge-brand">{{ strtoupper($export->format) }}</span> <span class="text-xs text-gray-500">{{ \App\Services\CardExportService::SIDES[$export->sides] ?? $export->sides }}</span></td>
                            <td class="text-xs text-gray-500">
                                {{ collect([
                                    $export->format === 'png' ? ($o['dpi'] ?? 300).' DPI' : null,
                                    'bleed '.($o['bleed_mm'] ?? 0).' mm',
                                    ! empty($o['crop_marks']) ? 'crop marks' : null,
                                    ! empty($o['cmyk']) ? 'CMYK' : null,
                                    ! empty($o['transparent']) ? 'transparent' : null,
                                ])->filter()->implode(' · ') }}
                            </td>
                            @if ($showAll)<td>{{ $export->user?->name }}</td>@endif
                            <td>
                                <template x-if="rows['{{ $export->ulid }}']">
                                    <span :class="{ 'badge-green': rows['{{ $export->ulid }}'].status === 'completed', 'badge-red': rows['{{ $export->ulid }}'].status === 'failed', 'badge-amber': ['queued','processing'].includes(rows['{{ $export->ulid }}'].status) }"
                                          x-text="rows['{{ $export->ulid }}'].status.charAt(0).toUpperCase() + rows['{{ $export->ulid }}'].status.slice(1)"></span>
                                </template>
                                <span x-show="! rows['{{ $export->ulid }}']" class="{{ $badges[$export->status] ?? 'badge-gray' }}">{{ ucfirst($export->status) }}</span>
                                @if ($export->status === 'failed' && $export->error)<p class="mt-1 max-w-xs whitespace-normal text-xs text-red-600">{{ $export->error }}</p>@endif
                                <p class="mt-1 max-w-xs whitespace-normal text-xs text-red-600" x-show="rows['{{ $export->ulid }}']?.status === 'failed'" x-text="rows['{{ $export->ulid }}']?.error"></p>
                            </td>
                            <td class="text-xs text-gray-500">{{ $export->created_at->diffForHumans() }}</td>
                            <td class="text-right">
                                <a x-show="rows['{{ $export->ulid }}']?.download" :href="rows['{{ $export->ulid }}']?.download" class="btn-primary btn-sm" x-cloak><x-lucide-download class="h-3.5 w-3.5" /> Download</a>
                                @if ($export->status === 'completed' && $export->file_path)
                                    <a href="{{ route('exports.download', $export) }}" class="btn-primary btn-sm"><x-lucide-download class="h-3.5 w-3.5" /> Download <span class="font-normal opacity-80">{{ Illuminate\Support\Number::fileSize($export->file_size ?? 0) }}</span></a>
                                @endif
                                <form method="POST" action="{{ route('exports.destroy', $export) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm" title="Delete" aria-label="Delete export"><x-lucide-trash-2 class="h-4 w-4" /></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="download" title="No exports yet" description="Open a design and choose Export to make print-ready PDF, PNG or SVG files." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($exports->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $exports->links() }}</div>@endif
    </div>
</x-layouts.app>
