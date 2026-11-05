<x-layouts.guest title="Sign in" heading="Welcome back" subheading="Sign in to manage your cards, profiles and NFC tags.">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-field name="email" type="email" label="Email address" autocomplete="email" required autofocus />
        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="form-label">Password</label>
                <a href="{{ route('password.request') }}" class="mb-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700">Forgot password?</a>
            </div>
            <x-field name="password" type="password" autocomplete="current-password" required />
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="checkbox-base" @checked(old('remember'))> Keep me signed in
        </label>
        <button type="submit" class="btn-primary w-full py-2.5">Sign in</button>
    </form>
    <p class="mt-8 text-center text-sm text-gray-500">
        New to SmartCard Studio? <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">Create an account</a>
    </p>
</x-layouts.guest>
