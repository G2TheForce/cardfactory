<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\CustomerRequest;
use App\Models\ActivityLog;
use App\Models\CardDesign;
use App\Models\Customer;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Services\CardDesignService;
use App\Services\CustomerService;
use App\Support\CustomerFields;
use App\Support\Fonts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);
        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in([...array_keys(CustomerFields::STATUSES), 'deleted'])],
            'designer' => ['nullable', 'string', 'max:30'],
            'sort' => ['nullable', Rule::in(['name', 'newest', 'updated'])],
        ]);

        $query = CustomerPolicy::visibleTo($user)
            ->with(['company:id,name,logo_path', 'assignedDesigner:id,name', 'user:id'])
            ->withCount(['cardDesigns', 'digitalProfiles'])
            ->when(($filters['status'] ?? null) === 'deleted' && $user->can('customers.manage'), fn ($q) => $q->onlyTrashed())
            ->when(in_array($filters['status'] ?? null, array_keys(CustomerFields::STATUSES), true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['designer'] ?? null, fn ($q, $d) => $q->whereHas('assignedDesigner', fn ($u) => $u->where('ulid', $d)))
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->where(fn ($q) => $q->where('full_name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")
                    ->orWhere('mobile', 'like', "%$s%")->orWhere('professional_title', 'like', "%$s%")
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%$s%")));
            });

        match ($filters['sort'] ?? 'newest') {
            'name' => $query->orderBy('full_name'),
            'updated' => $query->latest('updated_at'),
            default => $query->latest('id'),
        };

        $base = CustomerPolicy::visibleTo($user);

        return view('customers.index', [
            'customers' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'designers' => $user->can('customers.view-any') ? $this->designers() : collect(),
            'counts' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
                'with_login' => (clone $base)->whereNotNull('user_id')->count(),
                'new' => (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.form', $this->formData(new Customer(['status' => 'active'])));
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->save(null, $this->formInput($request), $request->user(), $this->files($request), $request->designer());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer created.');
    }

    public function show(Request $request, Customer $customer, CardDesignService $designs): View
    {
        $this->authorize('view', $customer);
        $customer->load(['company', 'assignedDesigner:id,ulid,name', 'creator:id,name', 'user:id,ulid,email,last_login_at']);

        $recentDesigns = CardDesign::where('customer_id', $customer->id)->with('brandKit')->latest('updated_at')->limit(6)->get()->each->setRelation('customer', $customer);
        $previews = $recentDesigns->mapWithKeys(fn ($d) => [$d->id => $designs->preview($d)]);

        return view('customers.show', [
            'customer' => $customer,
            'designs' => $recentDesigns,
            'designCount' => CardDesign::where('customer_id', $customer->id)->count(),
            'previews' => $previews,
            'fontsUrl' => Fonts::googleUrl($previews->flatMap(fn ($p) => $p['fonts'])->all()),
            'profiles' => $customer->digitalProfiles()->latest()->get(),
            'nfc' => $customer->nfcAssignments()->whereNull('ended_at')->with('nfcCard')->latest()->limit(10)->get(),
            'activity' => $request->user()->can('activity.view') || $request->user()->can('customers.manage')
                ? ActivityLog::where('subject_type', $customer->getMorphClass())->where('subject_id', $customer->id)->with('user:id,name')->latest()->limit(15)->get()
                : collect(),
            'completeness' => $this->customers->completeness($customer),
            'canCreateDesign' => Route::has('designs.create') && $request->user()->can('create', CardDesign::class),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.form', $this->formData($customer->load('company', 'assignedDesigner:id,ulid,name')));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->save($customer, $this->formInput($request), $request->user(), $this->files($request), $request->designer());

        return $request->user()->can('view', $customer)
            ? redirect()->route('customers.show', $customer)->with('success', 'Customer saved.')
            : back()->with('success', 'Your details were saved.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);
        $this->customers->delete($customer);

        return redirect()->route('customers.index')->with('success', 'Customer deleted. You can restore them from the Deleted filter.');
    }

    public function restore(string $customer): RedirectResponse
    {
        $model = Customer::onlyTrashed()->where('ulid', $customer)->firstOrFail();
        $this->authorize('restore', $model);
        $this->customers->restore($model);

        return redirect()->route('customers.show', $model)->with('success', 'Customer restored.');
    }

    public function createLogin(Customer $customer): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $this->authorize('update', $customer);
        $this->customers->createLogin($customer);

        return back()->with('success', 'Login created. '.$customer->email.' will get an email to set a password.');
    }

    /** Customer accounts: edit the details used on their own cards and profile. */
    public function myDetails(Request $request): RedirectResponse
    {
        $user = $request->user();
        $customer = $user->customerProfile ?? Customer::create([
            'user_id' => $user->id, 'created_by' => $user->id, 'full_name' => $user->name, 'email' => $user->email,
            'mobile' => $user->phone, 'status' => 'active',
        ]);

        return redirect()->route('customers.edit', $customer);
    }

    private function formData(Customer $customer): array
    {
        return [
            'customer' => $customer,
            'designers' => auth()->user()->can('customers.manage') ? $this->designers() : collect(),
            'visibility' => $this->customers->visibility($customer->visibility ?? []),
        ];
    }

    private function designers()
    {
        return User::permission(['designs.assigned', 'designs.manage'])->where('is_active', true)->orderBy('name')->get(['id', 'ulid', 'name']);
    }

    /** Web forms omit empty groups entirely; treat them as cleared. */
    private function formInput(CustomerRequest $request): array
    {
        return $request->safe()->except(['photo', 'company_logo']) + [
            'social_links' => [], 'custom_fields' => [], 'custom_links' => [], 'visibility' => [],
        ];
    }

    private function files(CustomerRequest $request): array
    {
        return ['photo' => $request->file('photo'), 'company_logo' => $request->file('company_logo')];
    }
}
