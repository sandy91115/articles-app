<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleUnlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArticleUnlockService
{
    public function __construct(
        protected WalletService $walletService,
        protected CommissionService $commissionService,
    ) {
    }

    public function unlock(User $reader, Article $article): ArticleUnlock
    {
        if ($article->status !== ArticleStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                'article' => ['Only published articles can be unlocked.'],
            ]);
        }

        if ($article->author_id === $reader->id) {
            throw ValidationException::withMessages([
                'article' => ['Authors already have access to their own articles.'],
            ]);
        }

        $activeUnlock = $article->activeUnlockFor($reader);

        if ($activeUnlock) {
            return $activeUnlock->load(['article.author', 'transaction']);
        }

        return DB::transaction(function () use ($reader, $article) {
            $lockedArticle = Article::query()
                ->with('author')
                ->lockForUpdate()
                ->findOrFail($article->id);

            $existingUnlock = ArticleUnlock::query()
                ->where('user_id', $reader->id)
                ->where('article_id', $lockedArticle->id)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();

            if ($existingUnlock) {
                return $existingUnlock->load(['article.author', 'transaction']);
            }

            $split = $this->commissionService->split($lockedArticle);
            $referenceId = (string) Str::uuid();

            $debitTransaction = $this->walletService->debit(
                $reader,
                $split['price'],
                'article_unlock',
                $referenceId,
                [
                    'article_id' => $lockedArticle->id,
                    'author_id' => $lockedArticle->author_id,
                ],
                $lockedArticle->author,
            );

            if ($split['author_earnings'] > 0) {
                $this->walletService->credit(
                    $lockedArticle->author,
                    $split['author_earnings'],
                    'article_sale',
                    $referenceId,
                    [
                        'article_id' => $lockedArticle->id,
                        'reader_id' => $reader->id,
                    ],
                    $reader,
                );
            }

            if ($split['admin_commission'] > 0) {
                $this->walletService->platformCredit(
                    $split['admin_commission'],
                    'platform_commission',
                    $referenceId,
                    [
                        'article_id' => $lockedArticle->id,
                        'reader_id' => $reader->id,
                        'author_id' => $lockedArticle->author_id,
                    ],
                    $lockedArticle->author,
                );
            }

            $unlock = ArticleUnlock::create([
                'user_id' => $reader->id,
                'article_id' => $lockedArticle->id,
                'transaction_id' => $debitTransaction->id,
                'credits_spent' => $split['price'],
                'author_earnings' => $split['author_earnings'],
                'admin_commission' => $split['admin_commission'],
                'unlocked_at' => now(),
                'expires_at' => $lockedArticle->access_duration_hours
                    ? now()->addHours($lockedArticle->access_duration_hours)
                    : null,
            ]);

            $lockedArticle->increment('unlock_count');

            return $unlock->load(['article.author', 'transaction']);
        });
    }
}
