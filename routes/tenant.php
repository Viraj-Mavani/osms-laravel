<?php

/*
|--------------------------------------------------------------------------
| Tenant module routes
|--------------------------------------------------------------------------
| Included inside the `tenant` prefix + `tenant.` name group (auth + onboarded).
| Each feature module appends its routes here as it is built.
*/

use App\Http\Controllers\Tenant\AnalyticsController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\EyeRecordController;
use App\Http\Controllers\Tenant\InventoryController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\PatientController;
use App\Http\Controllers\Tenant\SearchController;
use Illuminate\Support\Facades\Route;

// ---- Global search (Cmd+K) ----
Route::middleware('throttle:120,1')->get('search', SearchController::class)->name('search');

// ---- Patients ----
Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
Route::get('patients/create', [PatientController::class, 'create'])->name('patients.create');
Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');

// ---- Eye records (nested under a patient) ----
Route::get('patients/{patient}/records/create', [EyeRecordController::class, 'create'])->name('eye-records.create');
Route::post('patients/{patient}/records', [EyeRecordController::class, 'store'])->name('eye-records.store');

// ---- Inventory ----
Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::middleware('throttle:120,1')->get('inventory/scan', [InventoryController::class, 'scan'])->name('inventory.scan');
Route::get('inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
Route::get('inventory/{inventory}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
Route::put('inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');

// ---- Orders ----
Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
Route::get('orders/{order}/pdf', [OrderController::class, 'pdf'])->name('orders.pdf');
Route::middleware('throttle:120,1')->get('patients/{patient}/eye-records', [OrderController::class, 'eyeRecords'])->name('patients.eye-records');

// ---- Analytics (store admins + superadmin only) ----
Route::middleware('role:store_admin,superadmin')->group(function () {
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/ledger/export', [AnalyticsController::class, 'exportLedger'])->name('analytics.ledger.export');
});

// ---- Billing / subscriptions ----
Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
Route::post('billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
Route::get('billing/success', [BillingController::class, 'success'])->name('billing.success');
