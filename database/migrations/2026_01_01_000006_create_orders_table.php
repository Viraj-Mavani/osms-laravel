<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('patient_id')->index();
            $table->uuid('eye_record_id')->nullable()->index();
            $table->enum('status', ['pending', 'ready_for_pickup', 'delivered'])->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('advance_paid', 10, 2)->default(0);
            // Kept in sync by the Order model (total_amount - advance_paid).
            // Stored (not generated) for portability between SQLite dev and MySQL prod.
            $table->decimal('balance_due', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('eye_record_id')->references('id')->on('eye_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
