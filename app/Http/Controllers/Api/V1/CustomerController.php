<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Policies\CustomerPolicy;
use App\Services\CustomerService;
use App\Support\CustomerFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(array_keys(CustomerFields::STATUSES))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $customers = CustomerPolicy::visibleTo($request->user())
            ->with(['company', 'assignedDesigner:id,ulid,name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('full_name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25);

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer->load(['company', 'assignedDesigner:id,ulid,name']));
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->save(null, $request->safe()->except(['photo', 'company_logo']), $request->user(),
            ['photo' => $request->file('photo'), 'company_logo' => $request->file('company_logo')], $request->designer());

        return (new CustomerResource($customer->load(['company', 'assignedDesigner:id,ulid,name'])))->response()->setStatusCode(201);
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->customers->save($customer, $request->safe()->except(['photo', 'company_logo']), $request->user(),
            ['photo' => $request->file('photo'), 'company_logo' => $request->file('company_logo')], $request->designer());

        return new CustomerResource($customer->refresh()->load(['company', 'assignedDesigner:id,ulid,name']));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        $this->customers->delete($customer);

        return response()->json(null, 204);
    }
}
