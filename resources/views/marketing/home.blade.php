@php
    $features = [
        ['pen-tool', 'Canvas design studio', 'Drag, resize, rotate and layer every element on the front and back of the card, with undo, redo and Google Fonts.'],
        ['palette', '20+ editable themes', 'Professional themes for every industry, stored as editable layouts rather than flat images.'],
        ['smartphone', 'Digital business profiles', 'A mobile-first profile page for every card with call, WhatsApp, email and one-tap "Save contact".'],
        ['nfc', 'NFC card management', 'Track tag UIDs, assign, reassign and replace lost cards without reprinting the profile link.'],
        ['printer', 'Print-ready exports', '300 DPI PNG, vector SVG and PDF with bleed, safe area and crop marks for professional printers.'],
        ['layers', 'Bulk generation', 'Import a CSV or Excel file and generate hundreds of cards, QR codes and profiles at once.'],
    ];
    $steps = [
        ['Add your details', 'Enter contact, company and social information once.'],
        ['Pick a theme', 'Start from a professional theme or a blank canvas.'],
        ['Customise in the studio', 'Edit every element on the front and back.'],
        ['Print, tap and share', 'Download print files, write the NFC link and share your QR.'],
    ];
    $testimonials = [
        ['Our sales team switched to NFC cards in one afternoon. The bulk import alone saved us days.', 'Operations lead', 'Logistics company'],
        ['The print PDFs come out with proper bleed and crop marks. Our printer had zero questions.', 'Studio owner', 'Print shop'],
        ['I love that I can update my profile without reprinting. Clients just tap my card.', 'Independent consultant', 'Freelancer'],
    ];
    $faqs = [
        ['Do I need a special phone to use NFC cards?', 'Almost every modern iPhone and Android phone can read NFC tags. People without NFC can scan the QR code on the card instead.'],
        ['Can the browser write to my NFC card?', 'No. Browsers cannot reliably program physical tags. SmartCard Studio gives you the exact profile URL to copy into an NFC writing app or hardware encoder, plus a QR code.'],
        ['What happens if I lose my card?', 'Mark it as lost and assign a replacement. Your profile URL stays the same, and the old card stops pointing to your profile.'],
        ['Are the exports really print quality?', 'Yes. Exports are rendered from physical measurements at 300 DPI with optional bleed and crop marks, not screenshots of the editor.'],
        ['Is visitor data private?', 'Analytics are aggregated. We do not store visitor IP addresses, and unique visitors are counted with a daily rotating anonymous hash.'],
    ];
    $showcase = ['midnight' => 'Corporate Executive', 'gold' => 'Luxury Black & Gold', 'aurora' => 'Modern Gradient', 'pearl' => 'Elegant White', 'ocean' => 'Technology', 'sage' => 'Medical Professional'];
