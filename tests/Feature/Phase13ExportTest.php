<?php

namespace Tests\Feature;

use App\Exports\InventoryExport;
use App\Exports\PatientsExport;
use App\Models\Inventory;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Section 2 — Phase C2: inventory + patient XLSX exports (FG-Export). Exports
 * honour the active index filters, stay tenant-scoped via the global scope,
 * and exclude archived (soft-deleted) rows.
 */
class Phase13ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create(['store_name' => 'Test Optical', 'tax_id' => 'GST123', 'address' => 'Mumbai']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
    }

    private function makeItem(array $attrs = []): Inventory
    {
        return Inventory::create(array_merge([
            'tenant_id' => $this->user->tenant_id,
            'sku' => 'SKU-' . uniqid(), 'barcode' => (string) random_int(100000000000, 999999999999),
            'item_type' => 'frame', 'brand' => 'Ray-Ban', 'model_name' => 'Aviator',
            'cost_price' => 50, 'selling_price' => 250, 'stock_qty' => 10, 'min_alert_qty' => 2,
        ], $attrs));
    }

    private function makePatient(string $name = 'Rahul'): Patient
    {
        return Patient::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => $name, 'phone' => '+91 90000' . random_int(10000, 99999),
        ]);
    }

    // ---- Inventory export ----

    public function test_inventory_export_is_tenant_scoped(): void
    {
        $this->makeItem();
        $this->makeItem();

        // An item owned by another tenant must never appear.
        $other = Tenant::create(['store_name' => 'Other']);
        Inventory::create([
            'tenant_id' => $other->id,
            'sku' => 'OTHER-SKU', 'barcode' => '111122223333',
            'item_type' => 'frame', 'brand' => 'Oakley', 'model_name' => 'Holbrook',
            'cost_price' => 50, 'selling_price' => 250, 'stock_qty' => 5, 'min_alert_qty' => 2,
        ]);

        $this->actingAs($this->user);
        $this->assertCount(2, (new InventoryExport())->collection());
    }

    public function test_inventory_export_honours_type_and_stock_filters(): void
    {
        $this->makeItem(['item_type' => 'frame', 'stock_qty' => 10]);
        $this->makeItem(['item_type' => 'lens', 'stock_qty' => 1, 'min_alert_qty' => 2]); // low
        $this->makeItem(['item_type' => 'lens', 'stock_qty' => 0]);                        // out + lens

        $this->actingAs($this->user);

        $this->assertCount(2, (new InventoryExport(type: 'lens'))->collection());
        $this->assertCount(2, (new InventoryExport(stock: 'low'))->collection()); // qty<=min: the lens(1) and lens(0)
        $this->assertCount(1, (new InventoryExport(stock: 'out'))->collection());
    }

    public function test_inventory_export_honours_search(): void
    {
        $this->makeItem(['brand' => 'Ray-Ban', 'model_name' => 'Aviator']);
        $this->makeItem(['brand' => 'Oakley', 'model_name' => 'Holbrook']);

        $this->actingAs($this->user);
        $rows = (new InventoryExport(q: 'Holbrook'))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('Holbrook', $rows->first()->model_name);
    }

    public function test_inventory_export_excludes_archived_items(): void
    {
        $this->makeItem();
        $archived = $this->makeItem();
        $archived->delete();

        $this->actingAs($this->user);
        $this->assertCount(1, (new InventoryExport())->collection());
    }

    // ---- Patients export ----

    public function test_patients_export_is_tenant_scoped_and_searchable(): void
    {
        $this->makePatient('Alice');
        $this->makePatient('Bob');

        $other = Tenant::create(['store_name' => 'Other']);
        Patient::create(['tenant_id' => $other->id, 'name' => 'Zara', 'phone' => '+91 9111100000']);

        $this->actingAs($this->user);

        $this->assertCount(2, (new PatientsExport())->collection());
        $this->assertCount(1, (new PatientsExport(q: 'Alice'))->collection());
    }

    public function test_patients_export_excludes_archived(): void
    {
        $this->makePatient('Keep');
        $gone = $this->makePatient('Gone');
        $gone->delete();

        $this->actingAs($this->user);
        $this->assertCount(1, (new PatientsExport())->collection());
    }

    // ---- Download routes ----

    public function test_inventory_export_route_downloads_xlsx(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');
        Excel::fake();
        $this->makeItem();

        $this->actingAs($this->user)->get(route('tenant.inventory.export'))->assertOk();

        Excel::assertDownloaded('inventory-20260701-120000.xlsx');
        Carbon::setTestNow();
    }

    public function test_patients_export_route_downloads_xlsx(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');
        Excel::fake();
        $this->makePatient();

        $this->actingAs($this->user)->get(route('tenant.patients.export'))->assertOk();

        Excel::assertDownloaded('patients-20260701-120000.xlsx');
        Carbon::setTestNow();
    }
}
