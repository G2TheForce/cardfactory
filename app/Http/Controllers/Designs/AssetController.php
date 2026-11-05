<?php

namespace App\Http\Controllers\Designs;

use App\Http\Controllers\Controller;
use App\Models\UploadedAsset;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Images and fonts uploaded by the current user for use in designs. */
class AssetController extends Controller
{
    public function __construct(private readonly AssetService $assets) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->validate(['type' => ['nullable', Rule::in(['font', ...AssetService::IMAGE_TYPES])]])['type'] ?? null;

        $items = $request->user()->uploadedAssets()
            ->when($type, fn ($q, $t) => $q->where('type', $t), fn ($q) => $q->where('type', '!=', 'font'))
            ->latest()->limit(200)->get();

        return response()->json($items->map(fn ($a) => $this->assets->toArray($a)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.AssetService::MAX_IMAGE_KB, 'dimensions:max_width=12000,max_height=12000'],
            'type' => ['nullable', Rule::in(AssetService::IMAGE_TYPES)],
        ]);

        $asset = $this->assets->storeImage($request->user(), $data['file'], $data['type'] ?? 'image');

        return response()->json($this->assets->toArray($asset), 201);
    }

    public function storeFont(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:ttf,otf,woff,woff2', 'max:'.AssetService::MAX_FONT_KB],
            'family' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9 \-]+$/'],
        ], [
            'family.regex' => 'Font names may only contain letters, numbers, spaces and dashes.',
        ]);

        $asset = $this->assets->storeFont($request->user(), $data['file'], trim($data['family']));

        return response()->json($this->assets->toArray($asset), 201);
    }

    public function destroy(Request $request, UploadedAsset $asset): JsonResponse
    {
        abort_unless($asset->user_id === $request->user()->id, 403);
        $this->assets->delete($asset);

        return response()->json(['deleted' => true]);
    }
}
