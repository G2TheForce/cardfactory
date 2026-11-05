<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\CardInventory;
use App\Models\CardOrder;
use App\Models\Customer;
use App\Models\NfcCard;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** NFC card orders and their shipped cards (routes require orders.manage). */
class OrderController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(CardOrder::STATUSES)],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $orders = CardOrder::with('customer:id,ulid,full_name')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('order_number', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%$s%"))))
            ->latest()->paginate(25)->withQueryString();

        $paid = CardOrder::whereIn('status', CardOrder::REVENUE_STATUSES);

        return view('orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'totals' => [
                'open' => CardOrder::whereIn('status', ['pending', 'paid', 'processing'])->count(),
                'revenue_30' => (float) (clone $paid)->where('paid_at', '>=', now()->subDays(30))->sum('total'),
                'cards_30' => (int) (clone $paid)->where('paid_at', '>=', now()->subDays(30))->sum('quantity'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('orders.form', $this->formData(new CardOrder([
            'status' => 'pending',
            'currency' => config('smartcard.currency', 'USD'),
            'customer_id' => Customer::where('ulid', $request->query('customer'))->value('id'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $order = $this->inventory->saveOrder(null, $this->validated($request), $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Order '.$order->order_number.' created.');
    }

    public function show(CardOrder $order): View
    {
        $order->load(['customer', 'creator:id,name', 'nfcCards.currentAssignment.digitalProfile:id,slug']);

        return view('orders.show', ['order' => $order]);
    }

    public function edit(CardOrder $order): View
    {
        return view('orders.form', $this->formData($order));
    }

    public function update(Request $request, CardOrder $order): RedirectResponse
    {
        $this->inventory->saveOrder($order, $this->validated($request), $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Order updated.');
    }

    public function status(Request $request, CardOrder $order): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(CardOrder::STATUSES)]]);
        $this->inventory->setOrderStatus($order, $data['status'], $request->user());

        return back()->with('success', 'Order marked '.$data['status'].'.');
    }

    public function attachCards(Request $request, CardOrder $order): RedirectResponse
    {
        $data = $request->validate(['identifiers' => ['required', 'string', 'max:20000']]);
        $count = $this->inventory->attachCards($order, preg_split('/[\s,;]+/', trim($data['identifiers'])), $request->user());

        return back()->with('success', $count.' '.str('card')->plural($count).' added to the order.');
    }

    public function detachCard(Request $request, CardOrder $order, NfcCard $card): RedirectResponse
    {
        $this->inventory->detachCard($order, $card, $request->user());

        return back()->with('success', 'Card removed from the order.');
    }

    public function destroy(Request $request, CardOrder $order): RedirectResponse
    {
        abort_if(in_array($order->status, CardOrder::REVENUE_STATUSES, true), 409, 'Paid orders can\'t be deleted. Cancel them instead.');
        $order->nfcCards()->where('status', 'sold')->update(['nfc_cards.status' => 'in_stock']);
        $order->nfcCards()->detach();
        $order->delete();

        return redirect()->route('orders.index')->with('success', 'Order deleted.');
    }

    private function formData(CardOrder $order): array
    {
        return [
            'order' => $order,
            'customers' => Customer::orderBy('full_name')->limit(1000)->get(['id', 'ulid', 'full_name', 'email']),
            'products' => CardInventory::where('is_active', true)->orWhereIn('id', collect($order->items ?? [])->pluck('card_inventory_id'))->orderBy('name')->get(['id', 'sku', 'name', 'unit_price']),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer' => ['nullable', 'string', Rule::exists('customers', 'ulid')->whereNull('deleted_at')],
            'status' => ['required', Rule::in(CardOrder::STATUSES)],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product' => ['required', 'string', Rule::exists('card_inventory', 'sku')],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'tax' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['items.required' => 'Add at least one product.', 'items.*.product.exists' => 'Choose a product.']);

        $skus = CardInventory::withTrashed()->whereIn('sku', collect($data['items'])->pluck('product'))->pluck('id', 'sku');
        $data['items'] = collect($data['items'])->map(fn ($i) => ['card_inventory_id' => $skus[$i['product']], 'qty' => $i['qty'], 'unit_price' => $i['unit_price'] ?? null])->all();
        $data['customer_id'] = isset($data['customer']) ? Customer::where('ulid', $data['customer'])->value('id') : null;

        return $data;
    }
}
