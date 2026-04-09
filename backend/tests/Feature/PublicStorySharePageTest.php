<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStorySharePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_story_preview_page_is_publicly_visible(): void
    {
        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'email_verified_at' => now(),
        ]);

        $article = Article::create([
            'author_id' => $author->id,
            'category' => 'Technology',
            'title' => 'Shareable Story',
            'slug' => 'shareable-story',
            'preview_text' => 'A short preview that can be safely shared in the browser.',
            'content' => 'Premium story body.',
            'price' => 45,
            'commission_type' => CommissionType::PERCENTAGE,
            'commission_value' => 10,
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => now(),
            'approved_at' => now(),
        ]);

        $this->get("/stories/{$article->slug}")
            ->assertOk()
            ->assertSee('Shareable Story')
            ->assertSee('A short preview that can be safely shared in the browser.');
    }

    public function test_unpublished_story_preview_page_returns_not_found(): void
    {
        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'email_verified_at' => now(),
        ]);

        $article = Article::create([
            'author_id' => $author->id,
            'category' => 'Technology',
            'title' => 'Private Draft',
            'slug' => 'private-draft',
            'preview_text' => 'This should not be publicly visible.',
            'content' => 'Draft body.',
            'price' => 35,
            'commission_type' => CommissionType::FIXED,
            'commission_value' => 5,
            'status' => ArticleStatus::DRAFT,
        ]);

        $this->get("/stories/{$article->slug}")->assertNotFound();
    }
}
