<?php

namespace App\Http\Controllers\Brands;

use App\Http\Controllers\Controller;
use App\Models\BrandKit;
use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Policies\BrandKitPolicy;
use App\Policies\CardDesignPolicy;
use App\Services\AssetService;
use App\Services\BrandKitService;
use App\Services\CardDesignService;
use App\Support\CustomerFields;
use App\Support\Fonts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Brand kits: CRUD, applying a kit to a design, and new designs (templates) per brand. */
class BrandKitController extends Controller
{
    private const COLOR = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

    public function __construct(private readonly BrandKitService $kits) {}

    public function index(Request $request): View
    {
        $kits = BrandKitPolicy::visibleTo($request->user())->withCount('cardDesigns')->with('user:id,name')->orderBy('name')->paginate(24);

        return view('brand-kits.index', ['kits' => $kits]);
    }

    public function create(): View
    {
        $this->authorize('create', BrandKit::class);

        return $this->form(new BrandKit([
            'primary_color' => '#4F46E5', 'secondary_color' => '#111827', 'accent_color' => '#F59E0B',
            'typography' => ['heading' => 'Inter', 'body' => 'Inter'], 'background' => ['type' => 'solid', 'color' => '#FFFFFF'],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BrandKit::class);
        $kit = $this->kits->save(null, $this->validated($request), $request->user(), $request->file() ?: []);

        return redirect()->route('brand-kits.show', $kit)->with('success', 'Brand kit "'.$kit->name.'" created.');
    }

    public function show(Request $request, BrandKit $brandKit, CardDesignService $designs, AssetService $assets): View
    {
        $this->authorize('view', $brandKit);
        $user = $request->user();

        $brandDesigns = $brandKit->cardDesigns()->with('customer:id,full_name')->latest('updated_at')->limit(60)->get()
            ->filter(fn (CardDesign $d) => $user->can('view', $d))->each->setRelation('brandKit', $brandKit)->values();
        $previews = $brandDesigns->mapWithKeys(fn (CardDesign $d) => [$d->id => $designs->preview($d)]);
        $others = CardDesignPolicy::visibleTo($user)->where(fn ($q) => $q->whereNull('brand_kit_id')->orWhere('brand_kit_id', '!=', $brandKit->id))
            ->latest('updated_at')->limit(200)->get(['id', 'ulid', 'name', 'user_id', 'designer_id', 'customer_id'])
            ->filter(fn (CardDesign $d) => $user->can('update', $d))->values();

        return view('brand-kits.show', [
            'kit' => $brandKit,
            'designs' => $brandDesigns,
            'previews' => $previews,
            'otherDesigns' => $others,
            'themes' => $this->themes($request),
            'fontsUrl' => Fonts::googleUrl($families = array_merge(array_values($brandKit->typography ?? []), $previews->flatMap(fn ($p) => $p['fonts'])->all())),
            'fontFaceCss' => $assets->fontFaceCssFor($families, $brandDesigns->flatMap(fn (CardDesign $d) => [$d->user_id, $d->designer_id])->push($user->id)->all()),
        ]);
    }

    public function edit(BrandKit $brandKit): View
    {
        $this->authorize('update', $brandKit);

        return $this->form($brandKit);
    }

    public function update(Request $request, BrandKit $brandKit): RedirectResponse
    {
        $this->authorize('update', $brandKit);
        $this->kits->save($brandKit, $this->validated($request), $request->user(), $request->file() ?: []);

        return redirect()->route('brand-kits.show', $brandKit)->with('success', 'Brand kit saved. Apply it to a design to update that design\'s colours and fonts.');
    }

    public function destroy(Request $request, BrandKit $brandKit): RedirectResponse
    {
        $this->authorize('delete', $brandKit);
        $this->kits->delete($brandKit, $request->user());

        return redirect()->route('brand-kits.index')->with('success', 'Brand kit deleted. Its designs keep their colours and fonts.');
    }

    /** Apply the kit to an existing design the user may edit. */
    public function apply(Request $request, BrandKit $brandKit): RedirectResponse
    {
        $this->authorize('view', $brandKit);
        $data = $request->validate([
            'design' => ['required', 'string', 'exists:card_designs,ulid'],
            'background' => ['nullable', 'array'],
            'background.*' => [Rule::in(['front', 'back'])],
        ]);
        $design = CardDesign::where('ulid', $data['design'])->firstOrFail();
        $this->authorize('update', $design);

        $this->kits->apply($brandKit, $design, $request->user(), $data['background'] ?? []);

        return redirect()->route('brand-kits.show', $brandKit)->with('success', '"'.$brandKit->name.'" applied to "'.$design->name.'".');
    }

    /** New design (template) for the brand, started from a theme. */
    public function storeDesign(Request $request, BrandKit $brandKit): RedirectResponse
    {
        $this->authorize('view', $brandKit);
        $this->authorize('create', CardDesign::class);
        $data = $request->validate([
            'theme' => ['required', 'string', 'exists:card_themes,ulid'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);
        $theme = CardTheme::where('ulid', $data['theme'])->firstOrFail();
        $this->authorize('use', $theme);

        $design = $this->kits->createDesign($brandKit, $theme, $request->user(), $data['name'] ?? null);

        return redirect()->route('designs.edit', $design)->with('success', 'New "'.$brandKit->name.'" design created.');
    }

    private function form(BrandKit $kit): View
    {
        return view('brand-kits.form', [
            'kit' => $kit,
            'fonts' => Fonts::options(),
            'fontsUrl' => Fonts::googleUrl(array_keys(config('fonts.google'))),
            'social' => CustomerFields::SOCIAL,
        ]);
    }

    private function themes(Request $request)
    {
        return CardTheme::query()
            ->where(fn ($q) => $q->where('status', CardTheme::STATUS_PUBLISHED)->orWhere('created_by', $request->user()->id))
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'ulid', 'name', 'status', 'created_by']);
    }

    private function validated(Request $request): array
    {
        $fonts = array_keys(config('fonts.google'));

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'primary_color' => self::COLOR,
            'secondary_color' => self::COLOR,
            'accent_color' => self::COLOR,
            'heading_font' => ['required', Rule::in($fonts)],
            'body_font' => ['required', Rule::in($fonts)],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120', 'dimensions:min_width=50,min_height=50'],
            'remove_logo' => ['nullable', 'boolean'],
            'background_type' => ['required', Rule::in(['solid', 'gradient', 'image'])],
            'background_color' => self::COLOR,
            'background_color2' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_angle' => ['nullable', 'integer', 'between:0,360'],
            'background_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:10240', 'dimensions:min_width=300,min_height=200'],
            'remove_background_image' => ['nullable', 'boolean'],
            'company' => ['nullable', 'array'],
            'company.name' => ['nullable', 'string', 'max:120'],
            'company.tagline' => ['nullable', 'string', 'max:160'],
            'company.website' => ['nullable', 'url:http,https', 'max:255'],
            'company.phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[\d\s().\-]{5,}$/'],
            'company.email' => ['nullable', 'email', 'max:190'],
            'company.address' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'url:http,https', 'max:255'],
        ], [
            '*_color.regex' => 'Use a hex colour such as #1A2B3C.',
            'background_color2.regex' => 'Use a hex colour such as #1A2B3C.',
            'company.phone.regex' => 'Use digits, spaces and + ( ) - only.',
        ]);
    }
}
