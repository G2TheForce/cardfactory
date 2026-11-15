<?php

namespace App\Http\Controllers\LandingPages;

use App\Http\Controllers\Controller;
use App\Http\Requests\LandingPages\LandingPageRequest;
use App\Models\BrandKit;
use App\Models\Customer;
use App\Models\LandingPage;
use App\Models\LandingPageLead;
use App\Policies\BrandKitPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\LandingPagePolicy;
use App\Services\AssetService;
use App\Services\LandingPageService;
use App\Services\LandingPageSiteExporter;
use App\Support\LandingPages\Sections;
use App\Support\LandingPages\Styles;
use App\Support\LandingPages\Templates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LandingPageController extends Controller
{
    public function __construct(private readonly LandingPageService $pages) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['live', 'draft'])],
        ]);

        $pages = LandingPagePolicy::visibleTo($user)
            ->with(['customer:id,ulid,full_name,profile_photo_path,company_id', 'customer.company:id,name'])
            ->withCount(['leads', 'leads as new_leads_count' => fn ($q) => $q->whereNull('read_at')])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('is_published', $s === 'live'))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('slug', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))))
            ->latest('updated_at')
            ->paginate(20)->withQueryString();

        return view('landing-pages.index', [
            'pages' => $pages,
            'filters' => $filters,
            'canCreate' => $user->can('create', LandingPage::class),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', LandingPage::class);
        $user = $request->user();
        $customers = $this->creatableCustomers($request);

        if ($customers->isEmpty()) {
            return redirect()->route($user->hasRole('customer') ? 'my-details' : 'customers.create')
                ->with('warning', 'Add the customer\'s details first, then build their landing page.');
        }

        return view('landing-pages.create', [
            'customers' => $customers,
            'selected' => $customers->firstWhere('ulid', $request->query('customer')) ?? ($customers->count() === 1 ? $customers->first() : null),
            'templates' => Templates::TEMPLATES,
            'palettes' => Styles::PALETTES,
            'brandKits' => BrandKitPolicy::visibleTo($user)->orderBy('name')->get(['id', 'ulid', 'name', 'primary_color', 'accent_color']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LandingPage::class);
        $data = $request->validate([
            'customer' => ['required', 'string', 'size:26'],
            'name' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'min:3', 'max:60', 'regex:'.LandingPageService::SLUG_PATTERN],
            'template' => ['required', Rule::in(array_keys(Templates::TEMPLATES))],
            'brand_kit' => ['nullable', 'string', 'size:26'],
        ], ['slug.regex' => 'Use lowercase letters, numbers and dashes (3–60 characters).']);

        $customer = Customer::where('ulid', $data['customer'])->firstOrFail();
        $this->authorize('createFor', [LandingPage::class, $customer]);
        $kit = null;
        if ($data['brand_kit'] ?? null) {
            $kit = BrandKit::where('ulid', $data['brand_kit'])->firstOrFail();
            $this->authorize('view', $kit);
        }

        $page = $this->pages->create($customer, [
            'name' => $data['name'] ?? null,
            'slug' => isset($data['slug']) ? strtolower($data['slug']) : null,
            'template' => $data['template'],
            'brand_kit' => $kit,
        ], $request->user());

        return redirect()->route('landing-pages.edit', $page)->with('success', 'Landing page created as a draft. Edit the sections, then publish it.');
    }

    public function show(Request $request, LandingPage $landingPage): View
    {
        $this->authorize('view', $landingPage);
        $landingPage->load('customer.company', 'brandKit:id,ulid,name');

        return view('landing-pages.show', [
            'page' => $landingPage,
            'url' => $this->pages->publicUrl($landingPage),
            'available' => $this->pages->isAvailable($landingPage),
            'leads' => $landingPage->leads()->latest('id')->paginate(15),
            'newLeads' => $landingPage->leads()->whereNull('read_at')->count(),
            'notifyAddress' => $this->pages->notifyAddress($landingPage),
            'canEdit' => $request->user()->can('update', $landingPage),
            'sectionCount' => count($landingPage->sections ?? []),
        ]);
    }

    public function edit(Request $request, LandingPage $landingPage): View
    {
        $this->authorize('update', $landingPage);
        $landingPage->load('customer', 'brandKit:id,ulid,name');
        $settings = $this->pages->settings($landingPage);
        $disk = Storage::disk('public');

        // The editor works with paths and shows thumbnails from this map.
        $images = collect(Sections::imagePaths($landingPage->sections ?? []))
            ->push($settings['logo_path'])->filter()
            ->mapWithKeys(fn ($path) => [$path => $disk->url($path)]);

        return view('landing-pages.edit', [
            'page' => $landingPage,
            'sections' => $landingPage->sections ?? [],
            'style' => Styles::sanitize($landingPage->style ?? []),
            'settings' => $settings,
            'images' => $images,
            'types' => Sections::TYPES,
            'icons' => Sections::ICONS,
            'palettes' => Styles::PALETTES,
            'brandKits' => BrandKitPolicy::visibleTo($request->user())->orderBy('name')->get(['id', 'ulid', 'name', 'primary_color', 'accent_color']),
        ]);
    }

    public function update(LandingPageRequest $request, LandingPage $landingPage): RedirectResponse|JsonResponse
    {
        $this->pages->update($landingPage, $request->safe()->only(['name', 'slug', 'sections', 'style', 'settings', 'is_published']), $request->user());

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'updated_at' => $landingPage->updated_at?->toIso8601String()]);
        }

        return redirect()->route('landing-pages.edit', [$landingPage, 'panel' => $request->input('_panel')])->with('success', 'Landing page saved.');
    }

    /**
     * Live preview of unsaved edits: applies the form inside a transaction,
     * renders the public page, then rolls everything back.
     */
    public function draftPreview(LandingPageRequest $request, LandingPage $landingPage): Response
    {
        DB::beginTransaction();
        try {
            // Style and settings are cleaned by the service, so half-typed values just fall back.
            $data = $request->safe()->only(['sections']) + array_filter([
                'name' => is_string($request->input('name')) && trim($request->input('name')) !== '' ? mb_substr($request->input('name'), 0, 120) : null,
                'style' => is_array($request->input('style')) ? $request->input('style') : null,
                'settings' => is_array($request->input('settings')) ? $request->input('settings') : null,
            ], fn ($v) => $v !== null);
            $draft = $this->pages->update($landingPage, $data, $request->user(), log: false);
            $html = $this->render($draft, preview: true);
        } finally {
            DB::rollBack();
        }

        return response($html);
    }

    /** Owner/staff preview of the saved page: works while it's a draft and isn't counted. */
    public function preview(LandingPage $landingPage): Response
    {
        $this->authorize('view', $landingPage);

        return response($this->render($landingPage, preview: true));
    }

    public function publish(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $this->authorize('update', $landingPage);
        $published = $request->validate(['published' => ['required', 'boolean']])['published'];
        $this->pages->publish($landingPage, (bool) $published, $request->user());

        return back()->with('success', $published ? 'Landing page is live.' : 'Landing page unpublished. Its address now shows "not available".');
    }

    /** Image upload from the editor; returns the stored path and its URL. */
    public function uploadImage(Request $request, LandingPage $landingPage): JsonResponse
    {
        $this->authorize('update', $landingPage);
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.AssetService::MAX_IMAGE_KB]]);

        return response()->json($this->pages->storeImage($landingPage, $request->file('image'), $request->user()));
    }

    public function applyBrandKit(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $this->authorize('update', $landingPage);
        $data = $request->validate(['brand_kit' => ['required', 'string', 'size:26']]);
        $kit = BrandKit::where('ulid', $data['brand_kit'])->firstOrFail();
        $this->authorize('view', $kit);

        $this->pages->applyBrandKit($landingPage, $kit, $request->user());

        return redirect()->route('landing-pages.edit', [$landingPage, 'panel' => 'style'])->with('success', '"'.$kit->name.'" colours, logo and details applied.');
    }

    /** Download the page as a static website to host anywhere. */
    public function exportSite(Request $request, LandingPage $landingPage, LandingPageSiteExporter $exporter): BinaryFileResponse
    {
        $this->authorize('update', $landingPage);
        $data = $request->validate(['site_url' => ['nullable', 'url:https,http', 'max:200']], [
            'site_url.url' => 'Enter the full address, like https://my-business.netlify.app.',
        ]);
        $path = $exporter->export($landingPage, $data['site_url'] ?? null);

        return response()->download($path, $exporter->filename($landingPage), ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function markLeadsRead(LandingPage $landingPage): RedirectResponse
    {
        $this->authorize('update', $landingPage);
        $landingPage->leads()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All enquiries marked as read.');
    }

    public function destroyLead(LandingPage $landingPage, LandingPageLead $lead): RedirectResponse
    {
        $this->authorize('update', $landingPage);
        $lead->delete();

        return back()->with('success', 'Enquiry deleted.');
    }

    public function destroy(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $this->authorize('delete', $landingPage);
        $this->pages->delete($landingPage, $request->user());

        return redirect()->route('landing-pages.index')->with('success', 'Landing page deleted.');
    }

    private function render(LandingPage $page, bool $preview): string
    {
        return view('landing-pages.public', [
            'page' => $page,
            'p' => $this->pages->publicData($page),
            'urls' => ['public' => $this->pages->publicUrl($page), 'contact' => route('landing-pages.contact', $page->slug)],
            'preview' => $preview,
            'export' => null,
        ])->render();
    }

    private function creatableCustomers(Request $request)
    {
        $user = $request->user();
        if ($user->can('landing-pages.manage') || $user->can('customers.manage')) {
            return CustomerPolicy::visibleTo($user)->orderBy('full_name')->limit(1000)->get(['id', 'ulid', 'full_name', 'email']);
        }

        return Customer::where('user_id', $user->id)->get(['id', 'ulid', 'full_name', 'email']);
    }
}
