<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FG-Delete — soft-delete support for inventory. Archiving keeps historical
 * order line-items intact (they carry their own captured unit_price), while
 * removing the item from active lists. Force-deletes are blocked while the item
 * is referenced by an open order (see InventoryController::destroy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
