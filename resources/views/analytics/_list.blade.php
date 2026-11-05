@php $max = max(1, max(array_column($rows, 'value') ?: [0])); @endphp
<div class="card">
    <div class="card-header"><h2 class="font-semibold text-gray-900">{{ $title }}</h2></div>
    <div class="card-body">
        @forelse ($rows as $row)
            <div class="mb-3 last:mb-0">
                <div class="flex justify-between text-sm"><span class="text-gray-700">{{ $labeler($row['label']) }}</span><span class="font-medium text-gray-900">{{ number_format($row['value']) }}</span></div>
                <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ round($row['value'] / $max * 100) }}%"></div></div>
            </div>
        @empty
            <p class="text-sm text-gray-500">{{ $empty }}</p>
        @endforelse
    </div>
</div>
