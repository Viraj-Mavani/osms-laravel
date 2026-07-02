<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Session 3 — FT-Barcode: printable / downloadable Code128 label on the item
 * edit page. Rendering itself is client-side (JsBarcode), so these tests assert
 * the page exposes the panel + controls and the item's SKU/barcode, and that the
 * scan lookup still resolves the barcode value the label encodes.
 */
class Phase15BarcodeLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create(['store_name' => 'Test Optical', 'tax_id' => 'GST123', 'address' => 'Mumbai']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
    }

    private function makeItem(): Inventory
    {
        return Inventory::create([
            'tenant_id' => $this->user->tenant_id,
            'sku' => 'FRM-RAY-001', 'barcode' => '123456789012',
            'item_type' => 'frame', 'brand' => 'Ray-Ban', 'model_name' => 'Aviator',
            'cost_price' => 50, 'selling_price' => 250, 'stock_qty' => 10, 'min_alert_qty' => 2,
        ]);
    }

    public function test_edit_page_exposes_the_barcode_panel_and_controls(): void
    {
        $item = $this->makeItem();

        $this->actingAs($this->user)->get(route('tenant.inventory.edit', $item))
            ->assertOk()
            ->assertSee('Barcode label')
            ->assertSee('barcodeDownload', false) // download control
            ->assertSee('barcodePrint', false)    // print control
            ->assertSee('barcodeSvg', false)      // render target
            ->assertSee($item->sku)               // human-readable label / filename
            ->assertSee($item->barcode);          // value encoded into the symbol
    }

    public function test_scan_resolves_the_barcode_value_the_label_encodes(): void
    {
        $item = $this->makeItem();

        // The label encodes $item->barcode as Code128; scanning it must resolve
        // back to this item (guards the label↔scan round-trip).
        $this->actingAs($this->user)->getJson(route('tenant.inventory.scan', ['q' => $item->barcode]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('item.id', $item->id);
    }

    public function test_barcode_panel_is_tenant_isolated(): void
    {
        $item = $this->makeItem();

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->get(route('tenant.inventory.edit', $item))->assertNotFound();
    }
}
