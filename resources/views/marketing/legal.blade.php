<x-layouts.marketing :title="$page === 'terms' ? 'Terms of service' : 'Privacy policy'">
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        @if ($page === 'terms')
            <h1 class="text-3xl font-bold text-gray-900">Terms of service</h1>
            <div class="mt-8 space-y-4 text-gray-700">
                <p>By creating an account you agree to use {{ config('app.name') }} lawfully and to upload only content you have the right to use, including logos, photos and fonts.</p>
                <p>You are responsible for the accuracy of the contact information published on your digital profiles and printed on your cards.</p>
                <p>We may suspend accounts that publish unlawful, misleading or abusive content. You may close your account at any time.</p>
                <p class="text-sm text-gray-500">Replace this template with terms reviewed by your legal counsel before launch.</p>
            </div>
        @else
            <h1 class="text-3xl font-bold text-gray-900">Privacy policy</h1>
            <div class="mt-8 space-y-4 text-gray-700">
                <p>We store the account and card information you provide in order to design cards and publish digital profiles.</p>
                <p>Profile analytics record aggregated events (views, scans, clicks), country and device type. We do not store visitor IP addresses; unique visitors are estimated with a daily rotating anonymous hash.</p>
                <p>You can export or delete your data by contacting us.</p>
                <p class="text-sm text-gray-500">Replace this template with a policy reviewed by your legal counsel before launch.</p>
            </div>
        @endif
    </article>
</x-layouts.marketing>
