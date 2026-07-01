<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Section 2 — Phase C1: soft-delete + 30-day archive (FG-Delete) for patients
 * and inventory. Every tenant-owned mutation carries a cross-tenant isolation
 * assertion; the open-order guard and the purge window are exercised directly.
 */
class Phase12SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create(['store_name' => 'Test Optical', 'tax_id' => 'GST123', 'address' => 'Mumbai']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
    }

    private function makePatient(string $name = 'Rahul'): Patient
    {
        return Patient::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => $name, 'phone' => '+91 90000' . random_int(10000, 99999),
        ]);
    }

    private function makeItem(int $stock = 10): Inventory
    {
        return Inventory::create([
            'tenant_id' => $this->user->tenant_id,
            'sku' => 'SKU-' . uniqid(), 'barcode' => (string) random_int(100000000000, 999999999999),
            'item_type' => 'frame', 'brand' => 'Ray-Ban', 'model_name' => 'Aviator',
            'cost_price' => 50, 'selling_price' => 250, 'stock_qty' => $stock, 'min_alert_qty' => 2,
        ]);
    }

    private function orderFor(Inventory $item, string $status = 'pending'): Order
    {
        $patient = $this->makePatient('Ordering Pat');
        $order = Order::create([
            'tenant_id' => $this->user->tenant_id,
            'patient_id' => $patient->id, 'status' => $status,
            'total_amount' => 250, 'advance_paid' => 0,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'inventory_id' => $item->id,
            'quantity' => 1, 'unit_price' => 250,
        ]);

        return $order;
    }

    // ---- Patients ----

    public function test_archiving_a_patient_soft_deletes_it(): void
    {
        $patient = $this->makePatient();

        $this->actingAs($this->user)->delete(route('tenant.patients.destroy', $patient))
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
        $this->assertSame(0, Patient::count());               // hidden from normal queries
        $this->assertSame(1, Patient::onlyTrashed()->count()); // present in the archive
    }

    public function test_archiving_a_patient_with_orders_is_blocked(): void
    {
        $item = $this->makeItem();
        $order = $this->orderFor($item);
        $patient = $order->patient;

        $this->actingAs($this->user)->delete(route('tenant.patients.destroy', $patient))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNotSoftDeleted('patients', ['id' => $patient->id]);
    }

    public function test_restoring_a_patient_brings_it_back(): void
    {
        $patient = $this->makePatient();
        $patient->delete();

        $this->actingAs($this->user)->patch(route('tenant.patients.restore', $patient))
            ->assertRedirect();

        $this->assertNotSoftDeleted('patients', ['id' => $patient->id]);
        $this->assertSame(1, Patient::count());
    }

    public function test_force_deleting_a_patient_removes_it_permanently(): void
    {
        $patient = $this->makePatient();
        $patient->delete();

        $this->actingAs($this->user)->delete(route('tenant.patients.force-delete', $patient))
            ->assertRedirect();

        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_cannot_archive_or_restore_another_tenants_patient(): void
    {
        $patient = $this->makePatient();

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->delete(route('tenant.patients.destroy', $patient))->assertNotFound();
        $this->assertNotSoftDeleted('patients', ['id' => $patient->id]);

        $patient->delete();
        $this->actingAs($otherUser)->patch(route('tenant.patients.restore', $patient))->assertNotFound();
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    // ---- Inventory ----

    public function test_archiving_an_item_soft_deletes_it(): void
    {
        $item = $this->makeItem();

        $this->actingAs($this->user)->delete(route('tenant.inventory.destroy', $item))
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSoftDeleted('inventory', ['id' => $item->id]);
    }

    public function test_archiving_an_item_on_an_open_order_is_blocked(): void
    {
        $item = $this->makeItem();
        $this->orderFor($item, 'pending');

        $this->actingAs($this->user)->delete(route('tenant.inventory.destroy', $item))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNotSoftDeleted('inventory', ['id' => $item->id]);
    }

    public function test_archiving_an_item_on_only_closed_orders_is_allowed(): void
    {
        $item = $this->makeItem();
        $this->orderFor($item, 'delivered');

        $this->actingAs($this->user)->delete(route('tenant.inventory.destroy', $item))
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSoftDeleted('inventory', ['id' => $item->id]);
    }

    public function test_restore_and_force_delete_inventory(): void
    {
        $item = $this->makeItem();
        $item->delete();

        $this->actingAs($this->user)->patch(route('tenant.inventory.restore', $item))->assertRedirect();
        $this->assertNotSoftDeleted('inventory', ['id' => $item->id]);

        $item->delete();
        $this->actingAs($this->user)->delete(route('tenant.inventory.force-delete', $item))->assertRedirect();
        $this->assertDatabaseMissing('inventory', ['id' => $item->id]);
    }

    public function test_cannot_archive_another_tenants_item(): void
    {
        $item = $this->makeItem();

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->delete(route('tenant.inventory.destroy', $item))->assertNotFound();
        $this->assertNotSoftDeleted('inventory', ['id' => $item->id]);
    }

    // ---- Purge command (30-day window) ----

    public function test_purge_removes_records_past_the_window_and_keeps_recent(): void
    {
        $old = $this->makePatient('Old');
        $recent = $this->makePatient('Recent');
        $oldItem = $this->makeItem();

        foreach ([$old, $recent, $oldItem] as $m) {
            $m->delete();
        }

        // Age two of them past the 30-day cutoff (bypass model events).
        DB::table('patients')->where('id', $old->id)->update(['deleted_at' => now()->subDays(40)]);
        DB::table('inventory')->where('id', $oldItem->id)->update(['deleted_at' => now()->subDays(31)]);

        $this->artisan('model:purge-trashed')->assertSuccessful();

        $this->assertDatabaseMissing('patients', ['id' => $old->id]);      // purged
        $this->assertDatabaseMissing('inventory', ['id' => $oldItem->id]); // purged
        $this->assertSoftDeleted('patients', ['id' => $recent->id]);       // still within window
    }

    // ---- Archive views render ----

    public function test_archive_views_render(): void
    {
        $p = $this->makePatient();
        $p->delete();
        $i = $this->makeItem();
        $i->delete();

        $this->actingAs($this->user)->get(route('tenant.patients.trash'))
            ->assertOk()->assertSee('Archive')->assertSee($p->name);
        $this->actingAs($this->user)->get(route('tenant.inventory.trash'))
            ->assertOk()->assertSee('Archive')->assertSee($i->model_name);
    }
}
