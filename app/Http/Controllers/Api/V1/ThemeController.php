<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardThemeResource;
use App\Models\CardTheme;
use App\Services\ThemeManagementService;
use App\Support\Themes\ThemeLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(ThemeLibrary::CATEGORIES))],
            'favorites' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();

        $themes = CardTheme::query()
            ->where(fn ($q) => $q->where('status', CardTheme::STATUS_PUBLISHED)->orWhere('created_by', $user->id))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->when($request->boolean('favorites'), fn ($q) => $q->whereHas('favoritedBy', fn ($f) => $f->whereKey($user->id)))
            ->withExists(['favoritedBy as is_favorite' => fn ($f) => $f->whereKey($user->id)])
            ->orderBy('sort_order')
            ->paginate($filters['per_page'] ?? 25);

        return CardThemeResource::collection($themes);
    }

    public function show(Request $request, CardTheme $theme): CardThemeResource
    {
        $this->authorize('view', $theme);

        return new CardThemeResource($theme->load('parent:id,ulid'));
    }

    public function favorite(Request $request, CardTheme $theme, ThemeManagementService $service): JsonResponse
    {
        $this->authorize('use', $theme);

        return response()->json(['data' => ['favorite' => $service->toggleFavorite($theme, $request->user())]]);
    }
}
