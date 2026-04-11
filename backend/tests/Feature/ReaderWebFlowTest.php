<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReaderWebFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_can_register_verify_and_enter_web_app(): void
    {
        $response = $this->post('/reader/register', [
            'name' => 'Reader One',
            'email' => 'reader.one@example.com',
            'phone' => '9876543210',
            'username' => 'reader.one',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertRedirect(route('reader.auth', [
                'mode' => 'verify',
                'email' => 'reader.one@example.com',
            ]))
            ->assertSessionHas('debug_code');

        $code = $response->getSession()->get('debug_code');

        $verifyResponse = $this->post('/reader/verify-otp', [
            'email' => 'reader.one@example.com',
            'code' => $code,
        ]);

        $verifyResponse
            ->assertRedirect(route('reader.home'));

        $this->assertAuthenticated();

        $this->get('/reader')
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('Mono Reader Web');
    }

    public function test_verified_reader_can_unlock_an_article_from_web(): void
    {
        $author = User::factory()->author()->create([
            'wallet_balance' => 0,
        ]);

        $reader = User::factory()->reader()->create([
            'wallet_balance' => 90,
            'email_verified_at' => now(),
        ]);

        $article = Article::create([
            'author_id' => $author->id,
            'category' => 'Culture',
            'title' => 'Premium Story',
            'slug' => 'premium-story',
            'preview_text' => 'Preview copy',
            'content' => 'Full content from the premium story.',
            'price' => 50,
            'commission_type' => CommissionType::PERCENTAGE,
            'commission_value' => 10,
            'access_duration_hours' => 24,
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->actingAs($reader);

        $this->get('/reader')
            ->assertOk()
            ->assertSee('Premium Story');

        $this->post("/reader/articles/{$article->slug}/unlock")
            ->assertRedirect(route('reader.articles.show', $article));

        $this->assertDatabaseHas('article_unlocks', [
            'user_id' => $reader->id,
            'article_id' => $article->id,
            'credits_spent' => 50,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $reader->id,
            'wallet_balance' => 40,
        ]);

        $this->get("/reader/articles/{$article->slug}")
            ->assertOk()
            ->assertSee('Full Story')
            ->assertSee('Full content from the premium story.');
    }
}
