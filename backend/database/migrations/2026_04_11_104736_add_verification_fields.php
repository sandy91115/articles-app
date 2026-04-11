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
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('email')->nullable()->after('user_id');
            $table->enum('purpose', ['email_verification', 'phone_verification'])->default('email_verification')->after('email');
            $table->timestamp('consumed_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->dropColumn(['email', 'purpose', 'consumed_at']);
        });
    }
};

