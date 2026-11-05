<?php

namespace App\Http\Controllers\Designs;

use App\Http\Controllers\Controller;
use App\Models\CardDesign;
use App\Models\CardExport;
use App\Services\CardDesignService;
use App\Services\CardExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly CardExportService $exports) {}

    public function create(CardDesign $design, CardDesignService $designs): View
    {
        $this->authorize('view', $design);
        $design->load('customer.company');

        return view('designs.export', [
            'design' => $design,
            'preview' => $designs->preview($design),
            'chrome' => $this->exports->chromeAvailable(),
            'ghostscript' => $this->exports->ghostscript() !== null,
            'recent' => CardExport::where('card_design_id', $design->id)->where('user_id', auth()->id())->latest()->limit(5)->get(),
        ]);
    }

    public function store(Request $request, CardDesign $design): RedirectResponse
    {
        $this->authorize('view', $design);
        $data = $request->validate([
            'format' => ['required', Rule::in(array_keys(CardExportService::FORMATS))],
            'sides' => ['required', Rule::in(array_keys(CardExportService::SIDES))],
            'bleed_mm' => ['required', 'numeric', 'min:0', 'max:'.CardExportService::MAX_BLEED],
            'dpi' => ['nullable', 'integer', Rule::in(CardExportService::DPIS)],
            'crop_marks' => ['nullable', 'boolean'],
            'transparent' => ['nullable', 'boolean'],
            'round_corners' => ['nullable', 'boolean'],
            'corner_radius_mm' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'guides' => ['nullable', 'boolean'],
            'cmyk' => ['nullable', 'boolean'],
        ]);

        if ($data['format'] !== 'svg' && ! $this->exports->chromeAvailable()) {
            return back()->withInput()->withErrors(['format' => 'PNG and PDF exports aren\'t available on this server yet (headless Chrome is missing). SVG works.']);
        }
        if (! empty($data['cmyk']) && $data['format'] === 'pdf' && ! $this->exports->ghostscript()) {
            return back()->withInput()->withErrors(['cmyk' => 'CMYK conversion needs Ghostscript on the server.']);
        }

        $this->exports->request($design, $request->user(), $data);

        return redirect()->route('exports.index')->with('success', 'Export started. It will be ready to download in a few seconds.');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $exports = CardExport::query()
            ->when(! $user->can('exports.manage') || ! $request->boolean('all'), fn ($q) => $q->where('user_id', $user->id))
            ->with(['cardDesign:id,ulid,name', 'user:id,name'])
            ->latest()->paginate(25)->withQueryString();

        return view('exports.index', [
            'exports' => $exports,
            'showAll' => $user->can('exports.manage') && $request->boolean('all'),
            'canSeeAll' => $user->can('exports.manage'),
        ]);
    }

    /** Status for polling while exports are running. */
    public function status(Request $request): JsonResponse
    {
        $ids = array_slice((array) $request->query('ids', []), 0, 50);
        $exports = CardExport::whereIn('ulid', $ids)->get()->filter(fn ($e) => $this->canAccess($request, $e));

        return response()->json($exports->mapWithKeys(fn ($e) => [$e->ulid => [
            'status' => $e->status,
            'error' => $e->error,
            'size' => $e->file_size,
            'download' => $e->status === 'completed' ? route('exports.download', $e) : null,
        ]]));
    }

    public function download(Request $request, CardExport $export): StreamedResponse
    {
        abort_unless($this->canAccess($request, $export), 403);
        abort_unless($export->status === 'completed' && $export->file_path && Storage::disk($export->disk)->exists($export->file_path), 404);
        $export->increment('downloads_count');

        return Storage::disk($export->disk)->download($export->file_path, $this->exports->downloadName($export));
    }

    public function destroy(Request $request, CardExport $export): RedirectResponse
    {
        abort_unless($this->canAccess($request, $export), 403);
        if ($export->file_path) {
            Storage::disk($export->disk)->deleteDirectory(dirname($export->file_path));
        }
        $export->delete();

        return back()->with('success', 'Export deleted.');
    }

    private function canAccess(Request $request, CardExport $export): bool
    {
        return (int) $export->user_id === (int) $request->user()->id || $request->user()->can('exports.manage');
    }
}
