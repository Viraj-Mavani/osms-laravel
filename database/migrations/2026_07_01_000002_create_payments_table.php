<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FG-PaymentLog — a per-payment audit trail against an order. Each collection
 * (initial advance + later balance settlements) is one row here; the order's
 * running `advance_paid` stays the source of truth for `balance_due`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('order_id')->index();
            $table->decimal('amount', 10, 2);
            $table->string('method')->default('cash'); // cash | card | upi | other
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
