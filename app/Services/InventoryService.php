<?php

namespace App\Services;

use App\Models\CardInventory;
use App\Models\CardOrder;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\NfcAssignment;
use App\Models\NfcCard;
use App\Models\User;
use App\Support\ProfileCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NFC stock and orders: products (SKUs), individual NFC cards with their
 * printed identifier and hardware UID, card status (in stock, assigned,
 * active, sold, defective, lost, replaced, retired) and order history.
 * Linking cards to profiles stays in NFCManagementService.
 */
class InventoryService
{
    /** Status groups used for stock figures. */
    public const STOCK_GROUPS = [
        'available' => ['in_stock'],
        'assigned' => ['assigned', 'active'],
        'sold' => ['sold'],
        'defective' => ['defective'],
        'lost' => ['lost', 'replaced'],
        'retired' => ['retired'],
    ];

    /** Statuses staff may set by hand. Assigned/active come from linking a profile. */
    public const MANUAL_STATUSES = ['in_stock', 'sold', 'defective', 'lost', 'retired'];

    public const MAX_BATCH = 500;

    public function __construct(
        private readonly NFCManagementService $nfc,
        private readonly ActivityLogger $logger,
    ) {}

    // ---------------------------------------------------------------- stock

    /** @return array<string, int> group => count, plus "total" */
    public function stockSummary(?CardInventory $product = null): array
    {
        $counts = NfcCard::query()
            ->when($product, fn (Builder $q) => $q->where('card_inventory_id', $product->id))
            ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $summary = ['total' => (int) $counts->sum()];
        foreach (self::STOCK_GROUPS as $group => $statuses) {
            $summary[$group] = (int) collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0);
        }

