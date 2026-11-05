<x-layouts.guest title="Choose a new password" heading="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" type="email" label="Email address" :value="$request->email" autocomplete="email" required />
        <x-field name="password" type="password" label="New password" autocomplete="new-password" required />
        <x-field name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
        <button type="submit" class="btn-primary w-full py-2.5">Reset password</button>
    </form>
</x-layouts.guest>
