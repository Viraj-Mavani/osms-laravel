<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2PatientTest extends TestCase
{
    use RefreshDatabase;

    private function storeUser(): User
    {
        $tenant = Tenant::create(['store_name' => 'Test Optical']);
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'store_admin']);
    }

    public function test_patient_pages_render(): void
    {
        $user = $this->storeUser();
        $this->actingAs($user)->get(route('tenant.patients.index'))->assertOk();
        $this->actingAs($user)->get(route('tenant.patients.create'))->assertOk();
    }

    public function test_can_create_patient(): void
    {
        $user = $this->storeUser();

        $this->actingAs($user)->post(route('tenant.patients.store'), [
            'name' => 'Rahul Kumar',
            'country_code' => '+91',
            'phone' => '9876543210',
            'age' => 32,
            'gender' => 'male',
        ])->assertRedirect();

        $this->assertDatabaseHas('patients', [
            'name' => 'Rahul Kumar',
            'phone' => '+91 9876543210', // country code + national, normalised on save
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function test_duplicate_phone_rejected_within_same_tenant(): void
    {
        $user = $this->storeUser();
        $this->actingAs($user);
        Patient::create(['name' => 'A', 'phone' => '+91 9876543210']);

        $this->post(route('tenant.patients.store'), ['name' => 'B', 'country_code' => '+91', 'phone' => '9876543210'])
            ->assertSessionHasErrors('phone');
    }

    public function test_same_phone_allowed_across_tenants(): void
    {
        $u1 = $this->storeUser();
        $this->actingAs($u1);
        Patient::create(['name' => 'A', 'phone' => '+91 9876543210']);

        $u2 = $this->storeUser();
        $this->actingAs($u2)->post(route('tenant.patients.store'), ['name' => 'B', 'country_code' => '+91', 'phone' => '9876543210'])
            ->assertRedirect();

        $this->assertDatabaseCount('patients', 2);
    }

    public function test_cannot_view_another_tenants_patient(): void
    {
        $u1 = $this->storeUser();
        $this->actingAs($u1);
        $patient = Patient::create(['name' => 'Secret', 'phone' => '999']);

        $u2 = $this->storeUser();
        $this->actingAs($u2)->get(route('tenant.patients.show', $patient))->assertNotFound();
    }

    public function test_patient_index_is_paginated(): void
    {
        $user = $this->storeUser();
        $this->actingAs($user);
        for ($i = 0; $i < 55; $i++) {
            Patient::create(['name' => 'Patient ' . $i, 'phone' => '9000000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }

        $page1 = $this->actingAs($user)->get(route('tenant.patients.index'));
        $page1->assertOk();
        $this->assertCount(50, $page1->viewData('patients'));
        $this->assertSame(55, $page1->viewData('patients')->total());

        $this->actingAs($user)->get(route('tenant.patients.index', ['page' => 2]))
            ->assertOk()->assertViewHas('patients', fn ($p) => $p->count() === 5);
    }

    public function test_can_add_eye_record_and_see_it_on_profile(): void
    {
        $user = $this->storeUser();
        $this->actingAs($user);
        $patient = Patient::create(['name' => 'Rahul', 'phone' => '222']);

        $this->actingAs($user)->post(route('tenant.eye-records.store', $patient), [
            'od_sph' => -1.5, 'od_cyl' => -0.75, 'od_axis' => 90, 'od_va' => '6/6',
            'os_sph' => -1.25, 'pd' => 62, 'notes' => 'Stable',
        ])->assertRedirect(route('tenant.patients.show', $patient));

        $this->assertDatabaseHas('eye_records', [
            'patient_id' => $patient->id,
            'tenant_id' => $user->tenant_id,
            'recorded_by' => $user->id,
            'od_axis' => 90,
        ]);

        $this->actingAs($user)->get(route('tenant.patients.show', $patient))
            ->assertOk()->assertSee('Eye record');
    }
}
