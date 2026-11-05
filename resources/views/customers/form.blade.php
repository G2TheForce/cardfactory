@php
    use App\Support\CustomerFields;
    $editing = $customer->exists;
    $staff = auth()->user()->can('customers.manage');
    $self = $editing && $customer->user_id === auth()->id();
    $customFields = old('custom_fields', $customer->custom_fields ?? []);
    $customLinks = old('custom_links', $customer->custom_links ?? []);
    $services = old('business.services', implode("\n", $customer->business['services'] ?? []));
    if (is_array($services)) { $services = implode("\n", $services); }
    $title = $self ? 'My card details' : ($editing ? 'Edit '.$customer->full_name : 'Add a customer');
@endphp
<x-layouts.app :title="$title">
    <x-slot:breadcrumbs>
        @if ($self && ! $staff)
            <span class="text-gray-900">My card details</span>
        @else
            <a href="{{ route('customers.index') }}" class="hover:text-gray-700">Customers</a><span>/</span>
            @if ($editing)<a href="{{ route('customers.show', $customer) }}" class="hover:text-gray-700">{{ $customer->full_name }}</a><span>/</span><span class="text-gray-900">Edit</span>@else<span class="text-gray-900">New customer</span>@endif
        @endif
    </x-slot:breadcrumbs>

    <x-page-header :title="$title" :description="$self ? 'These details fill in your card designs and your digital profile.' : 'Details used on printed cards and digital profiles. Only the name is required.'" />

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            Please fix {{ $errors->count() === 1 ? 'the highlighted field' : 'the '.$errors->count().' highlighted fields' }} below.
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_320px]">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6">
            {{-- Personal --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Personal</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="flex items-center gap-4 sm:col-span-2" x-data="{ preview: null, remove: false }">
                        <div class="relative">
                            <template x-if="preview"><img :src="preview" alt="" class="h-20 w-20 rounded-full object-cover"></template>
                            <div x-show="!preview" :class="remove && 'opacity-30'"><x-customer-avatar :customer="$customer->exists ? $customer : new App\Models\Customer(['full_name' => '?'])" size="lg" /></div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="btn-secondary btn-sm cursor-pointer"><x-lucide-camera class="h-3.5 w-3.5" /> {{ $customer->profile_photo_path ? 'Change photo' : 'Upload photo' }}
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; remove = false">
                            </label>
                            @if ($customer->profile_photo_path)
                                <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_photo" value="1" class="checkbox-base" x-model="remove"> Remove photo</label>
                            @endif
                            <p class="form-hint mt-0">JPG, PNG or WebP, at least 100 × 100 px, up to 5 MB.</p>
                            @error('photo')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <x-field name="full_name" label="Full name" :value="$customer->full_name" required maxlength="120" class="sm:col-span-2" />
                    <x-field name="professional_title" label="Professional title" :value="$customer->professional_title" placeholder="e.g. Senior Architect" />
                    <x-field name="designation" label="Designation" :value="$customer->designation" placeholder="e.g. Partner" />
                    <x-field name="department" label="Department" :value="$customer->department" />
                </div>
            </section>

            {{-- Contact --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Contact</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field name="mobile" type="tel" label="Mobile" :value="$customer->mobile" placeholder="+1 555 010 2000" />
                    <x-field name="whatsapp" type="tel" label="WhatsApp" :value="$customer->whatsapp" hint="Leave empty to use the mobile number." />
                    <x-field name="office_phone" type="tel" label="Office phone" :value="$customer->office_phone" />
                    <x-field name="email" type="email" label="Email" :value="$customer->email" />
                    <x-field name="website" type="url" label="Website" :value="$customer->website" placeholder="https://" class="sm:col-span-2" />
                </div>
            </section>

            {{-- Company --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Company</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field name="company_name" label="Company name" :value="$customer->company?->name" maxlength="120" :hint="$staff ? 'Customers with the same company name share one company record and logo.' : null" />
                    <x-field name="company_website" type="url" label="Company website" :value="$customer->company?->website" placeholder="https://" />
                    <div class="sm:col-span-2" x-data="{ preview: null }">
                        <span class="form-label">Company logo</span>
                        <div class="flex items-center gap-4">
                            <div class="grid h-16 w-28 place-items-center overflow-hidden rounded-lg border border-gray-200 bg-[conic-gradient(#f3f4f6_25%,#fff_0_50%,#f3f4f6_0_75%,#fff_0)] [background-size:12px_12px]">
                                <template x-if="preview"><img :src="preview" alt="" class="max-h-full max-w-full object-contain"></template>
                                @if ($customer->company?->logo_path)
                                    <img x-show="!preview" src="{{ Storage::disk('public')->url($customer->company->logo_path) }}" alt="{{ $customer->company->name }} logo" class="max-h-full max-w-full object-contain">
                                @else
                                    <x-lucide-building-2 x-show="!preview" class="h-6 w-6 text-gray-300" />
                                @endif
                            </div>
                            <div class="space-y-1.5">
                                <label class="btn-secondary btn-sm cursor-pointer"><x-lucide-upload class="h-3.5 w-3.5" /> Upload logo
                                    <input type="file" name="company_logo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                                </label>
                                @if ($customer->company?->logo_path)
                                    <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_company_logo" value="1" class="checkbox-base"> Remove logo</label>
                                @endif
                                <p class="form-hint mt-0">A PNG with a transparent background looks best on cards.</p>
                                @error('company_logo')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Address --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Address</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-field name="office_address" label="Office address" :value="$customer->office_address" class="sm:col-span-3" />
                    <x-field name="city" label="City" :value="$customer->city" />
                    <x-field name="province" label="Province / state" :value="$customer->province" />
                    <x-field name="country" label="Country" :value="$customer->country" />
                    <x-field name="map_url" type="url" label="Map link" :value="$customer->map_url" placeholder="https://maps.google.com/…" class="sm:col-span-3" />
                    <x-field name="latitude" type="number" step="any" label="Latitude" :value="$customer->latitude" />
                    <x-field name="longitude" type="number" step="any" label="Longitude" :value="$customer->longitude" />
                </div>
            </section>

            {{-- Social --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Social links</h2></div>
                <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @foreach (CustomerFields::SOCIAL as $key => $label)
                        <x-field :name="'social_links['.$key.']'" type="url" :label="$label" :value="$customer->social_links[$key] ?? null" placeholder="https://" />
                    @endforeach
                </div>
            </section>

            {{-- Business --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Business</h2></div>
                <div class="card-body space-y-5">
                    <div>
                        <label for="business_description" class="form-label">Business description</label>
                        <textarea id="business_description" name="business[description]" rows="2" maxlength="1000" class="input-base">{{ old('business.description', $customer->business['description'] ?? '') }}</textarea>
                        @error('business.description')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="business_services" class="form-label">Services</label>
                        <textarea id="business_services" name="business[services]" rows="3" class="input-base" placeholder="One per line">{{ $services }}</textarea>
                        <p class="form-hint">One service per line, up to 20.</p>
                        @error('business.services')<p class="form-error">{{ $message }}</p>@enderror
                        @error('business.services.*')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <fieldset>
                        <legend class="form-label">Opening hours</legend>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach (CustomerFields::DAYS as $day)
                                <label class="flex items-center gap-3">
                                    <span class="w-24 text-sm capitalize text-gray-600">{{ $day }}</span>
                                    <input name="business[hours][{{ $day }}]" value="{{ old('business.hours.'.$day, $customer->business['hours'][$day] ?? '') }}" maxlength="60" class="input-base" placeholder="{{ in_array($day, ['saturday', 'sunday']) ? 'Closed' : '09:00 – 17:00' }}">
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </section>

            {{-- Profile extras --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Digital profile extras</h2></div>
                <div class="card-body space-y-5">
                    <div>
                        <label for="introduction" class="form-label">Introduction</label>
                        <textarea id="introduction" name="introduction" rows="3" maxlength="2000" class="input-base" placeholder="A short paragraph about you or your work">{{ old('introduction', $customer->introduction) }}</textarea>
                        @error('introduction')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-field name="portfolio_url" type="url" label="Portfolio link" :value="$customer->portfolio_url" placeholder="https://" />
                        <x-field name="booking_url" type="url" label="Booking / appointment link" :value="$customer->booking_url" placeholder="https://" />
                    </div>

                    <div x-data="{ rows: @js(array_values($customFields)) }">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="form-label mb-0">Custom fields</span>
                            <button type="button" class="btn-ghost btn-sm" @click="rows.push({ label: '', value: '' })" x-show="rows.length < {{ CustomerFields::MAX_CUSTOM }}"><x-lucide-plus class="h-3.5 w-3.5" /> Add field</button>
                        </div>
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="mb-2 flex gap-2">
                                <input :name="`custom_fields[${i}][label]`" x-model="row.label" maxlength="60" class="input-base w-40" placeholder="Label" aria-label="Field label">
                                <input :name="`custom_fields[${i}][value]`" x-model="row.value" maxlength="255" class="input-base flex-1" placeholder="Value" aria-label="Field value">
                                <button type="button" class="btn-ghost px-2 text-gray-400 hover:text-red-600" @click="rows.splice(i, 1)" aria-label="Remove field"><x-lucide-x class="h-4 w-4" /></button>
                            </div>
                        </template>
                        <p class="text-xs text-gray-400" x-show="!rows.length">e.g. License number, Languages, Pronouns.</p>
                        @foreach ($errors->get('custom_fields.*') as $messages)<p class="form-error">{{ $messages[0] }}</p>@endforeach
                    </div>

                    <div x-data="{ rows: @js(array_values($customLinks)) }">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="form-label mb-0">Custom links</span>
                            <button type="button" class="btn-ghost btn-sm" @click="rows.push({ label: '', url: '' })" x-show="rows.length < {{ CustomerFields::MAX_CUSTOM }}"><x-lucide-plus class="h-3.5 w-3.5" /> Add link</button>
                        </div>
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="mb-2 flex gap-2">
                                <input :name="`custom_links[${i}][label]`" x-model="row.label" maxlength="60" class="input-base w-40" placeholder="Label" aria-label="Link label">
                                <input :name="`custom_links[${i}][url]`" x-model="row.url" type="url" maxlength="255" class="input-base flex-1" placeholder="https://" aria-label="Link URL">
                                <button type="button" class="btn-ghost px-2 text-gray-400 hover:text-red-600" @click="rows.splice(i, 1)" aria-label="Remove link"><x-lucide-x class="h-4 w-4" /></button>
                            </div>
                        </template>
                        @foreach ($errors->get('custom_links.*') as $messages)<p class="form-error">{{ $messages[0] }}</p>@endforeach
                    </div>
                </div>
            </section>

            {{-- Visibility --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">What to show</h2></div>
                <div class="card-body">
                    <p class="mb-4 text-sm text-gray-500">Switch off details you don't want on the printed card or the public digital profile. The name is always shown.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><th class="py-2">Detail</th><th class="w-28 py-2 text-center">Printed card</th><th class="w-28 py-2 text-center">Digital profile</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach (CustomerFields::VISIBLE_FIELDS as $field => $label)
                                    <tr>
                                        <td class="py-2 text-gray-700">{{ $label }}</td>
                                        @foreach (['card', 'profile'] as $channel)
                                            <td class="py-2 text-center">
                                                <input type="hidden" name="visibility[{{ $channel }}][{{ $field }}]" value="0">
                                                <input type="checkbox" name="visibility[{{ $channel }}][{{ $field }}]" value="1" class="checkbox-base" aria-label="Show {{ strtolower($label) }} on {{ $channel === 'card' ? 'printed card' : 'digital profile' }}"
                                                       @checked(old("visibility.$channel.$field", $visibility[$channel][$field] ?? true))>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:h-fit">
            @if ($staff)
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Account</h2></div>
                    <div class="card-body space-y-5">
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="select-base">
                                @foreach (CustomerFields::STATUSES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status', $customer->status) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="assigned_designer" class="form-label">Assigned designer</label>
                            <select id="assigned_designer" name="assigned_designer" class="select-base">
                                <option value="">Nobody</option>
                                @foreach ($designers as $designer)
                                    <option value="{{ $designer->ulid }}" @selected(old('assigned_designer', $customer->assignedDesigner?->ulid) === $designer->ulid)>{{ $designer->name }}</option>
                                @endforeach
                            </select>
                            <p class="form-hint">The designer can see this customer and edit their designs.</p>
                            @error('assigned_designer')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        @unless ($customer->user_id)
                            <label class="flex items-start gap-3 text-sm text-gray-700">
                                <input type="hidden" name="create_login" value="0">
                                <input type="checkbox" name="create_login" value="1" class="checkbox-base mt-0.5" @checked(old('create_login'))>
                                <span>Create a login for this customer<span class="block text-xs text-gray-500">They get an email to set a password, then can edit their own details and designs.</span></span>
                            </label>
                            @error('create_login')<p class="form-error">{{ $message }}</p>@enderror
                        @endunless
                    </div>
                </section>
            @endif
            <div class="card p-5">
                <button class="btn-primary w-full"><x-lucide-save class="h-4 w-4" /> {{ $editing ? 'Save changes' : 'Create customer' }}</button>
                <a href="{{ $editing && ! ($self && ! $staff) ? route('customers.show', $customer) : ($staff ? route('customers.index') : route('dashboard')) }}" class="btn-ghost mt-2 w-full">Cancel</a>
            </div>
        </aside>
    </form>
</x-layouts.app>
