<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Profiles\QrController;
use App\Http\Requests\Profiles\ProfileRequest;
use App\Http\Resources\DigitalProfileResource;
use App\Models\CardDesign;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Policies\DigitalProfilePolicy;
use App\Services\DigitalProfileService;
use App\Support\ProfileThemes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DigitalProfileController extends Controller
{
    public function __construct(private readonly DigitalProfileService $profiles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'active' => ['nullable', 'boolean'],
            'customer' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $profiles = DigitalProfilePolicy::visibleTo($request->user())->with('customer')
            ->when(isset($filters['active']), fn ($q) => $q->where('is_active', (bool) $filters['active']))
            ->when($filters['customer'] ?? null, fn ($q, $c) => $q->whereHas('customer', fn ($q) => $q->where('ulid', $c)))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('slug', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%$s%"))))
            ->latest('id')->paginate($filters['per_page'] ?? 25);

        return DigitalProfileResource::collection($profiles);
    }

    public function show(DigitalProfile $profile): DigitalProfileResource
    {
        $this->authorize('view', $profile);

        return new DigitalProfileResource($profile->load('customer'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DigitalProfile::class);
        $data = $request->validate([
            'customer' => ['required', 'string', 'size:26'],
            'slug' => ['nullable', 'string', 'min:3', 'max:60', 'regex:'.DigitalProfileService::SLUG_PATTERN],
            'profile_theme' => ['nullable', Rule::in(array_keys(ProfileThemes::THEMES))],
        ]);
        $customer = Customer::where('ulid', $data['customer'])->firstOrFail();
        $this->authorize('createFor', [DigitalProfile::class, $customer]);

        $profile = $this->profiles->create($customer, $data, $request->user());

        return (new DigitalProfileResource($profile->load('customer')))->response()->setStatusCode(201);
    }

    public function update(ProfileRequest $request, DigitalProfile $profile): DigitalProfileResource
    {
        $data = $request->safe()->except(['cover', 'portfolio', 'card_design']);
        if ($request->has('card_design')) {
            $data['card_design'] = $request->input('card_design') ? CardDesign::where('ulid', $request->input('card_design'))->firstOrFail() : null;
        }
        $this->profiles->update($profile, $data, $request->user(), ['cover' => $request->file('cover'), 'portfolio' => $request->file('portfolio', [])]);

        return new DigitalProfileResource($profile->refresh()->load('customer'));
    }

    public function destroy(Request $request, DigitalProfile $profile): JsonResponse
    {
        $this->authorize('delete', $profile);
        $this->profiles->delete($profile, $request->user());

        return response()->json(null, 204);
    }

    public function qr(Request $request, DigitalProfile $profile, string $format, QrController $qr): Response
    {
        $this->authorize('view', $profile);
        $size = (int) $request->query('size', QrController::SIZES[$format][1]);
        abort_unless(in_array($size, QrController::SIZES[$format], true), 422, 'Unsupported size.');

        return $qr->file($profile, $format, $size);
    }
}
