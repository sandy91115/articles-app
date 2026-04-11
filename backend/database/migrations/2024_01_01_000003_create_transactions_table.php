<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // debit,credit,platform_credit
            $table->string('reference_id')->unique();
            $table->unsignedInteger('amount');
            $table->json('meta');
            $table->foreignId('related_user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['type', 'created_at']);
            $table->index('reference_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
