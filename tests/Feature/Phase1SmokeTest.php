<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase1SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_registration_routes_to_onboarding(): void
    {
        $this->post('/register', [
            'name' => 'Jane Optician',
            'email' => 'jane@store.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('onboarding.create'));

        $user = User::where('email', 'jane@store.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('store_admin', $user->role);
        $this->assertNull($user->tenant_id);
    }

    public function test_onboarding_creates_tenant_trial_and_lands_on_dashboard(): void
    {
        $user = User::factory()->create(['tenant_id' => null, 'role' => 'store_admin']);

        $this->actingAs($user)
            ->post('/onboarding', [
                'store_name' => 'Sahaj Optical',
                'tax_id' => '22AAAAA0000A1Z5',
                'address' => 'Mumbai',
            ])
            ->assertRedirect(route('tenant.dashboard'));

        $user->refresh();
        $this->assertNotNull($user->tenant_id);

        $tenant = Tenant::find($user->tenant_id);
        $this->assertSame('Sahaj Optical', $tenant->store_name);
        $this->assertNotNull($tenant->subscription);
        $this->assertSame('trialing', $tenant->subscription->status);

        $this->actingAs($user)->get('/tenant')->assertOk()->assertSee('Sahaj Optical');
    }

    public function test_un_onboarded_user_is_redirected_from_tenant_area(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($user)->get('/tenant')->assertRedirect(route('onboarding.create'));
    }

    public function test_tenant_isolation_between_stores(): void
    {
        $tenantA = Tenant::create(['store_name' => 'Store A']);
        $tenantB = Tenant::create(['store_name' => 'Store B']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'store_admin']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'store_admin']);

        $this->actingAs($userA);
        Customer::create(['name' => 'Patient A', 'phone' => '111']);

        // User B must not see Store A's data through the global scope.
        $this->actingAs($userB);
        $this->assertSame(0, Customer::count());

        $this->actingAs($userA);
        $this->assertSame(1, Customer::count());
    }

    public function test_non_superadmin_blocked_from_platform_panel(): void
    {
        $user = User::factory()->create(['tenant_id' => null, 'role' => 'store_admin']);
        $this->actingAs($user)->get('/superadmin')->assertForbidden();
    }

    /**
     * Every tenant GET route must respond without a 500. This is the safety net
     * for large renames (e.g. patients → customers): a missed route() reference
     * fails at runtime, not at compile time — this sweep catches it.
     */
    public function test_all_tenant_get_routes_respond_without_error(): void
    {
        $tenant = Tenant::create(['store_name' => 'Smoke Optical', 'tax_id' => 'GST1', 'address' => 'Mumbai']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
        $this->actingAs($user);

        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Smoke Cust', 'phone' => '+91 9000000001']);
        $record = $customer->eyeRecords()->create(['tenant_id' => $tenant->id, 'recorded_by' => $user->id, 'od_sph' => -1.0]);
        $item = Inventory::create([
            'tenant_id' => $tenant->id, 'sku' => 'SMK-1', 'barcode' => '999900001111',
            'item_type' => 'frame', 'brand' => 'Ray-Ban', 'model_name' => 'Aviator',
            'cost_price' => 50, 'selling_price' => 250, 'stock_qty' => 10, 'min_alert_qty' => 2,
        ]);
        $order = Order::create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id,
            'status' => 'pending', 'total_amount' => 250, 'advance_paid' => 0,
        ]);
        $order->items()->create(['inventory_id' => $item->id, 'quantity' => 1, 'unit_price' => 250]);

        $bindings = [
            'customer' => $customer->id,
            'order' => $order->id,
            'inventory' => $item->id,
            'record' => $record->id,
        ];

        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'tenant.')) {
                continue;
            }

            $params = [];
            foreach ($route->parameterNames() as $p) {
                if (! isset($bindings[$p])) {
                    continue 2; // unresolvable param — skip this route
                }
                $params[$p] = $bindings[$p];
            }

            $status = $this->get(route($name, $params))->getStatusCode();
            $this->assertLessThan(500, $status, "Route {$name} returned {$status}");
            $checked++;
        }

        $this->assertGreaterThan(10, $checked, 'Expected to smoke-test many tenant routes.');
    }
}
