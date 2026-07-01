<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FG-Delete — soft-delete support for patients. A `deleted_at` timestamp lets a
 * record be archived (recoverable for 30 days) and restored, instead of being
 * lost forever. A scheduled purge hard-deletes rows trashed past the window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
