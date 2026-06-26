<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('phone');
            $table->unsignedSmallInteger('age')->nullable();
            $table->string('gender')->nullable();
            $table->timestamps();

            // Unique per tenant + phone (matches Supabase constraint)
            $table->unique(['tenant_id', 'phone']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
