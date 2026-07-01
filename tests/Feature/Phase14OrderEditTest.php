<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 2 — Phase C3: editable order line items (FG-OrderEdit). The riskiest
 * lifecycle action — it reconciles both stock and money — so every branch is
 * covered: the oversell guard on increases, price preservation, the
 * shrink-below-paid block, status guards, audit movements, and tenant isolation.
 */
class Phase14OrderEditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create(['store_name' => 'Test Optical', 'tax_id' => 'GST123', 'address' => 'Mumbai']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
    }

    private function makeItem(float $price = 250, int $stock = 10): Inventory
    {
        return Inventory::create([
            'tenant_id' => $this->user->tenant_id,
            'sku' => 'SKU-' . uniqid(), 'barcode' => (string) random_int(100000000000, 999999999999),
            'item_type' => 'frame', 'brand' => 'Ray-Ban', 'model_name' => 'Aviator',
            'cost_price' => 50, 'selling_price' => $price, 'stock_qty' => $stock, 'min_alert_qty' => 2,
        ]);
    }

    private function placeOrder(Inventory $item, int $qty = 2, float $advance = 0): Order
    {
        $patient = Patient::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Rahul', 'phone' => '+91 90000' . random_int(10000, 99999),
        ]);

        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'patient_id' => $patient->id,
            'advance_paid' => $advance,
            'items' => [['inventory_id' => $item->id, 'quantity' => $qty]],
        ])->assertRedirect();

        return Order::latest()->first();
    }

    private function editWith(Order $order, array $items): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->put(route('tenant.orders.update', $order), ['items' => $items]);
    }

    // ---- Access / status guards ----

    public function test_edit_page_renders_for_an_open_order(): void
    {
        $item = $this->makeItem();
        $order = $this->placeOrder($item);

        $this->actingAs($this->user)->get(route('tenant.orders.edit', $order))
            ->assertOk()->assertSee('Edit order');
    }

    public function test_edit_page_is_blocked_for_delivered_and_cancelled(): void
    {
        $item = $this->makeItem();

        $delivered = $this->placeOrder($item);
        $delivered->update(['status' => 'delivered']);
        $this->actingAs($this->user)->get(route('tenant.orders.edit', $delivered))
            ->assertRedirect(route('tenant.orders.show', $delivered))->assertSessionHas('error');

        $cancelled = $this->placeOrder($item);
        $this->actingAs($this->user)->post(route('tenant.orders.cancel', $cancelled));
        $this->actingAs($this->user)->get(route('tenant.orders.edit', $cancelled))
            ->assertRedirect(route('tenant.orders.show', $cancelled))->assertSessionHas('error');
    }

    public function test_update_is_blocked_for_delivered_order(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2); // stock 8
        $order->update(['status' => 'delivered']);

        $this->editWith($order, [['inventory_id' => $item->id, 'quantity' => 5]])
            ->assertRedirect(route('tenant.orders.show', $order))->assertSessionHas('error');

        $this->assertSame(8, $item->fresh()->stock_qty); // untouched
    }

    // ---- Increasing / decreasing quantity ----

    public function test_increasing_quantity_draws_more_stock_and_logs_edit_movement(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2); // stock 8, total 500
        $this->assertSame(8, $item->fresh()->stock_qty);

        $this->editWith($order, [['inventory_id' => $item->id, 'quantity' => 5]])
            ->assertRedirect(route('tenant.orders.show', $order));

        $this->assertSame(5, $item->fresh()->stock_qty);              // 8 − 3 more
        $this->assertEquals(1250, $order->fresh()->total_amount);      // 5 × 250
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $item->id, 'order_id' => $order->id, 'delta' => -3, 'type' => 'edit',
        ]);
    }

    public function test_decreasing_quantity_restores_stock_and_logs_edit_movement(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 5); // stock 5, total 1250

        $this->editWith($order, [['inventory_id' => $item->id, 'quantity' => 2]])
            ->assertRedirect();

        $this->assertSame(8, $item->fresh()->stock_qty);          // 5 + 3 restored
        $this->assertEquals(500, $order->fresh()->total_amount);   // 2 × 250
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $item->id, 'order_id' => $order->id, 'delta' => 3, 'type' => 'edit',
        ]);
    }

    public function test_removing_a_line_restores_its_stock_and_keeps_the_rest(): void
    {
        $a = $this->makeItem(250, 10);
        $b = $this->makeItem(300, 10);
        $order = $this->placeOrder($a, 2); // stock a=8
        // Add b via edit first (2 items on the order).
        $this->editWith($order, [
            ['inventory_id' => $a->id, 'quantity' => 2],
            ['inventory_id' => $b->id, 'quantity' => 2],
        ])->assertRedirect();
        $this->assertSame(8, $b->fresh()->stock_qty);

        // Now remove b entirely.
        $this->editWith($order, [['inventory_id' => $a->id, 'quantity' => 2]])->assertRedirect();

        $this->assertSame(10, $b->fresh()->stock_qty);  // fully restored
        $this->assertSame(8, $a->fresh()->stock_qty);   // untouched
        $this->assertEquals(500, $order->fresh()->total_amount);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id, 'inventory_id' => $b->id]);
    }

    // ---- Pricing rules ----

    public function test_existing_line_keeps_its_captured_price_when_item_price_changes(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2);

        // Price rises after the order was placed.
        $item->update(['selling_price' => 500]);

        $this->editWith($order, [['inventory_id' => $item->id, 'quantity' => 3]])->assertRedirect();

        // 3 × the *captured* 250, not the new 500.
        $this->assertEquals(750, $order->fresh()->total_amount);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'inventory_id' => $item->id, 'unit_price' => 250,
        ]);
    }

    public function test_newly_added_line_prices_at_current_selling_price(): void
    {
        $a = $this->makeItem(250, 10);
        $b = $this->makeItem(300, 10);
        $order = $this->placeOrder($a, 2); // total 500

        $this->editWith($order, [
            ['inventory_id' => $a->id, 'quantity' => 2],
            ['inventory_id' => $b->id, 'quantity' => 1],
        ])->assertRedirect();

        $this->assertEquals(800, $order->fresh()->total_amount); // 500 + 300
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'inventory_id' => $b->id, 'unit_price' => 300,
        ]);
    }

    // ---- Guards: oversell + shrink below paid + empty ----

    public function test_oversell_on_increase_is_rejected_and_nothing_changes(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2); // stock 8, total 500

        // Need 9 more (11 − 2) but only 8 in stock.
        $this->editWith($order, [['inventory_id' => $item->id, 'quantity' => 11]])
            ->assertSessionHasErrors('items');

        $this->assertSame(8, $item->fresh()->stock_qty);         // unchanged
        $this->assertEquals(500, $order->fresh()->total_amount);  // unchanged
    }

    public function test_increase_up_to_all_available_stock_succeeds(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2); // stock 8

        $this->editWith($order, [['inventory_id' => $item->id, 'quantity' => 10]])->assertRedirect();

        $this->assertSame(0, $item->fresh()->stock_qty); // drew the remaining 8
        $this->assertEquals(2500, $order->fresh()->total_amount);
    }

    public function test_cannot_shrink_total_below_amount_already_paid(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2, 400); // total 500, advance 400, stock 8

        // Drop to 1 → new total 250 < 400 paid.
        $this->editWith($order, [['inventory_id' => $item->id, 'quantity' => 1]])
            ->assertSessionHasErrors('items');

        $this->assertSame(8, $item->fresh()->stock_qty);          // unchanged
        $this->assertEquals(500, $order->fresh()->total_amount);   // unchanged
        $this->assertEquals(400, $order->fresh()->advance_paid);   // untouched
    }

    public function test_an_edit_cannot_empty_the_order(): void
    {
        $item = $this->makeItem();
        $order = $this->placeOrder($item, 2);

        $this->editWith($order, [])->assertSessionHasErrors('items');
    }

    // ---- Money re-sync + advance untouched ----

    public function test_balance_resyncs_and_advance_is_untouched(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2, 100); // total 500, advance 100, balance 400

        $this->editWith($order, [['inventory_id' => $item->id, 'quantity' => 3]])->assertRedirect();

        $order->refresh();
        $this->assertEquals(750, $order->total_amount);
        $this->assertEquals(100, $order->advance_paid); // unchanged
        $this->assertEquals(650, $order->balance_due);  // 750 − 100
    }

    // ---- Tenant isolation ----

    public function test_cannot_edit_or_update_another_tenants_order(): void
    {
        $item = $this->makeItem();
        $order = $this->placeOrder($item, 2);

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->get(route('tenant.orders.edit', $order))->assertNotFound();
        $this->actingAs($otherUser)->put(route('tenant.orders.update', $order), [
            'items' => [['inventory_id' => $item->id, 'quantity' => 1]],
        ])->assertNotFound();

        $this->assertSame(8, $item->fresh()->stock_qty); // unchanged
    }
}
