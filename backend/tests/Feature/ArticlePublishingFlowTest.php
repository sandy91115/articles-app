<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticlePublishingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_articles_are_published_immediately_when_created(): void
    {
        $author = User::factory()->author()->create();

        Sanctum::actingAs($author);

        $response = $this->postJson('/api/articles', [
            'category' => 'Technology',
            'title' => 'Instantly Published Story',
            'image_url' => 'https://example.com/story.jpg',
            'preview_text' => 'Preview copy',
            'content' => 'Full premium content',
            'price' => 25,
            'commission_type' => CommissionType::PERCENTAGE->value,
            'commission_value' => 10,
            'access_duration_hours' => 24,
            'submit_for_review' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Article published successfully.')
            ->assertJsonPath('article.category', 'Technology')
            ->assertJsonPath('article.status', ArticleStatus::PUBLISHED->value)
            ->assertJsonPath('article.approved_by', $author->id);

        $this->assertDatabaseHas('articles', [
            'title' => 'Instantly Published Story',
            'author_id' => $author->id,
            'category' => 'Technology',
            'status' => ArticleStatus::PUBLISHED->value,
            'approved_by' => $author->id,
        ]);
    }

    public function test_admin_can_approve_a_pending_article_by_numeric_id(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->author()->create();

        $article = Article::create([
            'author_id' => $author->id,
            'category' => 'Business',
            'title' => 'Needs Legacy Approval',
            'slug' => 'needs-legacy-approval',
            'preview_text' => 'Preview',
            'content' => 'Full content',
            'price' => 30,
            'commission_type' => CommissionType::FIXED,
            'commission_value' => 5,
            'access_duration_hours' => 24,
            'status' => ArticleStatus::PENDING_APPROVAL,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/articles/{$article->id}/approve", [])
            ->assertOk()
            ->assertJsonPath('article.id', $article->id)
            ->assertJsonPath('article.status', ArticleStatus::PUBLISHED->value)
            ->assertJsonPath('article.approved_by', $admin->id);
    }
}
