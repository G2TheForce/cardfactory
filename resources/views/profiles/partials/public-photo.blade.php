@if ($p['photo'])
    <img src="{{ $p['photo'] }}" alt="{{ $p['name'] }}" class="pf-avatar mx-auto {{ $size }} rounded-full object-cover shadow-lg {{ $ring }}" style="--tw-ring-color: var(--surface)">
@else
    <div class="pf-avatar mx-auto grid {{ $size }} place-items-center rounded-full text-4xl font-bold shadow-lg {{ $ring }}" style="background: var(--accent); color: var(--accent-text); --tw-ring-color: var(--surface)">{{ $p['initials'] }}</div>
@endif
