<x-layouts.app title="API tokens">
    <x-page-header title="Settings" description="Personal access tokens let other systems use the SmartCard Studio REST API on your behalf." />
    @include('settings._tabs')

    <div class="max-w-3xl space-y-6">
        @if ($plainTextToken)
            <div class="card border-amber-200 bg-amber-50/60 p-5" x-data="copyToClipboard(@js($plainTextToken))">
                <p class="text-sm font-semibold text-amber-900">Your new token</p>
                <p class="mt-1 text-xs text-amber-800">Copy it now. For security it will never be shown again.</p>
                <div class="mt-3 flex gap-2">
                    <code class="flex-1 overflow-x-auto rounded-lg bg-white px-3 py-2 font-mono text-xs text-gray-800 ring-1 ring-amber-200">{{ $plainTextToken }}</code>
                    <button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? 'Copied' : 'Copy'"></button>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.tokens.store') }}" class="card">
            @csrf
            <div class="card-header"><h2 class="card-title">Create token</h2></div>
            <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-field name="name" label="Token name" placeholder="e.g. CRM integration" required />
                <div>
                    <label for="expires_in_days" class="form-label">Expires</label>
                    <select id="expires_in_days" name="expires_in_days" class="select-base">
                        <option value="30">In 30 days</option>
                        <option value="90" selected>In 90 days</option>
                        <option value="365">In 1 year</option>
                        <option value="">Never</option>
                    </select>
                </div>
                <fieldset class="sm:col-span-2">
                    <legend class="form-label">Abilities</legend>
                    <div class="space-y-2">
                        @foreach ($abilities as $key => $description)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="abilities[]" value="{{ $key }}" class="checkbox-base" @checked(in_array($key, old('abilities', ['read']), true))>
                                <span class="font-mono text-xs">{{ $key }}</span> <span class="text-gray-500">{{ $description }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('abilities')<p class="form-error">{{ $message }}</p>@enderror
                </fieldset>
            </div>
            <div class="flex justify-end border-t border-gray-100 px-5 py-4"><button class="btn-primary">Create token</button></div>
        </form>

        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Active tokens</h2></div>
            @forelse ($tokens as $token)
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-3 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $token->name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ implode(', ', $token->abilities) }} ·
                            Last used {{ $token->last_used_at?->diffForHumans() ?? 'never' }} ·
                            {{ $token->expires_at ? ($token->expires_at->isPast() ? 'Expired' : 'Expires '.$token->expires_at->toFormattedDateString()) : 'No expiry' }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('settings.tokens.destroy', $token->id) }}">
                        @csrf @method('DELETE')
                        <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Revoke</button>
                    </form>
                </div>
            @empty
                <x-empty-state icon="key-round" title="No API tokens" description="Create a token to call the REST API at /api/v1." />
            @endforelse
        </div>
    </div>
</x-layouts.app>
