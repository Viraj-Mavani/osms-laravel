<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eye_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('patient_id')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            // OD = Right eye
            $table->decimal('od_sph', 5, 2)->nullable();
            $table->decimal('od_cyl', 5, 2)->nullable();
            $table->unsignedSmallInteger('od_axis')->nullable();
            $table->decimal('od_add', 5, 2)->nullable();
            $table->string('od_va')->nullable();
            $table->decimal('od_spl', 6, 2)->nullable();
            $table->decimal('od_dv', 6, 2)->nullable();
            $table->decimal('od_nv', 6, 2)->nullable();

            // OS = Left eye
            $table->decimal('os_sph', 5, 2)->nullable();
            $table->decimal('os_cyl', 5, 2)->nullable();
            $table->unsignedSmallInteger('os_axis')->nullable();
            $table->decimal('os_add', 5, 2)->nullable();
            $table->string('os_va')->nullable();
            $table->decimal('os_spl', 6, 2)->nullable();
            $table->decimal('os_dv', 6, 2)->nullable();
            $table->decimal('os_nv', 6, 2)->nullable();

            $table->decimal('pd', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eye_records');
    }
};
