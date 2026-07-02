<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
