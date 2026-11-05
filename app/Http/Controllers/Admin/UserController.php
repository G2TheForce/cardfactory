<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('smartcard.roles')))],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->with('roles:id,name')
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => config('smartcard.roles'),
            'counts' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'staff' => User::role(['super-admin', 'admin', 'designer', 'sales-manager'])->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => $this->roleOptions($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => $request->boolean('is_active', true),
            ]);
            $user->syncRoles([$data['role']]);

            if ($data['role'] === User::ROLE_CUSTOMER) {
                Customer::create([
                    'user_id' => $user->id,
                    'created_by' => $request->user()->id,
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->phone,
                ]);
            }

            return $user;
        });

        $this->logger->log('user.created', $user, "Created user {$user->email}", ['role' => $data['role']]);

        return redirect()->route('admin.users.index')->with('success', "{$user->name} has been added.");
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', [
            'user' => $user,
            'roles' => $this->roleOptions($request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $before = ['role' => $user->primaryRole(), 'is_active' => $user->is_active];

        DB::transaction(function () use ($user, $data, $request) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->syncRoles([$data['role']]);

            if ($data['role'] === User::ROLE_CUSTOMER && ! $user->customerProfile()->exists()) {
                Customer::create([
                    'user_id' => $user->id,
                    'created_by' => $request->user()->id,
                    'full_name' => $user->name,
                    'email' => $user->email,
                ]);
            }

            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        $this->logger->log('user.updated', $user, "Updated user {$user->email}", [
            'before' => $before,
            'after' => ['role' => $data['role'], 'is_active' => $user->is_active],
            'password_changed' => ! empty($data['password']),
        ]);

        return redirect()->route('admin.users.index')->with('success', "{$user->name} has been updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');

        $user->tokens()->delete();
        $user->delete(); // soft delete keeps designs and audit history intact

        $this->logger->log('user.deleted', $user, "Deleted user {$user->email}");

        return redirect()->route('admin.users.index')->with('success', "{$user->name} has been removed.");
    }

    private function roleOptions(User $actor): array
    {
        return collect($actor->assignableRoles())
            ->mapWithKeys(fn ($role) => [$role => config("smartcard.roles.$role.label")])
            ->all();
    }
}
