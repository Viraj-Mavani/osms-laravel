<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barcodes are auto-generated and scanned to resolve a single item, so they must
 * be unique within a tenant. Previously `barcode` was only indexed, allowing a
 * (rare) collision to silently map a scan to the wrong product. See BUG-004.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->unique(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'barcode']);
        });
    }
};
