<x-layouts.guest title="Reset password" heading="Forgot your password?" subheading="Enter your email and we'll send you a secure reset link.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-field name="email" type="email" label="Email address" autocomplete="email" required autofocus />
        <button type="submit" class="btn-primary w-full py-2.5">Email reset link</button>
    </form>
    <p class="mt-8 text-center text-sm text-gray-500"><a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Back to sign in</a></p>
</x-layouts.guest>