        return $summary;
    }

    /** Products with per-status counts, for the products table and dashboard. */
    public function productsWithStock(): Collection
    {
        $counts = NfcCard::query()->whereNotNull('card_inventory_id')
            ->selectRaw('card_inventory_id, status, COUNT(*) as aggregate')
            ->groupBy('card_inventory_id', 'status')->get()->groupBy('card_inventory_id');

        return CardInventory::orderBy('name')->get()->each(function (CardInventory $product) use ($counts) {
            $rows = ($counts[$product->id] ?? collect())->pluck('aggregate', 'status');
            $stock = ['total' => (int) $rows->sum()];
            foreach (self::STOCK_GROUPS as $group => $statuses) {
                $stock[$group] = (int) collect($statuses)->sum(fn ($s) => $rows[$s] ?? 0);
            }
            $product->setAttribute('stock', $stock);
            $product->setAttribute('low_stock', $product->is_active && $stock['available'] <= $product->reorder_level);
        });
    }

    // ---------------------------------------------------------------- products

    public function saveProduct(?CardInventory $product, array $data, User $actor): CardInventory
    {
        $isNew = $product === null;
        $product ??= new CardInventory;
        $product->fill($data + ['is_active' => true]);
        $product->sku = strtoupper(trim($data['sku']));
        $product->save();
        $this->logger->log($isNew ? 'inventory.product_created' : 'inventory.product_updated', $product, ($isNew ? 'Added' : 'Updated').' product '.$product->sku, [], $actor->id);

        return $product;
    }

    public function deleteProduct(CardInventory $product, User $actor): void
    {
        if ($product->nfcCards()->exists()) {
            throw ValidationException::withMessages(['product' => 'This product has NFC cards. Mark it inactive instead, or move its cards first.']);
        }
        $this->logger->log('inventory.product_deleted', $product, 'Deleted product '.$product->sku, [], $actor->id);
        $product->delete();
    }

    // ---------------------------------------------------------------- cards

    /**
     * Add cards to stock: either $count new cards with generated identifiers,
     * or one card per UID in $uids (from a reader or the supplier's list).
     *
     * @param  array{card_inventory_id?: ?int, tag_type: string, batch_number?: ?string, notes?: ?string, prefix?: ?string}  $attributes
     * @param  array<int, string>  $uids
     * @return Collection<int, NfcCard>
     *
     * @throws ValidationException
     */
    public function addCards(array $attributes, int $count, array $uids, User $actor): Collection
    {
        $uids = array_values(array_filter(array_map('trim', $uids)));
        $normalised = [];
        foreach ($uids as $i => $uid) {
            try {
                $normalised[] = $this->nfc->normaliseUid($uid);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['uids' => 'Line '.($i + 1).': "'.mb_substr($uid, 0, 40).'" is not a valid UID (4, 7 or 10 bytes of hex).']);
            }
        }
        if (count($normalised) !== count(array_unique($normalised))) {
            throw ValidationException::withMessages(['uids' => 'The list has the same UID more than once.']);
        }
        if ($normalised && ($taken = NfcCard::withTrashed()->whereIn('uid', $normalised)->pluck('uid'))->isNotEmpty()) {
            throw ValidationException::withMessages(['uids' => 'Already in stock: '.$taken->take(5)->implode(', ').($taken->count() > 5 ? '…' : '')]);
        }

        $total = $normalised ? count($normalised) : $count;
        if ($total < 1 || $total > self::MAX_BATCH) {
            throw ValidationException::withMessages(['count' => 'Add between 1 and '.self::MAX_BATCH.' cards at a time.']);
        }

        $prefix = strtoupper(trim((string) ($attributes['prefix'] ?? ''))) ?: 'SC';

        return DB::transaction(function () use ($attributes, $total, $normalised, $prefix, $actor) {
            $cards = collect();
            for ($i = 0; $i < $total; $i++) {
                $cards->push(NfcCard::create([
                    'card_inventory_id' => $attributes['card_inventory_id'] ?? null,
                    'card_identifier' => $this->newIdentifier($prefix),
                    'uid' => $normalised[$i] ?? null,
                    'tag_type' => $attributes['tag_type'],
                    'status' => 'in_stock',
                    'batch_number' => $attributes['batch_number'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                ]));
            }
            $this->logger->log('inventory.cards_added', null, 'Added '.$total.' NFC '.($total === 1 ? 'card' : 'cards').' to stock'.(! empty($attributes['batch_number']) ? ' (batch '.$attributes['batch_number'].')' : ''), ['first' => $cards->first()->card_identifier], $actor->id);

            return $cards;
        });
    }

    /**
     * Edit a card's details. Setting a status that takes the card out of use
     * (defective, lost, retired) unlinks it from its profile, so its URL stops
     * opening the profile straight away.
     *
     * @throws ValidationException
     */
    public function updateCard(NfcCard $card, array $data, User $actor): NfcCard
    {
        return DB::transaction(function () use ($card, $data, $actor) {
            $uid = trim((string) ($data['uid'] ?? ''));
            if ($uid === '') {
                $card->uid = null;
            } elseif ($this->nfc->normaliseUid($uid) !== $card->uid) {
                $normalised = $this->nfc->normaliseUid($uid);
                if (NfcCard::withTrashed()->where('uid', $normalised)->whereKeyNot($card->id)->exists()) {
                    throw ValidationException::withMessages(['uid' => 'Another card already has this UID.']);
                }
                $card->uid = $normalised;
            }
            $card->fill([
                'card_inventory_id' => $data['card_inventory_id'] ?? null,
                'tag_type' => $data['tag_type'],
                'batch_number' => $data['batch_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $status = $data['status'] ?? null;
            $open = $card->currentAssignment;
            if ($status && $status !== $card->status && ! ($open && $status === 'in_stock')) {
                if (! in_array($status, self::MANUAL_STATUSES, true)) {
                    throw ValidationException::withMessages(['status' => 'Assign the card to a profile to make it assigned or active.']);
                }
                if ($open) {
                    $open->update(['status' => 'revoked', 'reason' => 'Card marked '.$status, 'ended_at' => now()]);
                }
                $this->logger->log('inventory.card_status', $card, 'Marked NFC card '.$card->card_identifier.' as '.$status, ['from' => $card->status], $actor->id);
                $card->status = $status;
            }
            $card->save();

            return $card;
        });
    }

    /** @throws ValidationException */
    public function assignToProfile(NfcCard $card, DigitalProfile $profile, bool $move, User $actor): NfcAssignment
    {
        return $this->nfc->assign($profile, ['identifier' => $card->card_identifier, 'move' => $move], $actor);
    }

    /** Filtered card query for the inventory list and CSV. */
    public function cardQuery(array $filters): Builder
    {
        return NfcCard::query()
            ->with(['product:id,sku,name', 'currentAssignment.customer:id,ulid,full_name', 'currentAssignment.digitalProfile:id,ulid,slug'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->whereIn('status', self::STOCK_GROUPS[$s] ?? [$s]))
            ->when($filters['tag_type'] ?? null, fn ($q, $t) => $q->where('tag_type', $t))
            ->when($filters['product'] ?? null, fn ($q, $p) => $q->whereHas('product', fn ($q) => $q->where('sku', $p)))
            ->when($filters['batch'] ?? null, fn ($q, $b) => $q->where('batch_number', $b))
            ->when($filters['search'] ?? null, function ($q, $s) {
                $hex = strtoupper(preg_replace('/[^0-9a-f]/i', '', $s));
                $q->where(fn ($q) => $q->where('card_identifier', 'like', '%'.strtoupper($s).'%')
                    ->when(strlen($hex) >= 4, fn ($q) => $q->orWhereRaw("REPLACE(uid, ':', '') like ?", ['%'.$hex.'%']))
                    ->orWhereHas('currentAssignment.customer', fn ($c) => $c->where('full_name', 'like', "%$s%")));
            });
    }

    /** CSV rows for encoding and stock-taking: one card per row with its tag URL. */
    public function cardRows(Builder $query): \Generator
    {
        yield ['Card ID', 'Tag URL', 'UID', 'Tag type', 'SKU', 'Status', 'Batch', 'Customer', 'Profile URL', 'Encoded at'];
        foreach ($query->lazyById(500) as $card) {
            $a = $card->currentAssignment;
            yield [
                $card->card_identifier, $this->nfc->tagUrl($card), $card->uid, $card->tag_type, $card->product?->sku, $card->status,
                $card->batch_number, $a?->customer?->full_name, $a?->digitalProfile ? route('profiles.public', $a->digitalProfile->slug) : '',
                $card->encoded_at?->toDateTimeString(),
            ];
        }
    }

    // ---------------------------------------------------------------- orders

    /**
     * Create or update an order. Items are [{card_inventory_id, qty, unit_price}];
     * names and SKUs are copied in so history survives product changes.
     */
    public function saveOrder(?CardOrder $order, array $data, User $actor): CardOrder
    {
        $products = CardInventory::withTrashed()->whereIn('id', collect($data['items'])->pluck('card_inventory_id'))->get()->keyBy('id');
        $items = collect($data['items'])->map(function ($item) use ($products) {
            $product = $products[$item['card_inventory_id']];
            $price = isset($item['unit_price']) && $item['unit_price'] !== '' ? (float) $item['unit_price'] : (float) $product->unit_price;

            return [
                'card_inventory_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
                'qty' => (int) $item['qty'], 'unit_price' => round($price, 2), 'line_total' => round($price * (int) $item['qty'], 2),
            ];
        })->values();

        $subtotal = round($items->sum('line_total'), 2);
        $tax = round((float) ($data['tax'] ?? 0), 2);
        $isNew = $order === null;
        $order ??= new CardOrder(['created_by' => $actor->id, 'order_number' => $this->newOrderNumber()]);
        $order->fill([
            'customer_id' => $data['customer_id'] ?? null,
            'items' => $items->all(),
            'quantity' => (int) $items->sum('qty'),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => round($subtotal + $tax, 2),
            'currency' => strtoupper($data['currency'] ?? config('smartcard.currency', 'USD')),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->applyStatus($order, $data['status'] ?? $order->status ?? 'pending');
        $order->save();
        $this->logger->log($isNew ? 'order.created' : 'order.updated', $order, ($isNew ? 'Created' : 'Updated').' order '.$order->order_number, [], $actor->id);

        return $order;
    }

    public function setOrderStatus(CardOrder $order, string $status, User $actor): void
    {
        $from = $order->status;
        $this->applyStatus($order, $status);
        $order->save();
        $this->logger->log('order.status', $order, 'Order '.$order->order_number.': '.$from.' → '.$status, [], $actor->id);
    }

    /**
     * Ship specific cards with an order. Cards in stock become "sold";
     * cards already assigned to the customer's profile keep their status.
     *
     * @param  array<int, string>  $identifiers
     *
     * @throws ValidationException
     */
    public function attachCards(CardOrder $order, array $identifiers, User $actor): int
    {
        $ids = collect($identifiers)->map(fn ($i) => $this->nfc->normaliseIdentifier($i, false))->filter()->unique();
        $cards = NfcCard::whereIn('card_identifier', $ids)->get();
        if ($missing = $ids->diff($cards->pluck('card_identifier'))->first()) {
            throw ValidationException::withMessages(['identifiers' => "Card {$missing} isn't in stock."]);
        }
        $bad = $cards->first(fn ($c) => in_array($c->status, ['defective', 'lost', 'replaced', 'retired'], true));
        if ($bad) {
            throw ValidationException::withMessages(['identifiers' => "Card {$bad->card_identifier} is {$bad->status} and can't be shipped."]);
        }
        $elsewhere = DB::table('card_order_nfc_card')->whereIn('nfc_card_id', $cards->pluck('id'))->where('card_order_id', '!=', $order->id)->value('nfc_card_id');
        if ($elsewhere) {
            throw ValidationException::withMessages(['identifiers' => 'Card '.$cards->firstWhere('id', $elsewhere)->card_identifier.' is already on another order.']);
        }

        DB::transaction(function () use ($order, $cards) {
            $order->nfcCards()->syncWithoutDetaching($cards->pluck('id'));
            NfcCard::whereIn('id', $cards->where('status', 'in_stock')->pluck('id'))->update(['status' => 'sold']);
        });
        $this->logger->log('order.cards_attached', $order, 'Added '.$cards->count().' NFC card(s) to order '.$order->order_number, ['cards' => $cards->pluck('card_identifier')->all()], $actor->id);

        return $cards->count();
    }

    public function detachCard(CardOrder $order, NfcCard $card, User $actor): void
    {
        DB::transaction(function () use ($order, $card) {
            $order->nfcCards()->detach($card->id);
            if ($card->status === 'sold') {
                $card->update(['status' => 'in_stock']);
            }
        });
        $this->logger->log('order.card_detached', $order, 'Removed NFC card '.$card->card_identifier.' from order '.$order->order_number, [], $actor->id);
    }

    /** Orders for a customer (history on the customer page). */
    public function ordersFor(Customer $customer)
    {
        return $customer->orders()->latest()->get();
    }

    private function applyStatus(CardOrder $order, string $status): void
    {
        $order->status = $status;
        if (in_array($status, CardOrder::REVENUE_STATUSES, true)) {
            $order->paid_at ??= now();
        } else {
            $order->paid_at = null;
        }
    }

    private function newIdentifier(string $prefix): string
    {
        do {
            $id = $prefix.'-'.ProfileCodes::random(8);
        } while (NfcCard::withTrashed()->where('card_identifier', $id)->exists());

        return $id;
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('ymd').'-'.ProfileCodes::random(5);
        } while (CardOrder::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }
}
