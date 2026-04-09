<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider')->default('razorpay');
            $table->string('reference')->unique();
            $table->string('provider_order_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('provider_signature')->nullable();
            $table->unsignedInteger('credit_amount');
            $table->unsignedBigInteger('amount_in_paise');
            $table->string('currency', 10)->default('INR');
            $table->string('status')->default('pending')->index();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
