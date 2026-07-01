<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FG-StockLog — every change to an item's stock, with who/why/when. `delta` is
 * signed (negative = drawn down by an order, positive = restored on cancel or a
 * manual increase). `type` classifies the source; `order_id` links movements
 * that came from placing/cancelling an order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('inventory_id')->index();
            $table->integer('delta'); // signed: -n drawn down, +n restored/added
            $table->string('type')->default('adjustment'); // order | cancel | adjustment
            $table->string('reason')->nullable();
            $table->uuid('order_id')->nullable()->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('inventory_id')->references('id')->on('inventory')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
