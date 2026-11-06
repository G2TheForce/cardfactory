<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profiles\ProfileRequest;
use App\Models\AnalyticsEvent;
use App\Models\CardDesign;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Policies\CustomerPolicy;
use App\Policies\DigitalProfilePolicy;
use App\Services\DigitalProfileService;
use App\Services\NFCManagementService;
use App\Services\ProfileSiteExporter;
use App\Services\QRCodeService;
use App\Services\Social\SocialStatsService;
use App\Support\ProfileThemes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly DigitalProfileService $profiles) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['live', 'off'])],
            'theme' => ['nullable', Rule::in(array_keys(ProfileThemes::THEMES))],
        ]);

        $profiles = DigitalProfilePolicy::visibleTo($user)
            ->with(['customer:id,ulid,full_name,professional_title,profile_photo_path,company_id,status', 'customer.company:id,name'])
            ->withCount(['nfcAssignments as nfc_count' => fn ($q) => $q->whereNull('ended_at')])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('is_active', $s === 'live'))
            ->when($filters['theme'] ?? null, fn ($q, $t) => $q->where('profile_theme', $t))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('slug', 'like', "%$s%")
                ->orWhere('card_identifier', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))))
            ->latest('id')
            ->paginate(20)->withQueryString();

        return view('profiles.index', [
            'profiles' => $profiles,
            'filters' => $filters,
            'canCreate' => $user->can('create', DigitalProfile::class),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', DigitalProfile::class);
        $user = $request->user();
        $customers = $this->creatableCustomers($request);

        if ($customers->isEmpty()) {
            return redirect()->route($user->hasRole('customer') ? 'my-details' : 'customers.create')
                ->with('warning', 'Add the customer\'s details first, then create their profile.');
        }

        $selected = $customers->firstWhere('ulid', $request->query('customer')) ?? ($customers->count() === 1 ? $customers->first() : null);

        return view('profiles.create', [
            'customers' => $customers,
            'selected' => $selected,
            'suggested' => $selected ? $this->profiles->suggestSlug($selected->full_name) : '',
            'themes' => ProfileThemes::THEMES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DigitalProfile::class);
        $data = $request->validate([
            'customer' => ['required', 'string', 'size:26'],
            'slug' => ['nullable', 'string', 'min:3', 'max:60', 'regex:'.DigitalProfileService::SLUG_PATTERN],
            'profile_theme' => ['required', Rule::in(array_keys(ProfileThemes::THEMES))],
        ], ['slug.regex' => 'Use lowercase letters, numbers and dashes (3–60 characters).']);

        $customer = Customer::where('ulid', $data['customer'])->firstOrFail();
        $this->authorize('createFor', [DigitalProfile::class, $customer]);

        $profile = $this->profiles->create($customer, [
            'slug' => isset($data['slug']) ? strtolower($data['slug']) : null,
            'profile_theme' => $data['profile_theme'],
            'card_design' => CardDesign::where('customer_id', $customer->id)->latest('updated_at')->first(),
        ], $request->user());

        return redirect()->route('profiles.show', $profile)->with('success', 'Profile created. It is live at the address below.');
    }

    public function show(Request $request, DigitalProfile $profile, NFCManagementService $nfc, QRCodeService $qr): View
    {
        $this->authorize('view', $profile);
        $profile->load('customer.company', 'cardDesign:id,ulid,name');
        $qrCode = $this->profiles->qrCode($profile);
        $style = $qr->style($qrCode->style);
        $urls = $this->profiles->urls($profile);

        $since = now()->subDays(30);
        $stats = AnalyticsEvent::where('digital_profile_id', $profile->id)->where('occurred_at', '>=', $since)
            ->selectRaw('event_type, count(*) as total')->groupBy('event_type')->pluck('total', 'event_type');

        return view('profiles.show', [
            'profile' => $profile,
            'customer' => $profile->customer,
            'urls' => $urls,
            'qrCode' => $qrCode,
            'qrStyle' => $style,
            'qrSvg' => $qr->svg($urls['qr'], 220, $style['color'], $style['background'], $style['margin'], $style['level']),
            'available' => $this->profiles->isAvailable($profile),
            'cards' => $nfc->cardsFor($profile)->map(fn ($a) => $a->setAttribute('tag_url', $nfc->tagUrl($a->nfcCard))),
            'history' => $nfc->historyFor($profile),
            'canEdit' => $request->user()->can('update', $profile),
            'canNfc' => $request->user()->can('manageNfc', $profile),
            'tagTypes' => NFCManagementService::TAG_TYPES,
            'stats' => [
                'Views' => (int) ($stats[AnalyticsEvent::PROFILE_VIEW] ?? 0),
                'QR scans' => (int) ($stats[AnalyticsEvent::QR_SCAN] ?? 0),
                'NFC taps' => (int) ($stats[AnalyticsEvent::NFC_TAP] ?? 0),
                'Contacts saved' => (int) ($stats[AnalyticsEvent::VCARD_DOWNLOAD] ?? 0),
                'Button clicks' => (int) collect($stats)->filter(fn ($v, $k) => str_starts_with($k, 'click_'))->sum(),
            ],
        ]);
    }

    public function edit(DigitalProfile $profile): View
    {
        $this->authorize('update', $profile);
        $profile->load('customer');

        return view('profiles.edit', [
            'profile' => $profile,
            'settings' => $this->profiles->settings($profile),
            'layout' => ProfileThemes::normaliseLayout($profile->layout),
            'themes' => ProfileThemes::THEMES,
            'social' => app(SocialStatsService::class),
            'designs' => CardDesign::where('customer_id', $profile->customer_id)->latest('updated_at')->get(['id', 'ulid', 'name']),
        ]);
    }

    public function update(ProfileRequest $request, DigitalProfile $profile): RedirectResponse
    {
        $data = $request->safe()->except(['cover', 'background_image', 'portfolio', 'card_design']);
        // The web form omits an empty button list entirely.
        if ($request->boolean('_form')) {
            $data += ['ctas' => []];
        }
        if ($request->has('card_design')) {
            $data['card_design'] = $request->input('card_design')
                ? CardDesign::where('ulid', $request->input('card_design'))->firstOrFail()
                : null;
        }

        $this->profiles->update($profile, $data, $request->user(), [
            'cover' => $request->file('cover'),
            'background_image' => $request->file('background_image'),
            'portfolio' => $request->file('portfolio', []),
        ]);

        return redirect()->route('profiles.edit', [$profile, 'tab' => $request->input('_tab')])->with('success', 'Profile saved.');
    }

    /**
     * Live preview of unsaved edits: applies the form to the profile inside a
     * transaction, renders the public page, then rolls everything back.
     * Uploaded files are ignored; images show once saved.
     */
    public function draftPreview(ProfileRequest $request, DigitalProfile $profile): Response
    {
        $data = $request->safe()->except(['cover', 'background_image', 'portfolio', 'card_design', 'remove_cover', 'remove_background_image', 'portfolio_remove']);
        $data += ['ctas' => []];

        DB::beginTransaction();
        try {
            $draft = $this->profiles->update($profile, $data, $request->user());
            $html = view($this->profiles->publicView($draft), [
                'profile' => $draft,
                'p' => $this->profiles->publicData($draft),
                'urls' => $this->profiles->urls($draft),
                'preview' => true,
                'available' => $this->profiles->isAvailable($draft),
            ])->render();
        } finally {
            DB::rollBack();
        }

        return response($html);
    }

    /** Download the profile as a static website to host anywhere. */
    public function exportSite(Request $request, DigitalProfile $profile, ProfileSiteExporter $exporter): BinaryFileResponse
    {
        $this->authorize('update', $profile);
        $data = $request->validate(['site_url' => ['nullable', 'url:https,http', 'max:200']], [
            'site_url.url' => 'Enter the full address, like https://jane.netlify.app.',
        ]);
        $path = $exporter->export($profile, $data['site_url'] ?? null);

        return response()->download($path, $exporter->filename($profile), ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** Fetch follower counts now instead of waiting for the daily refresh. */
    public function refreshSocial(DigitalProfile $profile, SocialStatsService $stats): RedirectResponse
    {
        $this->authorize('update', $profile);
        $counts = $stats->refresh($profile);
        $failed = collect($counts)->filter(fn ($c) => $c['error'])->count();

        return redirect()->route('profiles.edit', [$profile, 'tab' => 'social'])->with(
            $failed ? 'error' : 'success',
            $failed ? "Counts refreshed, but {$failed} network(s) couldn't be read. See the notes below." : 'Follower counts refreshed.'
        );
    }

    public function destroy(Request $request, DigitalProfile $profile): RedirectResponse
    {
        $this->authorize('delete', $profile);
        $this->profiles->delete($profile, $request->user());

        return redirect()->route('profiles.index')->with('success', 'Profile deleted. Its QR code and NFC cards no longer open it.');
    }

    /** Owner/staff preview of the public page: works while inactive and isn't counted. */
    public function preview(DigitalProfile $profile): View
    {
        $this->authorize('view', $profile);

        return view($this->profiles->publicView($profile), [
            'profile' => $profile,
            'p' => $this->profiles->publicData($profile),
            'urls' => $this->profiles->urls($profile),
            'preview' => true,
            'available' => $this->profiles->isAvailable($profile),
        ]);
    }

    /** Customer accounts: jump to their profile, or start one. */
    public function mine(Request $request): RedirectResponse
    {
        $customer = $request->user()->customerProfile;
        $profile = $customer?->digitalProfiles()->latest('id')->first();

        return $profile ? redirect()->route('profiles.show', $profile) : redirect()->route('profiles.create');
    }

    private function creatableCustomers(Request $request)
    {
        $user = $request->user();
        if ($user->can('profiles.manage') || $user->can('customers.manage')) {
            return CustomerPolicy::visibleTo($user)->orderBy('full_name')->limit(1000)->get(['id', 'ulid', 'full_name', 'email']);
        }

        return Customer::where('user_id', $user->id)->get(['id', 'ulid', 'full_name', 'email']);
    }
}
