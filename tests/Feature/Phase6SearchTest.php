<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6SearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create(['store_name' => 'Test Optical']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
    }

    public function test_search_finds_patients_inventory_orders(): void
    {
        $this->actingAs($this->user);
        $patient = Patient::create(['name' => 'Anjali Verma', 'phone' => '9991112222']);
        Inventory::create(['sku' => 'FRM-RAYBA-XYZ999', 'barcode' => '111122223333', 'item_type' => 'frame',
            'brand' => 'Ray-Ban', 'model_name' => 'Wayfarer', 'cost_price' => 1, 'selling_price' => 2, 'stock_qty' => 5, 'min_alert_qty' => 1]);
        Order::create(['patient_id' => $patient->id, 'status' => 'pending', 'total_amount' => 500, 'advance_paid' => 0]);

        $this->actingAs($this->user)->getJson(route('tenant.search', ['q' => 'Anjali']))
            ->assertOk()
            ->assertJsonCount(1, 'patients')
            ->assertJsonCount(1, 'orders');

        $this->actingAs($this->user)->getJson(route('tenant.search', ['q' => 'Wayfarer']))
            ->assertOk()->assertJsonCount(1, 'inventory');

        $this->actingAs($this->user)->getJson(route('tenant.search', ['q' => '9991112222']))
            ->assertOk()->assertJsonCount(1, 'patients');
    }

    public function test_empty_query_returns_empty(): void
    {
        $this->actingAs($this->user)->getJson(route('tenant.search', ['q' => '']))
            ->assertOk()->assertExactJson(['patients' => [], 'inventory' => [], 'orders' => []]);
    }

    public function test_search_respects_tenant_isolation(): void
    {
        $this->actingAs($this->user);
        Patient::create(['name' => 'Secret Patient', 'phone' => '123']);

        $other = Tenant::create(['store_name' => 'Other']);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => 'store_admin']);

        $this->actingAs($otherUser)->getJson(route('tenant.search', ['q' => 'Secret']))
            ->assertOk()->assertJsonCount(0, 'patients');
    }
}
