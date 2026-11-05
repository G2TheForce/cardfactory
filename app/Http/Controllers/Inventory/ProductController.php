<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\CardInventory;
use App\Models\NfcCard;
use App\Services\InventoryService;
use App\Services\NFCManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** NFC card products (SKUs) with stock levels (routes require inventory.manage). */
class ProductController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(): View
    {
        return view('inventory.index', [
            'products' => $this->inventory->productsWithStock(),
            'summary' => $this->inventory->stockSummary(),
            'unassignedProduct' => NfcCard::whereNull('card_inventory_id')->count(),
        ]);
    }

    public function create(): View
    {
        return view('inventory.form', ['product' => new CardInventory(['tag_type' => 'NTAG215', 'reorder_level' => 20, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = $this->inventory->saveProduct(null, $this->validated($request), $request->user());

        return redirect()->route('inventory.index')->with('success', 'Product '.$product->sku.' added.');
    }

    public function edit(CardInventory $product): View
    {
        return view('inventory.form', ['product' => $product, 'stock' => $this->inventory->stockSummary($product)]);
    }

    public function update(Request $request, CardInventory $product): RedirectResponse
    {
        $this->inventory->saveProduct($product, $this->validated($request, $product), $request->user());

        return redirect()->route('inventory.index')->with('success', 'Product '.$product->sku.' updated.');
    }

    public function destroy(Request $request, CardInventory $product): RedirectResponse
    {
        $this->inventory->deleteProduct($product, $request->user());

        return redirect()->route('inventory.index')->with('success', 'Product deleted.');
    }

    private function validated(Request $request, ?CardInventory $product = null): array
    {
        $request->merge(['sku' => strtoupper(trim((string) $request->input('sku')))]);

        return $request->validate([
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('card_inventory', 'sku')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:120'],
            'tag_type' => ['required', Rule::in(NFCManagementService::TAG_TYPES)],
            'material' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ], ['sku.regex' => 'Use letters, digits, dots, dashes and underscores.']) + ['cost_price' => 0, 'is_active' => false];
    }
}
