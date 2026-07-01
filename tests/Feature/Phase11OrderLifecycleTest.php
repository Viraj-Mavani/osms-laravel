<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 2 — Phase B: order cancellation (NB-009), payment recording
 * (FG-PaymentLog), and manual stock adjustment (FG-StockLog). Every
 * tenant-owned mutation carries a cross-tenant isolation assertion.
 */
class Phase11OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create(['store_name' => 'Test Optical', 'tax_id' => 'GST123', 'address' => 'Mumbai']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
    }

    private function makeItem(float $price = 100, int $stock = 10): Inventory
    {
        return Inventory::create([
            'tenant_id' => $this->user->tenant_id,
            'sku' => 'SKU-' . uniqid(), 'barcode' => (string) random_int(100000000000, 999999999999),
            'item_type' => 'frame', 'brand' => 'Ray-Ban', 'model_name' => 'Aviator',
            'cost_price' => 50, 'selling_price' => $price, 'stock_qty' => $stock, 'min_alert_qty' => 2,
        ]);
    }

    /** Place an order through the controller so stock + movements + advance are wired. */
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

    // ---- Order placement now logs stock + the initial advance (FG-StockLog / FG-PaymentLog) ----

    public function test_placing_an_order_logs_a_stock_movement_and_initial_advance(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2, 200);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $item->id, 'order_id' => $order->id, 'delta' => -2, 'type' => 'order',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'amount' => 200, 'note' => 'Initial advance',
        ]);
    }

    public function test_placing_an_order_without_advance_records_no_payment(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 1, 0);

        $this->assertDatabaseCount('payments', 0);
    }

    // ---- NB-009: cancel order + restore stock ----

    public function test_cancelling_an_order_restores_stock_and_logs_movement(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 3); // stock now 7
        $this->assertSame(7, $item->fresh()->stock_qty);

        $this->actingAs($this->user)->post(route('tenant.orders.cancel', $order), [
            'cancel_reason' => 'Customer changed mind',
        ])->assertRedirect();

        $order->refresh();
        $this->assertTrue($order->isCancelled());
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(10, $item->fresh()->stock_qty); // restored
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $item->id, 'order_id' => $order->id, 'delta' => 3, 'type' => 'cancel',
        ]);
    }

    public function test_cancelling_is_idempotent_and_does_not_double_restore(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 3);

        $this->actingAs($this->user)->post(route('tenant.orders.cancel', $order))->assertRedirect();
        $this->assertSame(10, $item->fresh()->stock_qty);

        // Second attempt: friendly error, no further restore.
        $this->actingAs($this->user)->post(route('tenant.orders.cancel', $order))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(10, $item->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 2); // one order, one cancel
    }

    public function test_a_delivered_order_cannot_be_cancelled(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2);
        $order->update(['status' => 'delivered']);

        $this->actingAs($this->user)->post(route('tenant.orders.cancel', $order))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(8, $item->fresh()->stock_qty); // unchanged
    }

    public function test_cannot_cancel_another_tenants_order(): void
    {
        $item = $this->makeItem(250, 10);
        $order = $this->placeOrder($item, 2);

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->post(route('tenant.orders.cancel', $order))->assertNotFound();
        $this->assertFalse($order->fresh()->isCancelled());
    }

    // ---- FG-PaymentLog: record payment ----

    public function test_recording_a_payment_reduces_balance_and_logs_it(): void
    {
        $item = $this->makeItem(500, 10);
        $order = $this->placeOrder($item, 1, 100); // total 500, advance 100, balance 400

        $this->actingAs($this->user)->post(route('tenant.orders.payments.store', $order), [
            'amount' => 150, 'method' => 'upi', 'note' => 'Part payment',
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals(250, $order->advance_paid); // 100 + 150
        $this->assertEquals(250, $order->balance_due);   // 500 - 250
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => 150, 'method' => 'upi']);
    }

    public function test_overpayment_is_clamped_to_the_balance(): void
    {
        $item = $this->makeItem(500, 10);
        $order = $this->placeOrder($item, 1, 100); // balance 400

        $this->actingAs($this->user)->post(route('tenant.orders.payments.store', $order), [
            'amount' => 9999, 'method' => 'cash',
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals(500, $order->advance_paid); // capped at total
        $this->assertEquals(0, $order->balance_due);
    }

    public function test_cannot_record_payment_on_cancelled_order(): void
    {
        $item = $this->makeItem(500, 10);
        $order = $this->placeOrder($item, 1, 100);
        $this->actingAs($this->user)->post(route('tenant.orders.cancel', $order))->assertRedirect();

        $this->actingAs($this->user)->post(route('tenant.orders.payments.store', $order), [
            'amount' => 50, 'method' => 'cash',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('payments', ['order_id' => $order->id, 'amount' => 50]);
    }

    public function test_cannot_record_payment_on_another_tenants_order(): void
    {
        $item = $this->makeItem(500, 10);
        $order = $this->placeOrder($item, 1, 100);

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->post(route('tenant.orders.payments.store', $order), [
            'amount' => 50, 'method' => 'cash',
        ])->assertNotFound();
    }

    // ---- FG-StockLog: manual stock adjustment ----

    public function test_adjusting_stock_changes_qty_and_logs_movement(): void
    {
        $this->actingAs($this->user);
        $item = $this->makeItem(100, 10);

        $this->actingAs($this->user)->post(route('tenant.inventory.adjust', $item), [
            'delta' => -3, 'reason' => 'Damaged in transit',
        ])->assertRedirect();

        $this->assertSame(7, $item->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $item->id, 'delta' => -3, 'type' => 'adjustment', 'reason' => 'Damaged in transit',
        ]);
    }

    public function test_adjustment_cannot_drop_stock_below_zero(): void
    {
        $this->actingAs($this->user);
        $item = $this->makeItem(100, 2);

        $this->actingAs($this->user)->post(route('tenant.inventory.adjust', $item), [
            'delta' => -5, 'reason' => 'Recount',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(2, $item->fresh()->stock_qty); // unchanged
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cannot_adjust_another_tenants_stock(): void
    {
        $this->actingAs($this->user);
        $item = $this->makeItem(100, 10);

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->post(route('tenant.inventory.adjust', $item), [
            'delta' => 5, 'reason' => 'Hijack',
        ])->assertNotFound();

        $this->assertSame(10, $item->fresh()->stock_qty);
    }

    public function test_inventory_edit_page_renders_adjust_panel_and_history(): void
    {
        $this->actingAs($this->user);
        $item = $this->makeItem(100, 10);
        $this->actingAs($this->user)->post(route('tenant.inventory.adjust', $item), [
            'delta' => -1, 'reason' => 'Sample damage',
        ])->assertRedirect();

        $this->actingAs($this->user)->get(route('tenant.inventory.edit', $item))
            ->assertOk()
            ->assertSee('Adjust stock')
            ->assertSee('Stock movement history')
            ->assertSee('Sample damage');
    }

    // ---- Payments/movements are tenant-scoped ----

    public function test_payments_and_movements_are_tenant_stamped(): void
    {
        $item = $this->makeItem(500, 10);
        $order = $this->placeOrder($item, 1, 100);

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'tenant_id' => $this->user->tenant_id]);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'tenant_id' => $this->user->tenant_id]);
    }
}
