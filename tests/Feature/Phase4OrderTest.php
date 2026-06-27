<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4OrderTest extends TestCase
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
            'sku' => 'SKU-' . uniqid(), 'barcode' => (string) random_int(100000000000, 999999999999),
            'item_type' => 'frame', 'brand' => 'Ray-Ban', 'model_name' => 'Aviator',
            'cost_price' => 50, 'selling_price' => $price, 'stock_qty' => $stock, 'min_alert_qty' => 2,
        ]);
    }

    public function test_order_pages_render(): void
    {
        $this->actingAs($this->user)->get(route('tenant.orders.index'))->assertOk();
        $this->actingAs($this->user)->get(route('tenant.orders.create'))->assertOk();
    }

    public function test_can_create_order_with_items_and_balance_computed(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $item = $this->makeItem(250, 10);

        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'patient_id' => $patient->id,
            'advance_paid' => 200,
            'items' => [
                ['inventory_id' => $item->id, 'quantity' => 2],
            ],
        ])->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEquals(500, $order->total_amount);   // 250 * 2
        $this->assertEquals(200, $order->advance_paid);
        $this->assertEquals(300, $order->balance_due);     // 500 - 200
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_creating_an_order_decrements_stock(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $item = $this->makeItem(250, 10);

        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'patient_id' => $patient->id,
            'items' => [['inventory_id' => $item->id, 'quantity' => 3]],
        ])->assertRedirect();

        $this->assertSame(7, $item->fresh()->stock_qty); // 10 - 3
    }

    public function test_overselling_is_rejected_and_nothing_is_written(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $item = $this->makeItem(250, 2);

        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'patient_id' => $patient->id,
            'items' => [['inventory_id' => $item->id, 'quantity' => 5]],
        ])->assertSessionHasErrors('items');

        // Transaction rolled back: no order, no items, stock untouched.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(2, $item->fresh()->stock_qty);
    }

    public function test_overselling_via_duplicate_lines_is_rejected(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $item = $this->makeItem(250, 3);

        // Two lines for the same item that together exceed stock (3): 2 + 2 = 4.
        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'patient_id' => $patient->id,
            'items' => [
                ['inventory_id' => $item->id, 'quantity' => 2],
                ['inventory_id' => $item->id, 'quantity' => 2],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $item->fresh()->stock_qty);
    }

    public function test_unit_price_is_resolved_server_side(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $item = $this->makeItem(999, 10);

        // Client sends only inventory_id + quantity; server uses the real price.
        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'patient_id' => $patient->id,
            'items' => [['inventory_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();

        $this->assertEquals(999, Order::first()->total_amount);
    }

    public function test_cannot_attach_another_tenants_eye_record_to_an_order(): void
    {
        // Another tenant with its own patient + eye record.
        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);
        $this->actingAs($otherUser);
        $otherPatient = Patient::create(['name' => 'Theirs', 'phone' => '222']);
        $foreignRecord = $otherPatient->eyeRecords()->create(['tenant_id' => $other->id, 'od_sph' => -1]);

        // Our tenant tries to reference the foreign eye record on a new order.
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Ours', 'phone' => '111']);
        $item = $this->makeItem(100, 10);

        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'patient_id' => $patient->id,
            'eye_record_id' => $foreignRecord->id,
            'items' => [['inventory_id' => $item->id, 'quantity' => 1]],
        ])->assertNotFound();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_requires_items(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);

        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'patient_id' => $patient->id, 'items' => [],
        ])->assertSessionHasErrors('items');
    }

    public function test_status_transition(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $order = Order::create(['patient_id' => $patient->id, 'status' => 'pending', 'total_amount' => 100, 'advance_paid' => 0]);

        $this->actingAs($this->user)->patch(route('tenant.orders.status', $order), ['status' => 'ready_for_pickup'])
            ->assertRedirect();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
    }

    public function test_receipt_and_pdf_render(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $item = $this->makeItem(100, 10);
        $order = Order::create(['patient_id' => $patient->id, 'status' => 'pending', 'total_amount' => 100, 'advance_paid' => 0]);
        $order->items()->create(['inventory_id' => $item->id, 'quantity' => 1, 'unit_price' => 100]);

        $this->actingAs($this->user)->get(route('tenant.orders.show', $order))
            ->assertOk()->assertSee('Order receipt')->assertSee('Test Optical');

        $res = $this->actingAs($this->user)->get(route('tenant.orders.pdf', $order));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    public function test_cannot_view_another_tenants_order(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $order = Order::create(['patient_id' => $patient->id, 'status' => 'pending', 'total_amount' => 100, 'advance_paid' => 0]);

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->get(route('tenant.orders.show', $order))->assertNotFound();
    }

    public function test_eye_records_json_endpoint(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '111']);
        $patient->eyeRecords()->create(['tenant_id' => $this->user->tenant_id, 'od_sph' => -1]);

        $this->actingAs($this->user)->getJson(route('tenant.patients.eye-records', $patient))
            ->assertOk()->assertJsonCount(1);
    }
}
