<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnlockFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlocking_an_article_splits_commission_and_updates_wallets(): void
    {
        $author = User::factory()->author()->create([
            'wallet_balance' => 0,
        ]);

        $reader = User::factory()->reader()->create([
            'wallet_balance' => 100,
        ]);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Premium Story',
            'slug' => 'premium-story',
            'preview_text' => 'Preview',
            'content' => 'Full content',
            'price' => 50,
            'commission_type' => CommissionType::PERCENTAGE,
            'commission_value' => 10,
            'access_duration_hours' => 24,
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($reader);

        $response = $this->postJson("/api/articles/{$article->slug}/unlock");

        $response
            ->assertOk()
            ->assertJsonPath('unlock.credits_spent', 50)
            ->assertJsonPath('unlock.author_earnings', 45)
            ->assertJsonPath('unlock.admin_commission', 5);

        $this->assertDatabaseHas('users', [
            'id' => $reader->id,
            'wallet_balance' => 50,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $author->id,
            'wallet_balance' => 45,
        ]);

        $this->assertDatabaseHas('article_unlocks', [
            'user_id' => $reader->id,
            'article_id' => $article->id,
            'credits_spent' => 50,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => null,
            'source' => 'platform_commission',
            'amount' => 5,
        ]);
    }
}
