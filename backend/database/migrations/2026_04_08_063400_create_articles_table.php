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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('image_url')->nullable();
            $table->text('preview_text');
            $table->longText('content');
            $table->unsignedInteger('price');
            $table->string('commission_type');
            $table->unsignedInteger('commission_value');
            $table->unsignedInteger('access_duration_hours')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('unlock_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
