<x-layouts.app title="Settings">
    <x-page-header title="Settings" description="Manage your personal details and password." />
    @include('settings._tabs')

    <div class="max-w-3xl space-y-6">
        <form method="POST" action="{{ route('user-profile-information.update') }}" enctype="multipart/form-data" class="card">
            @csrf @method('PUT')
            <div class="card-header">
                <h2 class="card-title">Profile</h2>
                @if (session('status') === 'profile-information-updated')<span class="badge-green">Saved</span>@endif
            </div>
            <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="flex items-center gap-4 sm:col-span-2" x-data="{ preview: null }">
                    <template x-if="preview"><img :src="preview" class="h-16 w-16 rounded-full object-cover" alt=""></template>
                    <div x-show="!preview"><x-avatar :user="$user" size="lg" /></div>
                    <div>
                        <label class="btn-secondary btn-sm cursor-pointer">
                            Change photo
                            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only"
                                   @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null">
                        </label>
                        <p class="form-hint">JPG, PNG or WebP, up to 2 MB.</p>
                        @error('avatar', 'updateProfileInformation')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <x-field name="name" label="Full name" :value="$user->name" bag="updateProfileInformation" required class="sm:col-span-2" />
                <x-field name="email" type="email" label="Email" :value="$user->email" bag="updateProfileInformation" required />
                <x-field name="phone" type="tel" label="Phone" :value="$user->phone" bag="updateProfileInformation" />
            </div>
            <div class="flex justify-end border-t border-gray-100 px-5 py-4"><button class="btn-primary">Save profile</button></div>
        </form>

        <form method="POST" action="{{ route('user-password.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header">
                <h2 class="card-title">Password</h2>
                @if (session('status') === 'password-updated')<span class="badge-green">Password changed</span>@endif
            </div>
            <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-field name="current_password" type="password" label="Current password" bag="updatePassword" autocomplete="current-password" required class="sm:col-span-2" />
                <x-field name="password" type="password" label="New password" bag="updatePassword" autocomplete="new-password" required />
                <x-field name="password_confirmation" type="password" label="Confirm new password" bag="updatePassword" autocomplete="new-password" required />
            </div>
            <div class="flex justify-end border-t border-gray-100 px-5 py-4"><button class="btn-primary">Update password</button></div>
        </form>
    </div>
</x-layouts.app>
