<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FT-Customers — rename the contact entity `patients` → `customers` and repoint
 * the two foreign keys (`orders.patient_id`, `eye_records.patient_id`) to
 * `customer_id`. "Patient" is henceforth a *derived role* (a customer who has an
 * eye record), not a separate table.
 *
 * Portability: `Schema::rename` + `renameColumn` emit correct per-driver SQL in
 * Laravel 12. On both SQLite (dev/tests, FKs enabled) and MySQL/MariaDB (prod),
 * renaming the parent table updates the child FK references automatically, and
 * renaming the FK column keeps the constraint. Verified green on SQLite with
 * foreign_key_constraints=on; apply on MySQL (Hostinger) with a backup first.
 *
 * Data-preserving + reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('patients', 'customers');

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('patient_id', 'customer_id');
        });

        Schema::table('eye_records', function (Blueprint $table) {
            $table->renameColumn('patient_id', 'customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('eye_records', function (Blueprint $table) {
            $table->renameColumn('customer_id', 'patient_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('customer_id', 'patient_id');
        });

        Schema::rename('customers', 'patients');
    }
};
