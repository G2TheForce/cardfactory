@php $editing = $user->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit user' : 'Add user'">
    <x-slot:breadcrumbs>
        <a href="{{ route('admin.users.index') }}" class="hover:text-gray-700">Users</a><span>/</span><span class="text-gray-900">{{ $editing ? $user->name : 'New user' }}</span>
    </x-slot:breadcrumbs>
    <x-page-header :title="$editing ? 'Edit '.$user->name : 'Add a user'" description="Roles control what each person can see and change." />

    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Account details</h2></div>
            <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-field name="name" label="Full name" :value="$user->name" required class="sm:col-span-2" />
                <x-field name="email" type="email" label="Email" :value="$user->email" required />
                <x-field name="phone" type="tel" label="Phone" :value="$user->phone" />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Role & access</h2></div>
            <div class="card-body space-y-5">
                <fieldset>
                    <legend class="form-label">Role <span class="text-red-500">*</span></legend>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($roles as $key => $label)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50">
                                <input type="radio" name="role" value="{{ $key }}" class="mt-0.5 h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old('role', $user->primaryRole() ?? 'customer') === $key)>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ $label }}</span>
                                    <span class="block text-xs text-gray-500">{{ count(config("smartcard.roles.$key.permissions")) === 1 && config("smartcard.roles.$key.permissions")[0] === '*' ? 'Full access to everything' : count(config("smartcard.roles.$key.permissions")).' permissions' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('role')<p class="form-error">{{ $message }}</p>@enderror
                </fieldset>
                <div>
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1" class="checkbox-base" @checked(old('is_active', $user->is_active))>
                        Account is active (inactive accounts cannot sign in or use API tokens)
                    </label>
                    @error('is_active')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ $editing ? 'Change password' : 'Password' }}</h2></div>
            <div class="card-body grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-field name="password" type="password" :label="$editing ? 'New password' : 'Password'" autocomplete="new-password" :required="! $editing" :hint="$editing ? 'Leave blank to keep the current password.' : null" />
                <x-field name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" :required="! $editing" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">{{ $editing ? 'Save changes' : 'Create user' }}</button>
        </div>
    </form>
</x-layouts.app>
