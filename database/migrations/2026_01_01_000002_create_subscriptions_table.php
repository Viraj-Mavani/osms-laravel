<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            // Razorpay (replaces the scaffolded Stripe column)
            $table->string('razorpay_subscription_id')->nullable();
            $table->string('razorpay_customer_id')->nullable();
            $table->enum('status', ['active', 'past_due', 'canceled', 'trialing'])->default('trialing');
            $table->enum('tier', ['basic', 'pro', 'enterprise'])->default('basic');
            $table->date('current_period_end')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
