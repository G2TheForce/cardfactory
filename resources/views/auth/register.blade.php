<x-layouts.guest title="Create account" heading="Create your account" subheading="Start designing smart business cards in minutes.">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <x-field name="name" label="Full name" autocomplete="name" required autofocus />
        <x-field name="email" type="email" label="Work email" autocomplete="email" required />
        <x-field name="password" type="password" label="Password" autocomplete="new-password" required hint="At least 8 characters." />
        <x-field name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
        <div>
            <label class="flex items-start gap-2 text-sm text-gray-600">
                <input type="checkbox" name="terms" value="1" class="checkbox-base mt-0.5" @checked(old('terms')) required>
                <span>I agree to the <a href="{{ route('legal', 'terms') }}" class="font-medium text-brand-600 hover:underline" target="_blank">terms of service</a> and <a href="{{ route('legal', 'privacy') }}" class="font-medium text-brand-600 hover:underline" target="_blank">privacy policy</a>.</span>
            </label>
            @error('terms')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary w-full py-2.5">Create account</button>
    </form>
    <p class="mt-8 text-center text-sm text-gray-500">
        Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Sign in</a>
    </p>
</x-layouts.guest>
