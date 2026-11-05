<?php

namespace App\Http\Controllers;

use App\Http\Requests\Themes\CustomizeThemeRequest;
use App\Http\Requests\Themes\ThemeRequest;
use App\Models\CardTheme;
use App\Services\ThemeManagementService;
use App\Support\Fonts;
use App\Support\Scene\Scene;
use App\Support\Themes\ThemeLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function __construct(private readonly ThemeManagementService $themes) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', Rule::in(array_keys(ThemeLibrary::CATEGORIES))],
            'scope' => ['nullable', Rule::in(['all', 'favorites', 'mine', 'drafts'])],
        ]);
        $user = $request->user();
        $scope = $filters['scope'] ?? 'all';

        $themes = CardTheme::query()
            ->where(function ($q) use ($user, $scope) {
                if ($scope === 'drafts' && $user->can('themes.manage')) {
                    $q->where('status', CardTheme::STATUS_DRAFT);
                } elseif ($scope === 'mine') {
                    $q->where('created_by', $user->id);
                } else {
                    $q->where('status', CardTheme::STATUS_PUBLISHED)->orWhere('created_by', $user->id);
                }
            })
            ->when($scope === 'favorites', fn ($q) => $q->whereHas('favoritedBy', fn ($f) => $f->whereKey($user->id)))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('description', 'like', "%$s%")))
            ->withExists(['favoritedBy as is_favorite' => fn ($f) => $f->whereKey($user->id)])
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(24)->withQueryString();

        $previews = $themes->getCollection()->mapWithKeys(fn (CardTheme $t) => [$t->id => $this->themes->preview($t)]);

        return view('themes.index', [
            'themes' => $themes,
            'previews' => $previews,
            'fontsUrl' => Fonts::googleUrl($previews->flatMap(fn ($p) => $p['fonts'])->all()),
            'filters' => $filters + ['scope' => $scope],
            'categories' => ThemeLibrary::CATEGORIES,
            'counts' => [
                'favorites' => $user->favoriteThemes()->count(),
                'mine' => CardTheme::where('created_by', $user->id)->count(),
                'drafts' => $user->can('themes.manage') ? CardTheme::where('status', CardTheme::STATUS_DRAFT)->count() : 0,
            ],
        ]);
    }

    public function show(Request $request, CardTheme $theme): View
    {
        $this->authorize('view', $theme);
        $preview = $this->themes->preview($theme);

        return view('themes.show', [
            'theme' => $theme->load('creator:id,name', 'parent:id,ulid,name'),
            'preview' => $preview,
            'fontsUrl' => Fonts::googleUrl($preview['fonts']),
            'isFavorite' => $request->user()->favoriteThemes()->whereKey($theme->id)->exists(),
            'designsCount' => $theme->cardDesigns()->count(),
            'canStartDesign' => Route::has('designs.create'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CardTheme::class);
        $source = $request->query('from') ? CardTheme::where('ulid', $request->query('from'))->first() : null;

        $theme = new CardTheme([
            'name' => '',
            'category' => $source->category ?? 'corporate',
            'orientation' => 'landscape',
            'width_mm' => 85.6,
            'height_mm' => 54,
            'palette' => $source->palette ?? ['primary' => '#111827', 'secondary' => '#374151', 'accent' => '#4f46e5', 'background' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280'],
            'fonts' => $source->fonts ?? ['heading' => 'Inter', 'body' => 'Inter'],
            'layout' => ['bleed_mm' => 3, 'safe_mm' => 3, 'corner_radius_mm' => 0],
            'front' => $source->front ?? Scene::emptySide(),
            'back' => $source->back ?? Scene::emptySide('#111827'),
        ]);

        return view('themes.form', $this->formData($theme));
    }

    public function store(ThemeRequest $request): RedirectResponse
    {
        $theme = $this->themes->create($request->validated(), $request->user());

        return redirect()->route('themes.show', $theme)->with('success', "Theme \"{$theme->name}\" created as a draft.");
    }

    public function edit(CardTheme $theme): View
    {
        $this->authorize('update', $theme);

        return view('themes.form', $this->formData($theme));
    }

    public function update(ThemeRequest $request, CardTheme $theme): RedirectResponse
    {
        $this->themes->update($theme, $request->validated());

        return redirect()->route('themes.show', $theme)->with('success', 'Theme saved.');
    }

    public function destroy(CardTheme $theme): RedirectResponse
    {
        $this->authorize('delete', $theme);
        $this->themes->delete($theme);

        return redirect()->route('themes.index')->with('success', "Theme \"{$theme->name}\" deleted.");
    }

    // ---------------------------------------------------------------- customisation

    public function customize(Request $request, CardTheme $theme): View
    {
        $this->authorize('use', $theme);
        $preview = $this->themes->preview($theme);

        return view('themes.customize', [
            'theme' => $theme,
            'preview' => $preview,
            'fontsUrl' => Fonts::googleUrl(array_keys(config('fonts.google'))),
            'fonts' => Fonts::options(),
            'canSaveInPlace' => $request->user()->can('update', $theme),
            'canReset' => $request->user()->can('reset', $theme),
        ]);
    }

    /** "Save as new theme" with the customisation applied. */
    public function storeCustomization(CustomizeThemeRequest $request, CardTheme $theme): RedirectResponse
    {
        $copy = $this->themes->saveAsNew($theme, $request->user(), $request->customization());

        return redirect()->route('themes.show', $copy)->with('success', "Saved as \"{$copy->name}\". You can keep customising it.");
    }

    /** Save customisation into a theme the user may edit. */
    public function updateCustomization(CustomizeThemeRequest $request, CardTheme $theme): RedirectResponse
    {
        $this->themes->applyCustomization($theme, Arr::except($request->customization(), ['name']));

        return redirect()->route('themes.customize', $theme)->with('success', 'Changes saved.');
    }

    /** Live preview for unsaved customisation (JSON with SVG markup). */
    public function preview(CustomizeThemeRequest $request, CardTheme $theme): JsonResponse
    {
        $preview = $this->themes->preview($theme, $request->customization());

        return response()->json(['front' => $preview['front'], 'back' => $preview['back'], 'fontsUrl' => Fonts::googleUrl($preview['fonts'])]);
    }

    /** Standalone SVG preview of one side (fonts embedded), e.g. for downloads and API clients. */
    public function previewSvg(CardTheme $theme, string $side): Response
    {
        $this->authorize('view', $theme);
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $svg = $this->themes->preview($theme, [], ['embed_fonts' => true])[$side];

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src * data:",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    // ---------------------------------------------------------------- actions

    public function duplicate(Request $request, CardTheme $theme): RedirectResponse
    {
        $this->authorize('use', $theme);
        $copy = $this->themes->duplicate($theme, $request->user());

        return redirect()->route('themes.show', $copy)->with('success', "Created \"{$copy->name}\".");
    }

    public function reset(CardTheme $theme): RedirectResponse
    {
        $this->authorize('reset', $theme);
        $this->themes->resetToOriginal($theme);

        return back()->with('success', 'Theme reset to the original design.');
    }

    public function favorite(Request $request, CardTheme $theme): RedirectResponse|JsonResponse
    {
        $this->authorize('use', $theme);
        $favorite = $this->themes->toggleFavorite($theme, $request->user());

        return $request->expectsJson()
            ? response()->json(['favorite' => $favorite])
            : back()->with('success', $favorite ? 'Added to favourites.' : 'Removed from favourites.');
    }

    public function publish(CardTheme $theme): RedirectResponse
    {
        $this->authorize('publish', $theme);
        $this->themes->publish($theme);

        return back()->with('success', "\"{$theme->name}\" is now published.");
    }

    public function unpublish(CardTheme $theme): RedirectResponse
    {
        $this->authorize('publish', $theme);
        $this->themes->unpublish($theme);

        return back()->with('success', "\"{$theme->name}\" is now a draft.");
    }

    private function formData(CardTheme $theme): array
    {
        return [
            'theme' => $theme,
            'categories' => ThemeLibrary::CATEGORIES,
            'fonts' => Fonts::options(),
            'mergeFields' => Scene::MERGE_FIELDS,
        ];
    }
}
