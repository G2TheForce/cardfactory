@php
    $badges = ['uploaded' => 'badge-gray', 'validated' => 'badge-brand', 'processing' => 'badge-amber', 'completed' => 'badge-green', 'failed' => 'badge-red'];
    $labels = ['uploaded' => 'Needs matching', 'validated' => 'Checked', 'processing' => 'Generating', 'completed' => 'Completed', 'failed' => 'Failed'];
@endphp
<x-layouts.app title="Bulk generator">
    <x-page-header title="Bulk generator" description="Create customers, card designs, digital profiles and QR codes from a spreadsheet, then download every card at once.">
        <x-slot:actions>
            <a href="{{ route('imports.create') }}" class="btn-primary"><x-lucide-upload class="h-4 w-4" /> New bulk run</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>File</th><th>Card source</th><th>Rows</th><th>Status</th><th>By</th><th>Uploaded</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($imports as $import)
                        <tr>
                            <td class="font-medium text-gray-900"><a href="{{ route('imports.show', $import) }}" class="hover:text-brand-700">{{ $import->original_filename }}</a></td>
                            <td class="text-sm text-gray-600">{{ $import->templateDesign?->name ?? $import->theme?->name ?? '—' }}</td>
                            <td class="text-sm text-gray-600">
                                {{ $import->total_rows }} rows
                                @if ($import->status === 'completed')<span class="block text-xs text-gray-500">{{ $import->generated_rows }} generated</span>
                                @elseif ($import->status === 'validated')<span class="block text-xs text-gray-500">{{ $import->valid_rows }} valid · {{ $import->failed_rows }} with problems</span>@endif
                            </td>
                            <td><span class="{{ $badges[$import->status] ?? 'badge-gray' }}">{{ $labels[$import->status] ?? ucfirst($import->status) }}</span></td>
                            <td class="text-sm text-gray-600">{{ $import->user?->name }}</td>
                            <td class="text-xs text-gray-500">{{ $import->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="layers" title="No bulk runs yet" description="Upload a CSV or Excel file with one customer per row to generate their cards in one go." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($imports->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $imports->links() }}</div>@endif
    </div>
</x-layouts.app>
