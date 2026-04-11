<?php

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('category')->default('General');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('image_url')->nullable();
            $table->text('preview_text');
            $table->longText('content');
            $table->unsignedInteger('price');
            $table->string('commission_type')->default('percentage'); // percentage,fixed
            $table->unsignedInteger('commission_value');
            $table->unsignedInteger('access_duration_hours')->nullable();
            $table->string('status')->default(ArticleStatus::DRAFT->value)->index();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('unlock_count')->default(0);
            $table->float('rating_average', 2, 1)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
