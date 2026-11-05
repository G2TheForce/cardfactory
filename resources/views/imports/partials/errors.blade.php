@php $problems = collect($import->errors ?? []); @endphp
@if ($problems->isNotEmpty())
    <div class="card overflow-hidden" x-data="{ open: {{ $problems->count() <= 10 ? 'true' : 'false' }} }">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-gray-900">Problems found</h2>
                <p class="text-sm text-gray-500">{{ $problems->count() }} {{ Str::plural('problem', $problems->count()) }} in {{ $problems->pluck('line')->filter()->unique()->count() }} {{ Str::plural('row', $problems->pluck('line')->filter()->unique()->count()) }}. Row numbers match your spreadsheet.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary btn-sm" @click="open = ! open" x-text="open ? 'Hide' : 'Show'"></button>
                <a href="{{ route('imports.errors', $import) }}" class="btn-secondary btn-sm"><x-lucide-download class="h-4 w-4" /> Error report (CSV)</a>
            </div>
        </div>
        <div class="max-h-96 overflow-auto" x-show="open" x-cloak>
            <table class="table">
                <thead><tr><th class="w-20">Row</th><th>Column</th><th>Problem</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($problems->take(200) as $e)
                        <tr>
                            <td class="text-sm text-gray-500">{{ $e['line'] ?: '—' }}</td>
                            <td class="text-sm text-gray-700">{{ $e['column'] ?? (isset($e['field']) ? ($fields[$e['field']]['label'] ?? $e['field']) : '—') }}</td>
                            <td class="text-sm {{ ($e['level'] ?? 'error') === 'skipped' ? 'text-gray-600' : 'text-red-700' }}">{{ $e['message'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($problems->count() > 200)<p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">Showing the first 200. Download the error report for the full list.</p>@endif
        </div>
    </div>
@endif
