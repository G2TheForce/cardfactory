<?php

namespace App\Http\Controllers\Designs;

use App\Http\Controllers\Controller;
use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Policies\CardDesignPolicy;
use App\Services\AssetService;
use App\Services\CardDesignService;
use App\Services\CardRenderingService;
use App\Services\ThemeManagementService;
use App\Support\Fonts;
use App\Support\Scene\IconLibrary;
use App\Support\Scene\Scene;
use App\Support\Scene\SceneValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DesignController extends Controller
{
    public function __construct(
        private readonly CardDesignService $designs,
        private readonly CardRenderingService $renderer,
    ) {}

    public function index(Request $request, AssetService $assets): View
    {
        $this->authorize('viewAny', CardDesign::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(CardDesignService::STATUSES)],
        ]);

        $designs = CardDesignPolicy::visibleTo($request->user())
            ->with(['customer.company', 'theme:id,ulid,name', 'owner:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%$s%"))))
            ->latest('updated_at')
            ->paginate(18)->withQueryString();

        $previews = $designs->getCollection()->mapWithKeys(fn (CardDesign $d) => [$d->id => $this->designs->preview($d)]);
        $families = $previews->flatMap(fn ($p) => $p['fonts'])->unique()->all();
        $people = $designs->getCollection()->flatMap(fn (CardDesign $d) => [$d->user_id, $d->designer_id])->push($request->user()->id)->all();

        return view('designs.index', [
            'designs' => $designs,
            'previews' => $previews,
            'fontsUrl' => Fonts::googleUrl($families),
            'fontFaceCss' => $assets->fontFaceCssFor($families, $people),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, ThemeManagementService $themes): View
    {
        $this->authorize('create', CardDesign::class);
        $user = $request->user();

        $themeList = CardTheme::query()
            ->where(fn ($q) => $q->where('status', CardTheme::STATUS_PUBLISHED)->orWhere('created_by', $user->id))
            ->orderBy('sort_order')->orderBy('name')->get();
        $previews = $themeList->mapWithKeys(fn (CardTheme $t) => [$t->id => $themes->preview($t)]);

        $customers = CardDesignPolicy::attachableCustomers($user)->orderBy('full_name')->limit(500)->get(['id', 'ulid', 'full_name', 'email']);
        $ownCustomer = $user->customerProfile;

        return view('designs.create', [
            'themes' => $themeList,
            'previews' => $previews,
            'fontsUrl' => Fonts::googleUrl($previews->flatMap(fn ($p) => $p['fonts'])->all()),
            'sizes' => CardDesignService::SIZES,
            'customers' => $user->isStaff() ? $customers : collect(),
            'selectedTheme' => $request->query('theme'),
            'selectedCustomer' => $request->query('customer', $user->isStaff() ? null : $ownCustomer?->ulid),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CardDesign::class);
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'theme' => ['nullable', 'string', 'exists:card_themes,ulid'],
            'customer' => ['nullable', 'string', 'exists:customers,ulid'],
            'size' => ['required_without:theme', 'nullable', Rule::in(array_keys(CardDesignService::SIZES))],
            'orientation' => ['nullable', Rule::in(['landscape', 'portrait'])],
        ]);

        $theme = null;
        if (! empty($data['theme'])) {
            $theme = CardTheme::where('ulid', $data['theme'])->firstOrFail();
            $this->authorize('use', $theme);
        }

        $customer = null;
        if (! empty($data['customer'])) {
            $customer = CardDesignPolicy::attachableCustomers($user)->where('ulid', $data['customer'])->first();
            abort_unless($customer, 403, 'You cannot create designs for this customer.');
        } elseif (! $user->isStaff()) {
            $customer = $user->customerProfile;
        }

        $design = $this->designs->create($user, [
            'name' => $data['name'],
            'theme' => $theme,
            'customer' => $customer,
            'size' => $data['size'] ?? 'iso',
            'orientation' => $data['orientation'] ?? 'landscape',
        ]);

        return redirect()->route('designs.edit', $design)->with('success', 'Design created. Start editing below.');
    }

    /** The visual editor. */
    public function edit(Request $request, CardDesign $design, AssetService $assets): View
    {
        $this->authorize('update', $design);
        $user = $request->user();
        $design->load('customer.company', 'theme:id,ulid,name');

        $uploads = $user->uploadedAssets()->latest()->limit(200)->get();
        $fonts = $uploads->where('type', 'font')->values();
        // Fonts uploaded by the design's owner or designer (e.g. a customer's brand font) also load.
        $sharedFontCss = $assets->fontFaceCssFor(
            $this->renderer->fontsUsed($design->front ?? [], $design->back ?? []),
            array_diff([$design->user_id, $design->designer_id], [$user->id]),
        );
        $customFamilies = $fonts->map(fn ($f) => $f->meta['family'] ?? null)
            ->merge(collect($this->renderer->fontsUsed($design->front ?? [], $design->back ?? []))->reject(fn ($f) => Fonts::isGoogle($f)))
            ->filter()->unique()->values();

        $palette = array_merge(['primary' => '#111827', 'secondary' => '#374151', 'accent' => '#4f46e5', 'background' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280'], $design->settings['palette'] ?? []);

        $state = [
            'design' => [
                'id' => $design->ulid,
                'name' => $design->name,
                'status' => $design->status,
                'width' => (float) $design->width_mm,
                'height' => (float) $design->height_mm,
                'bleed' => (float) $design->bleed_mm,
                'safe' => (float) config('smartcard.card.safe_area_mm'),
                'radius' => (float) $design->corner_radius_mm,
                'version' => (int) $design->version,
                'palette' => $palette,
                'front' => $design->front ?? Scene::emptySide(),
                'back' => $design->back ?? Scene::emptySide(),
                'customer' => $design->customer ? ['id' => $design->customer->ulid, 'name' => $design->customer->full_name] : null,
                'showGuides' => (bool) ($design->settings['show_guides'] ?? true),
            ],
            'urls' => [
                'save' => route('designs.update', $design),
                'render' => route('designs.render', $design),
                'upload' => route('assets.store'),
                'uploadFont' => route('assets.fonts.store'),
                'assets' => route('assets.index'),
                'icons' => route('editor.icons'),
                'duplicate' => route('designs.duplicate', $design),
                'index' => route('designs.index'),
            ],
            'assets' => $uploads->where('type', '!=', 'font')->values()->map(fn ($a) => $assets->toArray($a)),
            'fonts' => $fonts->map(fn ($a) => $assets->toArray($a)),
            'fontOptions' => array_merge(
                $customFamilies->map(fn ($f) => ['name' => $f, 'category' => 'custom', 'weights' => [400, 700]])->all(),
                Fonts::options(),
            ),
            'mergeFields' => Scene::MERGE_FIELDS,
            'icons' => IconLibrary::CURATED,
            'social' => Scene::SOCIAL_NETWORKS,
            'shapes' => Scene::SHAPES,
            'patterns' => Scene::PATTERNS,
            'hasCustomer' => (bool) $design->customer_id,
            'designFonts' => $design->settings['fonts'] ?? ['heading' => 'Inter', 'body' => 'Inter'],
        ];

        $options = $this->editorRenderOptions($design, $palette, $state['design']['showGuides'], $design->customer_id ? 'customer' : 'sample');

        return view('designs.editor', [
            'design' => $design,
            'state' => $state,
            'svgs' => [
                'front' => $this->renderer->render($state['design']['front'], $options),
                'back' => $this->renderer->render($state['design']['back'], $options),
            ],
            'fontsUrl' => Fonts::googleUrl(array_column(Fonts::options(), 'name')),
            'fontFaceCss' => $assets->fontFaceCss($fonts).$sharedFontCss,
            'iconSvgs' => collect(IconLibrary::CURATED)->mapWithKeys(fn ($i) => [$i => IconLibrary::inner($i)]),
            'socialSvgs' => collect(Scene::SOCIAL_NETWORKS)->mapWithKeys(fn ($n) => [$n => IconLibrary::social($n)]),
            'statuses' => CardDesignService::STATUSES,
            'sizes' => CardDesignService::SIZES,
        ]);
    }

    /** Save editor state (JSON). */
    public function update(Request $request, CardDesign $design): JsonResponse
    {
        $this->authorize('update', $design);

        $data = $request->validate([
            'version' => ['nullable', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(CardDesignService::STATUSES)],
            'front' => ['sometimes', 'array'],
            'back' => ['sometimes', 'array'],
            'palette' => ['sometimes', 'array'],
            'palette.*' => ['string', 'max:40'],
            'settings' => ['sometimes', 'array'],
            'settings.show_guides' => ['sometimes', 'boolean'],
            'width_mm' => ['sometimes', 'numeric', 'between:30,200', 'required_with:height_mm'],
            'height_mm' => ['sometimes', 'numeric', 'between:30,200', 'required_with:width_mm'],
            'bleed_mm' => ['sometimes', 'numeric', 'between:0,6'],
            'corner_radius_mm' => ['sometimes', 'numeric', 'between:0,10'],
            'customer' => ['sometimes', 'nullable', 'string'],
        ]);

        $palette = $data['palette'] ?? null;
        if ($palette !== null) {
            $data['palette'] = $this->validPalette($palette);
        }

        if (array_key_exists('customer', $data)) {
            if ($data['customer'] === null) {
                $data['customer_id'] = null;
            } else {
                $customer = CardDesignPolicy::attachableCustomers($request->user())->where('ulid', $data['customer'])->first();
                abort_unless($customer, 422, 'You cannot attach this customer.');
                $data['customer_id'] = $customer->id;
            }
        }

        $design = $this->designs->update($design, Arr::except($data, ['version', 'customer']), isset($data['version']) ? (int) $data['version'] : null);
        $design->load('customer:id,ulid,full_name');

        return response()->json([
            'version' => $design->version,
            'saved_at' => $design->updated_at?->toIso8601String(),
            'front' => $design->front,
            'back' => $design->back,
            'customer' => $design->customer ? ['id' => $design->customer->ulid, 'name' => $design->customer->full_name] : null,
            'message' => 'Design saved.',
        ]);
    }

    /** Render an unsaved side for the editor canvas (JSON with SVG markup). */
    public function render(Request $request, CardDesign $design, SceneValidator $validator): JsonResponse
    {
        $this->authorize('update', $design);
        $data = $request->validate([
            'side' => ['required', 'array'],
            'palette' => ['nullable', 'array'],
            'fields' => ['nullable', Rule::in(['customer', 'sample', 'raw'])],
            'guides' => ['nullable', 'boolean'],
            'bleed' => ['nullable', 'boolean'],
            'width_mm' => ['nullable', 'numeric', 'between:30,200'],
            'height_mm' => ['nullable', 'numeric', 'between:30,200'],
            'corner_radius_mm' => ['nullable', 'numeric', 'between:0,10'],
        ]);

        $side = $validator->validate($data['side'], 'side');
        $palette = $this->validPalette($data['palette'] ?? ($design->settings['palette'] ?? []));
        $options = $this->editorRenderOptions($design, $palette, (bool) ($data['guides'] ?? false), $data['fields'] ?? 'customer', $data['bleed'] ?? true);
        foreach (['width_mm', 'height_mm', 'corner_radius_mm'] as $key) {
            if (isset($data[$key])) {
                $options[$key] = (float) $data[$key];
            }
        }

        return response()->json(['svg' => $this->renderer->render($side, $options)]);
    }

    public function duplicate(Request $request, CardDesign $design): RedirectResponse
    {
        $this->authorize('view', $design);
        $copy = $this->designs->duplicate($design, $request->user());

        return redirect()->route('designs.edit', $copy)->with('success', 'Design duplicated.');
    }

    public function destroy(CardDesign $design): RedirectResponse
    {
        $this->authorize('delete', $design);
        $this->designs->delete($design);

        return redirect()->route('designs.index')->with('success', 'Design deleted.');
    }

    /** Icon search for the editor's icon picker. */
    public function icons(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:40']]);
        $q = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($validated['q'] ?? '')));
        $names = $q === '' ? IconLibrary::CURATED : collect(glob(IconLibrary::directory().'/*'.$q.'*.svg') ?: [])
            ->map(fn ($f) => basename($f, '.svg'))->sortBy(fn ($n) => [strlen($n), $n])->take(60)->values()->all();

        return response()->json(collect($names)->map(fn ($n) => ['name' => $n, 'svg' => IconLibrary::inner($n)])->filter(fn ($i) => $i['svg'])->values());
    }

    private function editorRenderOptions(CardDesign $design, array $palette, bool $guides, string $fields, bool $bleed = true): array
    {
        $data = match ($fields) {
            'raw' => $this->designs->rawFieldData(),
            'sample' => Scene::sampleData(),
            default => $this->designs->mergeData($design),
        };

        return $this->designs->renderOptions($design, [
            'bleed_mm' => $bleed ? (float) $design->bleed_mm : 0,
            'palette' => $palette,
            'data' => $data,
            'clip_corners' => ! $bleed,
            'show_guides' => $guides,
        ]);
    }

    private function validPalette(array $palette): array
    {
        $validator = app(SceneValidator::class);
        $clean = [];
        $errors = [];
        foreach (Arr::only($palette, Scene::PALETTE_KEYS) as $key => $value) {
            $color = $validator->color($value, "palette.$key");
            if (! is_string($value) || $color !== trim($value) || str_starts_with($color, 'palette.')) {
                $errors["palette.$key"] = 'Palette colours must be hex or rgb values.';
            } else {
                $clean[$key] = $color;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
