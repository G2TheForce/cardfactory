<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Models\CustomerImport;
use App\Policies\CardDesignPolicy;
use App\Policies\CustomerImportPolicy;
use App\Services\Batch\SpreadsheetReader;
use App\Services\BatchGenerationService;
use App\Services\CardExportService;
use App\Support\BatchFields;
use App\Support\ProfileThemes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchController extends Controller
{
    public function __construct(
        private readonly BatchGenerationService $batches,
        private readonly CardExportService $exports,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerImport::class);

        return view('imports.index', [
            'imports' => CustomerImportPolicy::visibleTo($request->user())
                ->with(['user:id,name', 'theme:id,name', 'templateDesign:id,name'])
                ->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CustomerImport::class);

        return view('imports.create', [
            'fields' => BatchFields::FIELDS,
            'maxRows' => $this->batches->maxRows(),
            'maxKb' => (int) config('smartcard.imports.max_file_kb'),
        ]);
    }

    public function template(string $format): Response
    {
        $this->authorize('create', CustomerImport::class);

        return response($this->batches->template($format), 200, [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="smartcard-bulk-template.'.$format.'"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CustomerImport::class);
        $request->validate([
            'file' => ['required', 'file', 'max:'.(int) config('smartcard.imports.max_file_kb'),
                'extensions:'.implode(',', SpreadsheetReader::EXTENSIONS), 'mimes:'.implode(',', SpreadsheetReader::EXTENSIONS)],
        ], ['file.extensions' => 'Upload a .csv, .xlsx, .xls or .ods file.', 'file.mimes' => 'Upload a .csv, .xlsx, .xls or .ods file.']);

        $import = $this->batches->upload($request->user(), $request->file('file'));

        return redirect()->route('imports.show', $import)->with('success', 'File uploaded. Check the column matches below.');
    }

    public function show(Request $request, CustomerImport $import): View
    {
        $this->authorize('view', $import);
        $import->load(['theme', 'templateDesign', 'user:id,name']);

        $data = ['import' => $import, 'fields' => BatchFields::FIELDS];

        if (in_array($import->status, ['uploaded', 'validated'], true)) {
            try {
                $sheetRows = $this->batches->sheet($import)['rows'];
            } catch (\RuntimeException $e) {
                $sheetRows = [];
            }
            $data += [
                'samples' => collect($sheetRows)->take(3)->pluck('cells')->all(),
                'themes' => CardTheme::published()->orderBy('name')->get(['id', 'ulid', 'name', 'category']),
                'templates' => CardDesignPolicy::visibleTo($request->user())->whereNull('customer_import_id')
                    ->latest('updated_at')->limit(100)->get(['id', 'ulid', 'name', 'width_mm', 'height_mm']),
                'profileThemes' => ProfileThemes::THEMES,
            ];
            if ($import->status === 'validated') {
                $data['preview'] = collect($this->safeCheck($import)['valid'])->take(BatchGenerationService::PREVIEW_ROWS);
            }
        }

        if ($import->status === 'completed') {
            $data['results'] = $this->batches->results($import)->paginate(50);
            $data['recentExports'] = $import->exports()->where('user_id', $request->user()->id)->latest()->limit(5)->get();
            $data['chrome'] = $this->exports->chromeAvailable();
            $data['ghostscript'] = $this->exports->ghostscript() !== null;
        }

        return view('imports.show', $data);
    }

    public function check(Request $request, CustomerImport $import): RedirectResponse
    {
        $this->authorize('update', $import);
        abort_unless(in_array($import->status, ['uploaded', 'validated'], true), 409, 'This import has already been generated.');

        $columns = count($import->headers ?? []);
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:'.max(0, $columns - 1)],
            'mapping.full_name' => ['required', 'integer', 'min:0', 'max:'.max(0, $columns - 1)],
            'source' => ['required', Rule::in(['theme', 'design'])],
            'card_theme' => ['required_if:source,theme', 'nullable', 'string'],
            'card_design' => ['required_if:source,design', 'nullable', 'string'],
            'create_profiles' => ['nullable', 'boolean'],
            'profile_theme' => ['nullable', Rule::in(array_keys(ProfileThemes::THEMES))],
            'skip_existing' => ['nullable', 'boolean'],
        ], [
            'mapping.full_name.required' => 'Choose the column that holds the full name.',
            'card_theme.required_if' => 'Choose a card theme.',
            'card_design.required_if' => 'Choose a template design.',
        ]);

        $theme = $template = null;
        if ($data['source'] === 'theme') {
            $theme = CardTheme::published()->where('ulid', $data['card_theme'])->first();
            if (! $theme) {
                return back()->withInput()->withErrors(['card_theme' => 'Choose a published card theme.']);
            }
        } else {
            $template = CardDesign::where('ulid', $data['card_design'])->first();
            if (! $template || $request->user()->cannot('view', $template)) {
                return back()->withInput()->withErrors(['card_design' => 'Choose a design you can open.']);
            }
        }

        try {
            $this->batches->configure($import, $data['mapping'], $data, $theme, $template);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->to(route('imports.show', $import).'#review')
            ->with('success', $import->valid_rows.' of '.$import->total_rows.' rows are ready to generate.');
    }

    public function generate(Request $request, CustomerImport $import): RedirectResponse
    {
        $this->authorize('update', $import);
        $this->batches->start($import, $request->user());

        return redirect()->route('imports.show', $import)->with('success', 'Generating cards. You can leave this page; it keeps running.');
    }

    public function status(CustomerImport $import): JsonResponse
    {
        $this->authorize('view', $import);

        return response()->json([
            'status' => $import->status,
            'processed' => $import->processed_rows,
            'generated' => $import->generated_rows,
            'total' => $import->valid_rows,
        ]);
    }

    public function errors(CustomerImport $import): StreamedResponse
    {
        $this->authorize('view', $import);

        return $this->csvDownload($this->batches->errorRows($import), 'import-errors-'.$this->slug($import).'.csv');
    }

    public function info(CustomerImport $import): StreamedResponse
    {
        $this->authorize('view', $import);
        abort_unless($import->status === 'completed', 404);

        return $this->csvDownload($this->batches->infoRows($import), $this->slug($import).'-cards.csv');
    }

    public function export(Request $request, CustomerImport $import): RedirectResponse
    {
        $this->authorize('view', $import);
        abort_unless($import->status === 'completed' && $import->generated_rows > 0, 409, 'Generate the cards first.');

        $data = $request->validate([
            'kind' => ['required', Rule::in(['zip', 'combined'])],
            'format' => ['required_if:kind,zip', 'nullable', Rule::in(array_keys(CardExportService::FORMATS))],
            'sides' => ['required', Rule::in(array_keys(CardExportService::SIDES))],
            'bleed_mm' => ['required', 'numeric', 'min:0', 'max:'.CardExportService::MAX_BLEED],
            'dpi' => ['nullable', 'integer', Rule::in(CardExportService::DPIS)],
            'crop_marks' => ['nullable', 'boolean'],
            'cmyk' => ['nullable', 'boolean'],
        ]);
        $combined = $data['kind'] === 'combined';
        $format = $combined ? 'pdf' : $data['format'];

        if ($format !== 'svg' && ! $this->exports->chromeAvailable()) {
            return back()->withErrors(['format' => 'PNG and PDF exports aren\'t available on this server yet (headless Chrome is missing). SVG works.']);
        }
        if (! empty($data['cmyk']) && $format === 'pdf' && ! $this->exports->ghostscript()) {
            return back()->withErrors(['cmyk' => 'CMYK conversion needs Ghostscript on the server.']);
        }

        $this->exports->requestBatch($import, $request->user(), ['format' => $format] + $data, $combined);

        return redirect()->route('exports.index')->with('success', 'Export started. Large batches take a few minutes; this page updates by itself.');
    }

    public function destroy(Request $request, CustomerImport $import): RedirectResponse
    {
        $this->authorize('delete', $import);
        abort_if($import->status === 'processing', 409, 'Wait for generation to finish before deleting.');
        $this->batches->delete($import, $request->user());

        return redirect()->route('imports.index')->with('success', 'Import deleted. Generated customers, designs and profiles were kept.');
    }

    private function safeCheck(CustomerImport $import): array
    {
        try {
            return $this->batches->check($import);
        } catch (\RuntimeException) {
            return ['valid' => [], 'errors' => [], 'failed' => 0];
        }
    }

    private function csvDownload(iterable $rows, string $name): StreamedResponse
    {
        return response()->streamDownload(fn () => print ($this->exports->csv($rows)), $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function slug(CustomerImport $import): string
    {
        return Str::limit(Str::slug(pathinfo($import->original_filename, PATHINFO_FILENAME)), 50, '') ?: 'import';
    }
}
