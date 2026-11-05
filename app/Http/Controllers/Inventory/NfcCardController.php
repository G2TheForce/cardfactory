<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\CardInventory;
use App\Models\DigitalProfile;
use App\Models\NfcAssignment;
use App\Models\NfcCard;
use App\Services\CardExportService;
use App\Services\InventoryService;
use App\Services\NFCManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** NFC card inventory: stock, UIDs, status and assignments (routes require nfc.manage). */
class NfcCardController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly NFCManagementService $nfc,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('nfc.index', [
            'cards' => $this->inventory->cardQuery($filters)->latest('id')->paginate(30)->withQueryString(),
            'summary' => $this->inventory->stockSummary(),
            'products' => CardInventory::orderBy('name')->get(['id', 'sku', 'name', 'tag_type']),
            'batches' => NfcCard::whereNotNull('batch_number')->distinct()->orderBy('batch_number')->limit(100)->pluck('batch_number'),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['generate', 'uids'])],
            'count' => ['required_if:mode,generate', 'nullable', 'integer', 'min:1', 'max:'.InventoryService::MAX_BATCH],
            'uids' => ['required_if:mode,uids', 'nullable', 'string', 'max:20000'],
            'product' => ['nullable', 'string', Rule::exists('card_inventory', 'sku')->whereNull('deleted_at')],
            'tag_type' => ['required', Rule::in(NFCManagementService::TAG_TYPES)],
            'batch_number' => ['nullable', 'string', 'max:64'],
            'prefix' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{1,6}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['prefix.regex' => 'Use 1–6 letters or digits.', 'uids.required_if' => 'Paste at least one UID, one per line.']);

        $product = isset($data['product']) ? CardInventory::where('sku', $data['product'])->first() : null;
        $uids = $data['mode'] === 'uids' ? preg_split('/\r\n|\r|\n|,|;/', (string) $data['uids']) : [];
        $cards = $this->inventory->addCards([
            'card_inventory_id' => $product?->id,
            'tag_type' => $data['tag_type'],
            'batch_number' => $data['batch_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'prefix' => $data['prefix'] ?? null,
        ], (int) ($data['count'] ?? 0), $uids, $request->user());

        return redirect()->route('nfc.index', array_filter(['batch' => $data['batch_number'] ?? null]))
            ->with('success', 'Added '.$cards->count().' '.str('card')->plural($cards->count()).' to stock. Download the CSV to encode them.');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->inventory->cardRows($this->inventory->cardQuery($this->filters($request))->orderBy('id'));

        return response()->streamDownload(fn () => print (app(CardExportService::class)->csv($rows)), 'nfc-cards-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(NfcCard $card): View
    {
        $card->load(['product', 'currentAssignment.customer', 'currentAssignment.digitalProfile']);

        return view('nfc.show', [
            'card' => $card,
            'tagUrl' => $this->nfc->tagUrl($card),
            'history' => NfcAssignment::where('nfc_card_id', $card->id)->with(['customer:id,ulid,full_name', 'digitalProfile:id,ulid,slug', 'assignedBy:id,name'])->latest('assigned_at')->get(),
            'orders' => $card->orders()->with('customer:id,ulid,full_name')->latest()->get(),
            'products' => CardInventory::orderBy('name')->get(['id', 'sku', 'name']),
            'profiles' => DigitalProfile::with('customer:id,full_name')->where('is_active', true)->orderBy('slug')->limit(500)->get(['id', 'ulid', 'slug', 'customer_id']),
        ]);
    }

    public function update(Request $request, NfcCard $card): RedirectResponse
    {
        $data = $request->validate([
            'uid' => ['nullable', 'string', 'max:40'],
            'tag_type' => ['required', Rule::in(NFCManagementService::TAG_TYPES)],
            'product' => ['nullable', 'string', Rule::exists('card_inventory', 'sku')],
            'batch_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(NfcCard::STATUSES)],
        ]);
        $data['card_inventory_id'] = isset($data['product']) ? CardInventory::where('sku', $data['product'])->value('id') : null;
        $this->inventory->updateCard($card, $data, $request->user());

        return back()->with('success', 'Card '.$card->card_identifier.' updated.');
    }

    public function assign(Request $request, NfcCard $card): RedirectResponse
    {
        $data = $request->validate(['profile' => ['required', 'string'], 'move' => ['nullable', 'boolean']]);
        $profile = DigitalProfile::where('ulid', $data['profile'])->first();
        if (! $profile) {
            return back()->withErrors(['profile' => 'Choose a digital profile.']);
        }
        $this->inventory->assignToProfile($card, $profile, (bool) ($data['move'] ?? false), $request->user());

        return back()->with('success', 'Card '.$card->card_identifier.' now opens /card/'.$profile->slug.'.');
    }

    public function unassign(Request $request, NfcCard $card): RedirectResponse
    {
        $assignment = $card->currentAssignment;
        abort_unless($assignment && $assignment->digitalProfile, 404);
        $this->nfc->unassign($assignment->digitalProfile, $card, $request->user());

        return back()->with('success', 'Card '.$card->card_identifier.' unlinked and back in stock.');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(array_merge(array_keys(InventoryService::STOCK_GROUPS), NfcCard::STATUSES))],
            'tag_type' => ['nullable', Rule::in(NFCManagementService::TAG_TYPES)],
            'product' => ['nullable', 'string', 'max:64'],
            'batch' => ['nullable', 'string', 'max:64'],
        ]);
    }
}
