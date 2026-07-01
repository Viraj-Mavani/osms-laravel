<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NB-009 — order cancellation. Adds a `cancelled` status plus an audit stamp
 * (when + why). The status enum is widened portably: MySQL needs a raw MODIFY,
 * while SQLite (dev/tests) only enforces the enum via a CHECK constraint that a
 * native ->change() rebuild drops, so 'cancelled' is then accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('balance_due');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY COLUMN status "
                . "ENUM('pending','ready_for_pickup','delivered','cancelled') "
                . "NOT NULL DEFAULT 'pending'"
            );
        } else {
            // SQLite & others: relax the column to a plain string (rebuilds the
            // table, dropping the old CHECK constraint that would reject 'cancelled').
            Schema::table('orders', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY COLUMN status "
                . "ENUM('pending','ready_for_pickup','delivered') "
                . "NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
