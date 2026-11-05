<x-layouts.app title="Roles & permissions">
    <x-page-header title="Roles & permissions" description="Role-based access control. Super Admin always has full access." />
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-gray-50">Permission</th>
                        @foreach ($roles as $role)
                            <th class="text-center">
                                <span class="block">{{ config("smartcard.roles.{$role->name}.label", $role->name) }}</span>
                                <span class="block font-normal normal-case tracking-normal text-gray-400">{{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($permissions as $permission => $description)
                        <tr>
                            <td class="sticky left-0 bg-white">
                                <p class="font-medium text-gray-900">{{ $permission }}</p>
                                <p class="text-xs text-gray-500">{{ $description }}</p>
                            </td>
                            @foreach ($roles as $role)
                                <td class="text-center">
                                    @if ($role->name === 'super-admin' || $role->permissions->contains('name', $permission))
                                        <x-lucide-circle-check class="mx-auto h-5 w-5 text-emerald-500" aria-label="Granted" />
                                    @else
                                        <span class="text-gray-300" aria-label="Not granted">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50/70">
                        <td class="sticky left-0 bg-gray-50"></td>
                        @foreach ($roles as $role)
                            <td class="text-center">
                                @if ($role->name !== 'super-admin')
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn-secondary btn-sm">Edit</a>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-layouts.app>