@endphp
<x-layouts.marketing>
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(60%_50%_at_70%_0%,rgba(99,102,241,0.15),transparent)]"></div>
        <div class="mx-auto grid max-w-7xl items-center gap-16 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-28">
            <div>
                <span class="badge-brand"><x-lucide-sparkles class="h-3.5 w-3.5" /> NFC + QR smart business cards</span>
                <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl lg:text-6xl">Design cards people <span class="bg-gradient-to-r from-brand-600 to-sky-500 bg-clip-text text-transparent">tap, scan and keep.</span></h1>
                <p class="mt-6 max-w-xl text-lg text-gray-600">SmartCard Studio combines a professional canvas designer with digital profiles, QR codes and NFC card management, so every printed card leads to a living, always up-to-date profile.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="btn-primary btn-lg">Start designing free <x-lucide-arrow-right class="h-4 w-4" /></a>
                    <a href="#how" class="btn-secondary btn-lg">See how it works</a>
                </div>
                <p class="mt-6 text-sm text-gray-500">No credit card required · Print-ready at 300 DPI</p>
            </div>
            <div class="relative mx-auto w-full max-w-lg">
                <x-sample-card variant="aurora" class="-rotate-6" />
                <x-sample-card variant="pearl" name="Priya Shah" title="Principal Architect" company="Lumen Atelier" class="-mt-16 ml-12 rotate-3" />
            </div>
        </div>
    </section>

    {{-- Product introduction --}}
    <section class="border-y border-gray-100 bg-gray-50/60">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 text-center sm:grid-cols-3 sm:px-6 lg:px-8">
            <div><p class="text-3xl font-bold text-gray-900">85.6 × 54 mm</p><p class="mt-1 text-sm text-gray-500">True physical card dimensions</p></div>
            <div><p class="text-3xl font-bold text-gray-900">300 DPI</p><p class="mt-1 text-sm text-gray-500">Print-ready raster and vector output</p></div>
            <div><p class="text-3xl font-bold text-gray-900">NTAG213 / 215 / 216</p><p class="mt-1 text-sm text-gray-500">NFC tag tracking out of the box</p></div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-24 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Everything from design to tap</h2>
            <p class="mt-4 text-lg text-gray-600">One workspace for designers, sales teams and the people carrying the cards.</p>
        </div>
        <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as [$icon, $title, $text])
                <div class="card p-6 transition hover:-translate-y-1 hover:shadow-lift">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-dynamic-component :component="'lucide-'.$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-5 text-base font-semibold text-gray-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Theme gallery --}}
    <section id="themes" class="scroll-mt-20 bg-gray-950 py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Themes for every profession</h2>
                <p class="mt-4 text-lg text-gray-400">Corporate, luxury, creative, medical, legal, hospitality and more. Every theme is fully editable.</p>
            </div>
            <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @if ($themes->isNotEmpty())
                    @foreach ($themes as $theme)
                        <figure>
                            @if ($theme->thumbnail_path)
                                <img src="{{ Storage::disk('public')->url($theme->thumbnail_path) }}" alt="{{ $theme->name }} theme preview" class="aspect-[85.6/54] w-full rounded-2xl object-cover shadow-2xl" loading="lazy">
                            @else
                                <x-sample-card :variant="array_keys($showcase)[$loop->index % 6]" />
                            @endif
                            <figcaption class="mt-3 text-sm font-medium text-gray-300">{{ $theme->name }} <span class="text-gray-500">· {{ $theme->category }}</span></figcaption>
                        </figure>
                    @endforeach
                @else
                    @foreach ($showcase as $variant => $label)
                        <figure>
                            <x-sample-card :variant="$variant" />
                            <figcaption class="mt-3 text-sm font-medium text-gray-300">{{ $label }}</figcaption>
                        </figure>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-24 sm:px-6 lg:px-8">
        <h2 class="text-center text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">How it works</h2>
        <ol class="mt-16 grid gap-6 md:grid-cols-4">
            @foreach ($steps as [$title, $text])
                <li class="card relative p-6">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ $loop->iteration }}</span>
                    <h3 class="mt-4 font-semibold text-gray-900">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm text-gray-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="scroll-mt-20 bg-gray-50 py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Simple pricing</h2>
            <p class="mt-4 text-center text-lg text-gray-600">Start free. Upgrade when your team grows.</p>
            <div class="mx-auto mt-16 grid max-w-5xl gap-6 lg:grid-cols-3">
                @foreach ($plans as $key => $plan)
                    <div @class(['card flex flex-col p-8', 'ring-2 ring-brand-600 shadow-lift' => $plan['featured'] ?? false])>
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $plan['name'] }}</h3>
                            @if ($plan['featured'] ?? false)<span class="badge-brand">Most popular</span>@endif
                        </div>
                        <p class="mt-2 text-sm text-gray-500">{{ $plan['description'] }}</p>
                        <p class="mt-6"><span class="text-4xl font-extrabold text-gray-900">${{ $plan['price'] }}</span><span class="text-sm text-gray-500">/{{ $plan['interval'] }}</span></p>
                        <ul class="mt-6 flex-1 space-y-3 text-sm text-gray-700">
                            @foreach ($plan['features'] as $feature)
                                <li class="flex gap-2"><x-lucide-circle-check class="h-5 w-5 shrink-0 text-brand-600" /> {{ $feature }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('register', ['plan' => $key]) }}" @class(['mt-8 w-full', 'btn-primary' => $plan['featured'] ?? false, 'btn-secondary' => ! ($plan['featured'] ?? false)])>Get started</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    <section class="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8">
        <h2 class="text-center text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Loved by teams that hand out cards</h2>
        <div class="mt-16 grid gap-6 md:grid-cols-3">
            @foreach ($testimonials as [$quote, $role, $org])
                <figure class="card p-6">
                    <blockquote class="text-gray-700">&ldquo;{{ $quote }}&rdquo;</blockquote>
                    <figcaption class="mt-6 text-sm"><span class="font-semibold text-gray-900">{{ $role }}</span><span class="text-gray-500"> · {{ $org }}</span></figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="scroll-mt-20 bg-gray-50 py-24">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Frequently asked questions</h2>
            <div class="mt-12 divide-y divide-gray-200 rounded-2xl border border-gray-200 bg-white" x-data="{ open: 0 }">
                @foreach ($faqs as [$q, $a])
                    <div>
                        <button type="button" class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left" @click="open = open === {{ $loop->index }} ? null : {{ $loop->index }}" :aria-expanded="open === {{ $loop->index }}">
                            <span class="font-medium text-gray-900">{{ $q }}</span>
                            <x-lucide-chevron-down class="h-5 w-5 shrink-0 text-gray-400 transition" ::class="open === {{ $loop->index }} && 'rotate-180'" />
                        </button>
                        <div x-show="open === {{ $loop->index }}" x-collapse x-cloak class="px-6 pb-5 text-sm leading-6 text-gray-600">{{ $a }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Contact --}}
    <section id="contact" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-24 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Talk to us</h2>
                <p class="mt-4 text-lg text-gray-600">Ordering cards for a team, running a print shop, or need a custom theme? Send us a message.</p>
            </div>
            <form method="POST" action="{{ route('contact.store') }}" class="card space-y-5 p-6">
                @csrf
                @if (session('contact_sent'))
                    <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('contact_sent') }}</div>
                @endif
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="name" label="Name" required />
                    <x-field name="email" type="email" label="Email" required />
                </div>
                <x-field name="company" label="Company" />
                <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                <div>
                    <label for="message" class="form-label">Message <span class="text-red-500">*</span></label>
                    <textarea id="message" name="message" rows="4" class="input-base" required minlength="10">{{ old('message') }}</textarea>
                    @error('message')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary w-full">Send message</button>
            </form>
        </div>
    </section>
</x-layouts.marketing>
