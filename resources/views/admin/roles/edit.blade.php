@php $label = config("smartcard.roles.{$role->name}.label", $role->name); @endphp
<x-layouts.app :title="'Edit '.$label">
    <x-slot:breadcrumbs>
        <a href="{{ route('admin.roles.index') }}" class="hover:text-gray-700">Roles</a><span>/</span><span class="text-gray-900">{{ $label }}</span>
    </x-slot:breadcrumbs>
    <x-page-header :title="$label.' permissions'" description="Changes apply immediately to every user with this role." />
    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="max-w-3xl">
        @csrf @method('PUT')
        <div class="card divide-y divide-gray-100">
            @foreach ($permissions as $permission => $description)
                <label class="flex cursor-pointer items-start gap-3 px-5 py-3.5 hover:bg-gray-50">
                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" class="checkbox-base mt-0.5"
                           @checked(in_array($permission, old('permissions', $role->permissions->pluck('name')->all()), true))>
                    <span><span class="block text-sm font-medium text-gray-900">{{ $permission }}</span><span class="block text-xs text-gray-500">{{ $description }}</span></span>
                </label>
            @endforeach
        </div>
        @error('permissions.*')<p class="form-error">{{ $message }}</p>@enderror
        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('admin.roles.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Save permissions</button>
        </div>
    </form>
</x-layouts.app>
