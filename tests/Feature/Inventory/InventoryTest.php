<?php

namespace Tests\Feature\Inventory;

use App\Models\CardInventory;
use App\Models\CardOrder;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\NfcCard;
use App\Models\User;
use App\Services\DigitalProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function product(array $attributes = []): CardInventory
    {
        return CardInventory::create($attributes + ['sku' => 'NFC-PVC-215', 'name' => 'PVC card', 'tag_type' => 'NTAG215', 'unit_price' => 12.5, 'reorder_level' => 3, 'is_active' => true]);
    }

    private function profile(User $actor): DigitalProfile
    {
        return app(DigitalProfileService::class)->create(Customer::factory()->create(), [], $actor);
    }

    public function test_inventory_pages_need_the_right_permission(): void
    {
        $customer = $this->userWithRole(User::ROLE_CUSTOMER);
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        foreach (['nfc.index', 'inventory.index', 'orders.index'] as $route) {
            $this->actingAs($customer)->get(route($route))->assertForbidden();
            $this->actingAs($designer)->get(route($route))->assertForbidden();
        }

        $sales = $this->userWithRole(User::ROLE_SALES_MANAGER);
        foreach (['nfc.index', 'inventory.index', 'orders.index', 'orders.create', 'inventory.create'] as $route) {
            $this->actingAs($sales)->get(route($route))->assertOk();
        }
    }

    public function test_products_are_managed_by_sku_with_stock_levels(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $this->actingAs($admin)->post(route('inventory.store'), ['sku' => 'nfc-mtl-216', 'name' => 'Metal card', 'tag_type' => 'NTAG216', 'unit_price' => 39, 'reorder_level' => 5, 'is_active' => 1])
            ->assertRedirect(route('inventory.index'));
        $product = CardInventory::where('sku', 'NFC-MTL-216')->firstOrFail();

        $this->post(route('inventory.store'), ['sku' => 'NFC-MTL-216', 'name' => 'Dup', 'tag_type' => 'NTAG216', 'unit_price' => 1, 'reorder_level' => 0])->assertSessionHasErrors('sku');
        $this->post(route('inventory.store'), ['sku' => 'bad sku!', 'name' => 'x', 'tag_type' => 'NTAG999', 'unit_price' => -1, 'reorder_level' => 0])->assertSessionHasErrors(['sku', 'tag_type', 'unit_price']);

        NfcCard::factory()->count(2)->create(['card_inventory_id' => $product->id, 'status' => 'in_stock']);
        NfcCard::factory()->create(['card_inventory_id' => $product->id, 'status' => 'sold']);

        $this->get(route('inventory.index'))->assertOk()->assertSee('NFC-MTL-216')->assertSee('Low stock');
        $this->get(route('inventory.edit', $product))->assertOk();
        $this->assertStringContainsString('/inventory/products/NFC-MTL-216/edit', route('inventory.edit', $product));

        $this->delete(route('inventory.destroy', $product))->assertSessionHasErrors('product');
        $this->assertModelExists($product);
    }

    public function test_cards_are_added_with_generated_ids_or_from_uids(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $product = $this->product();

        $this->actingAs($admin)->post(route('nfc.store'), ['mode' => 'generate', 'count' => 3, 'tag_type' => 'NTAG215', 'product' => $product->sku, 'batch_number' => 'B-1', 'prefix' => 'nw'])
            ->assertRedirect();
        $cards = NfcCard::where('batch_number', 'B-1')->get();
        $this->assertCount(3, $cards);
        $this->assertTrue($cards->every(fn ($c) => preg_match('/^NW-[2-9A-Z]{8}$/', $c->card_identifier) && $c->status === 'in_stock' && $c->card_inventory_id === $product->id));

        $this->post(route('nfc.store'), ['mode' => 'uids', 'uids' => "04a23b1c5d8000\n04:A2:3B:1C:5D:80:01", 'tag_type' => 'NTAG216'])->assertRedirect();
        $this->assertSame(['04:A2:3B:1C:5D:80:00', '04:A2:3B:1C:5D:80:01'], NfcCard::whereNotNull('uid')->orderBy('uid')->pluck('uid')->all());

        $this->post(route('nfc.store'), ['mode' => 'uids', 'uids' => '04:A2:3B:1C:5D:80:00', 'tag_type' => 'NTAG216'])->assertSessionHasErrors('uids');
        $this->post(route('nfc.store'), ['mode' => 'uids', 'uids' => 'nothex', 'tag_type' => 'NTAG216'])->assertSessionHasErrors('uids');
        $this->post(route('nfc.store'), ['mode' => 'generate', 'count' => 501, 'tag_type' => 'NTAG216'])->assertSessionHasErrors('count');
        $this->assertSame(5, NfcCard::count());

        $this->get(route('nfc.index', ['status' => 'available', 'product' => $product->sku]))->assertOk()->assertSee($cards->first()->card_identifier);
        $this->get(route('nfc.index', ['search' => '5d8001']))->assertOk()->assertSee('04:A2:3B:1C:5D:80:01')->assertDontSee(NfcCard::where('uid', '04:A2:3B:1C:5D:80:00')->value('card_identifier'));

        $csv = $this->get(route('nfc.export', ['batch' => 'B-1']))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Card ID","Tag URL"', $csv);
        $this->assertStringContainsString(route('nfc.tap', $cards->first()->card_identifier), $csv);
        $this->assertSame(4, substr_count(trim($csv), "\n") + 1);
    }

    public function test_card_page_assigns_moves_and_takes_cards_out_of_use(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $card = NfcCard::factory()->create(['status' => 'in_stock', 'uid' => null]);
        $first = $this->profile($admin);
        $second = $this->profile($admin);

        $this->actingAs($admin)->get(route('nfc.show', $card))->assertOk()->assertSee(route('nfc.tap', $card->card_identifier));

        $this->post(route('nfc.assign', $card), ['profile' => $first->ulid])->assertSessionHasNoErrors();
        $this->assertSame('assigned', $card->fresh()->status);
        $this->post(route('nfc.assign', $card), ['profile' => $second->ulid])->assertSessionHasErrors('identifier');
        $this->post(route('nfc.assign', $card), ['profile' => $second->ulid, 'move' => 1])->assertSessionHasNoErrors();
        $this->assertSame($second->id, $card->fresh()->currentAssignment->digital_profile_id);

        $this->put(route('nfc.update', $card), ['uid' => '04 a2 3b 1c', 'tag_type' => 'NTAG216', 'status' => 'assigned', 'notes' => 'Front desk'])->assertSessionHasNoErrors();
        $card->refresh();
        $this->assertSame('04:A2:3B:1C', $card->uid);
        $this->assertSame('assigned', $card->status);

        // Marking it lost unlinks it, so the tag stops opening the profile.
        $this->put(route('nfc.update', $card), ['uid' => $card->uid, 'tag_type' => 'NTAG216', 'status' => 'lost'])->assertSessionHasNoErrors();
        $this->assertSame('lost', $card->fresh()->status);
        $this->assertNull($card->fresh()->currentAssignment);
        $this->get(route('nfc.tap', $card->card_identifier))->assertNotFound();
        $this->post(route('nfc.assign', $card), ['profile' => $first->ulid])->assertSessionHasErrors('identifier');

        $this->put(route('nfc.update', $card), ['tag_type' => 'NTAG216', 'status' => 'active'])->assertSessionHasErrors('status');
    }

    public function test_unassign_returns_the_card_to_stock(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $card = NfcCard::factory()->create(['status' => 'in_stock']);
        $this->actingAs($admin)->post(route('nfc.assign', $card), ['profile' => $this->profile($admin)->ulid]);

        $this->delete(route('nfc.unassign', $card))->assertSessionHasNoErrors();
        $this->assertSame('in_stock', $card->fresh()->status);
    }

    public function test_orders_compute_totals_track_payment_and_ship_cards(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $product = $this->product();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->post(route('orders.store'), [
            'customer' => $customer->ulid, 'status' => 'pending', 'currency' => 'usd', 'tax' => 5,
            'items' => [['product' => $product->sku, 'qty' => 4, 'unit_price' => ''], ['product' => $product->sku, 'qty' => 1, 'unit_price' => 20]],
        ])->assertRedirect();
        $order = CardOrder::firstOrFail();
        $this->assertSame('70.00', $order->subtotal);
        $this->assertSame('75.00', $order->total);
        $this->assertSame(5, $order->quantity);
        $this->assertSame('USD', $order->currency);
        $this->assertNull($order->paid_at);
        $this->assertSame('NFC-PVC-215', $order->items[0]['sku']);

        $this->patch(route('orders.status', $order), ['status' => 'paid']);
        $this->assertNotNull($order->fresh()->paid_at);

        $cards = NfcCard::factory()->count(2)->create(['status' => 'in_stock']);
        $lost = NfcCard::factory()->create(['status' => 'lost']);
        $this->post(route('orders.cards.attach', $order), ['identifiers' => $cards->pluck('card_identifier')->implode(', ')])->assertSessionHasNoErrors();
        $this->assertSame(['sold', 'sold'], $cards->map(fn ($c) => $c->fresh()->status)->all());
        $this->post(route('orders.cards.attach', $order), ['identifiers' => $lost->card_identifier])->assertSessionHasErrors('identifiers');
        $this->post(route('orders.cards.attach', $order), ['identifiers' => 'SC-NOPE1234'])->assertSessionHasErrors('identifiers');

        $other = CardOrder::factory()->create(['status' => 'pending']);
        $this->post(route('orders.cards.attach', $other), ['identifiers' => $cards[0]->card_identifier])->assertSessionHasErrors('identifiers');

        $this->delete(route('orders.cards.detach', [$order, $cards[0]]));
        $this->assertSame('in_stock', $cards[0]->fresh()->status);

        $this->get(route('orders.show', $order))->assertOk()->assertSee($cards[1]->card_identifier);
        $this->get(route('customers.show', $customer))->assertOk()->assertSee($order->order_number);
        $this->get(route('nfc.show', $cards[1]))->assertOk()->assertSee($order->order_number);

        $this->delete(route('orders.destroy', $order))->assertStatus(409);
        $this->post(route('orders.store'), ['status' => 'pending', 'currency' => 'USD', 'items' => []])->assertSessionHasErrors('items');
    }

    public function test_dashboard_shows_stock_for_inventory_staff(): void
    {
        $this->product(['reorder_level' => 10]);
        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))->get(route('dashboard'))->assertOk()->assertSee('Stock alerts')->assertSee('NFC-PVC-215');
        $this->actingAs($this->userWithRole(User::ROLE_DESIGNER))->get(route('dashboard'))->assertOk()->assertDontSee('Stock alerts');
    }
}
