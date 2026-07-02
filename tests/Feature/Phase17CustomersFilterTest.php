<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Session 3 — FT-Customers C-c: the "Patients" filter + badge. A customer is a
 * patient once they have >=1 eye record (derived via scopePatients / the
 * eye_records_count). The index can filter to patients and badges them.
 */
class Phase17CustomersFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create(['store_name' => 'Test Optical', 'tax_id' => 'GST123', 'address' => 'Mumbai']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
    }

    private function makeCustomer(string $name): Customer
    {
        return Customer::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => $name, 'phone' => '+91 90000' . random_int(10000, 99999),
        ]);
    }

    private function withRecord(Customer $c): Customer
    {
        $c->eyeRecords()->create(['tenant_id' => $c->tenant_id, 'recorded_by' => $this->user->id, 'od_sph' => -1.0]);

        return $c;
    }

    public function test_is_patient_is_derived_from_eye_records(): void
    {
        $plain = $this->makeCustomer('Plain');
        $patient = $this->withRecord($this->makeCustomer('Patiento'));

        $this->assertFalse($plain->isPatient());
        $this->assertTrue($patient->fresh()->isPatient());
    }

    public function test_patients_filter_returns_only_customers_with_records(): void
    {
        $this->makeCustomer('Bob Buyer');            // plain customer
        $this->withRecord($this->makeCustomer('Ann Patient')); // has a prescription

        $this->actingAs($this->user);

        $all = $this->get(route('tenant.customers.index'));
        $all->assertOk()->assertSee('Ann Patient')->assertSee('Bob Buyer');

        $patients = $this->get(route('tenant.customers.index', ['filter' => 'patients']));
        $patients->assertOk()->assertSee('Ann Patient')->assertDontSee('Bob Buyer');
    }

    public function test_patient_badge_renders_for_customers_with_records(): void
    {
        $this->withRecord($this->makeCustomer('Ann Patient'));
        $this->makeCustomer('Bob Buyer');

        $this->actingAs($this->user)->get(route('tenant.customers.index'))
            ->assertOk()
            ->assertSee('osms-badge-blue', false); // the "Patient" badge is present
    }

    public function test_patients_filter_scopes_the_query_result(): void
    {
        $this->makeCustomer('Bob Buyer');
        $this->withRecord($this->makeCustomer('Ann Patient'));

        $res = $this->actingAs($this->user)->get(route('tenant.customers.index', ['filter' => 'patients']));
        $this->assertCount(1, $res->viewData('customers'));
    }
}
