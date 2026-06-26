<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('sku');
            $table->string('barcode')->nullable()->index();
            $table->enum('item_type', ['frame', 'lens', 'contact_lens', 'accessory']);
            $table->string('brand')->nullable();
            $table->string('model_name')->nullable();
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->unsignedInteger('stock_qty')->default(0);
            $table->unsignedInteger('min_alert_qty')->default(5);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
