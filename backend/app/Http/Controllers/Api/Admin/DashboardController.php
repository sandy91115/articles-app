<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ArticleStatus;
use App\Enums\PaymentOrderStatus;
use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleUnlock;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\Withdrawal;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalRevenuePaise = (int) PaymentOrder::query()
            ->where('status', PaymentOrderStatus::PAID)
            ->sum('amount_in_paise');

        $pendingArticlesCount = Article::query()
            ->where('status', ArticleStatus::PENDING_APPROVAL)
            ->count();

        $pendingWithdrawalsQuery = Withdrawal::query()
            ->where('status', WithdrawalStatus::PENDING);

        $pendingWithdrawalsCount = (int) $pendingWithdrawalsQuery->count();
        $pendingWithdrawalsAmount = (int) $pendingWithdrawalsQuery->sum('amount');

        $publishedArticles = Article::query()
            ->where('status', ArticleStatus::PUBLISHED)
            ->get();

        $articleUnlockMetrics = ArticleUnlock::query()
            ->selectRaw('article_id, COUNT(*) as realized_unlocks_count, COUNT(DISTINCT user_id) as unique_readers_count, SUM(credits_spent) as gross_credits, SUM(author_earnings) as author_earnings, MAX(unlocked_at) as last_unlocked_at')
            ->groupBy('article_id')
            ->get()
            ->keyBy('article_id');

        $authorUnlockMetrics = ArticleUnlock::query()
            ->join('articles', 'articles.id', '=', 'article_unlocks.article_id')
            ->selectRaw('articles.author_id, COUNT(*) as realized_unlocks_count, COUNT(DISTINCT article_unlocks.user_id) as unique_readers_count, SUM(article_unlocks.credits_spent) as gross_credits, SUM(article_unlocks.author_earnings) as author_earnings, SUM(article_unlocks.admin_commission) as admin_commission')
            ->groupBy('articles.author_id')
            ->get()
            ->keyBy('author_id');

        $pendingWithdrawalMetrics = Withdrawal::query()
            ->where('status', WithdrawalStatus::PENDING)
            ->selectRaw('author_id, COUNT(*) as pending_count, SUM(amount) as pending_amount')
            ->groupBy('author_id')
            ->get()
            ->keyBy('author_id');

        $withdrawalMetrics = Withdrawal::query()
            ->selectRaw('author_id, COUNT(*) as total_requests, SUM(amount) as total_requested_amount, MAX(created_at) as last_requested_at')
            ->groupBy('author_id')
            ->get()
            ->keyBy('author_id');

        $paidOrderMetrics = PaymentOrder::query()
            ->where('status', PaymentOrderStatus::PAID)
            ->selectRaw('user_id, COUNT(*) as paid_orders_count, SUM(credit_amount) as credits_purchased, SUM(amount_in_paise) as amount_in_paise, MAX(paid_at) as last_paid_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $readerUnlockMetrics = ArticleUnlock::query()
            ->selectRaw('user_id, COUNT(*) as unlock_count, COUNT(DISTINCT article_id) as unlocked_articles_count, SUM(credits_spent) as credits_spent, MAX(unlocked_at) as last_unlock_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $authors = User::query()
            ->authors()
            ->with(['articles' => fn ($query) => $query->latest('published_at')->latest('id')])
            ->get();

        $authorProfiles = $authors
            ->map(function (User $author) use ($articleUnlockMetrics, $authorUnlockMetrics, $pendingWithdrawalMetrics, $withdrawalMetrics): array {
                $articles = $author->articles;
                $publishedCount = $articles->filter(
                    fn (Article $article): bool => $article->status === ArticleStatus::PUBLISHED,
                )->count();
                $ratingVotes = (int) $articles->sum('rating_count');
                $authorUnlockMetric = $authorUnlockMetrics->get($author->id);
                $pendingMetric = $pendingWithdrawalMetrics->get($author->id);
                $withdrawalMetric = $withdrawalMetrics->get($author->id);

                return [
                    'id' => $author->id,
                    'name' => $author->name,
                    'email' => $author->email,
                    'phone' => $author->phone,
                    'username' => $author->username,
                    'profile_photo_url' => $author->profile_photo_url,
                    'wallet_balance' => (int) $author->wallet_balance,
                    'created_at' => $author->created_at?->toIso8601String(),
                    'email_verified_at' => $author->email_verified_at?->toIso8601String(),
                    'stats' => [
                        'articles_count' => (int) $articles->count(),
                        'published_articles_count' => (int) $publishedCount,
                        'other_articles_count' => (int) $articles->count() - $publishedCount,
                        'total_views' => (int) $articles->sum('view_count'),
                        'total_unlocks' => (int) $articles->sum('unlock_count'),
                        'unique_readers_count' => (int) ($authorUnlockMetric->unique_readers_count ?? 0),
                        'average_rating' => $this->weightedRatingAverage($articles),
                        'rating_votes' => $ratingVotes,
                        'realized_earnings' => (int) ($authorUnlockMetric->author_earnings ?? 0),
                        'gross_credits' => (int) ($authorUnlockMetric->gross_credits ?? 0),
                        'pending_withdrawals_count' => (int) ($pendingMetric->pending_count ?? 0),
                        'pending_withdrawals_amount' => (int) ($pendingMetric->pending_amount ?? 0),
                        'total_withdrawal_requests' => (int) ($withdrawalMetric->total_requests ?? 0),
                        'total_withdrawals_requested' => (int) ($withdrawalMetric->total_requested_amount ?? 0),
                        'last_withdrawal_requested_at' => $withdrawalMetric->last_requested_at ?? null,
                    ],
                    'articles' => $articles->map(function (Article $article) use ($articleUnlockMetrics): array {
                        $unlockMetric = $articleUnlockMetrics->get($article->id);

                        return [
                            'id' => $article->id,
                            'title' => $article->title,
                            'slug' => $article->slug,
                            'category' => $article->category,
                            'status' => $this->enumValue($article->status),
                            'price' => (int) $article->price,
                            'view_count' => (int) $article->view_count,
                            'unlock_count' => (int) $article->unlock_count,
                            'rating_average' => (float) $article->rating_average,
                            'rating_count' => (int) $article->rating_count,
                            'published_at' => $article->published_at?->toIso8601String(),
                            'updated_at' => $article->updated_at?->toIso8601String(),
                            'realized_earnings' => (int) ($unlockMetric->author_earnings ?? 0),
                            'realized_unlocks_count' => (int) ($unlockMetric->realized_unlocks_count ?? 0),
                            'unique_readers_count' => (int) ($unlockMetric->unique_readers_count ?? 0),
                            'gross_credits' => (int) ($unlockMetric->gross_credits ?? 0),
                        ];
                    })->values(),
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [
                    $right['stats']['realized_earnings'],
                    $right['stats']['total_unlocks'],
                    $right['stats']['average_rating'],
                ] <=> [
                    $left['stats']['realized_earnings'],
                    $left['stats']['total_unlocks'],
                    $left['stats']['average_rating'],
                ];
            })
            ->values();

        $authorProfilesById = $authorProfiles->keyBy('id');
        $users = User::query()
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->map(function (User $user) use ($authorProfilesById, $paidOrderMetrics, $readerUnlockMetrics, $pendingArticlesCount, $pendingWithdrawalsCount): array {
                $role = $this->enumValue($user->role);
                $stats = [];

                if ($role === UserRole::AUTHOR->value) {
                    $authorProfile = $authorProfilesById->get($user->id);
                    $stats = [
                        'articles_count' => (int) ($authorProfile['stats']['articles_count'] ?? 0),
                        'published_articles_count' => (int) ($authorProfile['stats']['published_articles_count'] ?? 0),
                        'total_unlocks' => (int) ($authorProfile['stats']['total_unlocks'] ?? 0),
                        'unique_readers_count' => (int) ($authorProfile['stats']['unique_readers_count'] ?? 0),
                        'average_rating' => (float) ($authorProfile['stats']['average_rating'] ?? 0),
                        'rating_votes' => (int) ($authorProfile['stats']['rating_votes'] ?? 0),
                        'realized_earnings' => (int) ($authorProfile['stats']['realized_earnings'] ?? 0),
                    ];
                } elseif ($role === UserRole::READER->value) {
                    $orderMetric = $paidOrderMetrics->get($user->id);
                    $readerMetric = $readerUnlockMetrics->get($user->id);
                    $stats = [
                        'paid_orders_count' => (int) ($orderMetric->paid_orders_count ?? 0),
                        'credits_purchased' => (int) ($orderMetric->credits_purchased ?? 0),
                        'amount_spent_rupees' => round(((int) ($orderMetric->amount_in_paise ?? 0)) / 100, 2),
                        'unlock_count' => (int) ($readerMetric->unlock_count ?? 0),
                        'unlocked_articles_count' => (int) ($readerMetric->unlocked_articles_count ?? 0),
                        'credits_spent' => (int) ($readerMetric->credits_spent ?? 0),
                        'last_paid_at' => $orderMetric->last_paid_at ?? null,
                        'last_unlock_at' => $readerMetric->last_unlock_at ?? null,
                    ];
                } else {
                    $stats = [
                        'managed_users_count' => (int) max(User::query()->count() - 1, 0),
                        'managed_authors_count' => (int) User::query()->authors()->count(),
                        'managed_readers_count' => (int) User::query()->readers()->count(),
                        'open_queue_count' => $pendingArticlesCount + $pendingWithdrawalsCount,
                    ];
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'username' => $user->username,
                    'profile_photo_url' => $user->profile_photo_url,
                    'role' => $role,
                    'wallet_balance' => (int) $user->wallet_balance,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'stats' => $stats,
                ];
            })
            ->sort(function (array $left, array $right): int {
                $roleComparison = $this->roleOrder($left['role']) <=> $this->roleOrder($right['role']);

                if ($roleComparison !== 0) {
                    return $roleComparison;
                }

                $createdComparison = ($right['created_at'] ?? '') <=> ($left['created_at'] ?? '');

                if ($createdComparison !== 0) {
                    return $createdComparison;
                }

                return $left['name'] <=> $right['name'];
            })
            ->values();

        $platformRatingVotes = (int) $publishedArticles->sum('rating_count');
        $platformUnlockReaderCount = (int) ArticleUnlock::query()
            ->distinct('user_id')
            ->count('user_id');

        return response()->json([
            'summary' => [
                'total_users' => (int) User::query()->count(),
                'total_admins' => (int) User::query()->admins()->count(),
                'total_authors' => (int) User::query()->authors()->count(),
                'total_readers' => (int) User::query()->readers()->count(),
                'active_authors' => (int) $authorProfiles->filter(
                    fn (array $author): bool => $author['stats']['published_articles_count'] > 0,
                )->count(),
                'engaged_readers' => $platformUnlockReaderCount,
                'live_articles' => (int) $publishedArticles->count(),
                'pending_articles_count' => $pendingArticlesCount,
                'pending_withdrawals_count' => $pendingWithdrawalsCount,
                'pending_withdrawals_amount' => $pendingWithdrawalsAmount,
                'total_article_views' => (int) $publishedArticles->sum('view_count'),
                'total_article_unlocks' => (int) $publishedArticles->sum('unlock_count'),
                'platform_rating_average' => $this->weightedRatingAverage($publishedArticles),
                'platform_rating_votes' => $platformRatingVotes,
                'total_revenue_paise' => $totalRevenuePaise,
                'total_revenue_rupees' => round($totalRevenuePaise / 100, 2),
                'total_credits_sold' => (int) PaymentOrder::query()
                    ->where('status', PaymentOrderStatus::PAID)
                    ->sum('credit_amount'),
                'author_earnings' => (int) ArticleUnlock::query()->sum('author_earnings'),
                'commission_earned' => (int) ArticleUnlock::query()->sum('admin_commission'),
            ],
            'article_performance' => Article::query()
                ->with('author:id,name')
                ->orderByDesc('unlock_count')
                ->orderByDesc('view_count')
                ->limit(10)
                ->get()
                ->map(function (Article $article) use ($articleUnlockMetrics): array {
                    $unlockMetric = $articleUnlockMetrics->get($article->id);

                    return [
                        'id' => $article->id,
                        'title' => $article->title,
                        'slug' => $article->slug,
                        'category' => $article->category,
                        'status' => $this->enumValue($article->status),
                        'view_count' => (int) $article->view_count,
                        'unlock_count' => (int) $article->unlock_count,
                        'price' => (int) $article->price,
                        'rating_average' => (float) $article->rating_average,
                        'rating_count' => (int) $article->rating_count,
                        'published_at' => $article->published_at?->toIso8601String(),
                        'realized_earnings' => (int) ($unlockMetric->author_earnings ?? 0),
                        'unique_readers_count' => (int) ($unlockMetric->unique_readers_count ?? 0),
                        'author' => [
                            'id' => $article->author?->id,
                            'name' => $article->author?->name,
                        ],
                    ];
                })
                ->values(),
            'authors' => $authorProfiles,
            'users' => $users,
        ]);
    }

    private function weightedRatingAverage(Collection $articles): float
    {
        $ratingVotes = (int) $articles->sum('rating_count');

        if ($ratingVotes === 0) {
            return 0.0;
        }

        $weightedTotal = $articles->sum(
            fn (Article|array $article): float => (float) data_get($article, 'rating_average', 0)
                * (int) data_get($article, 'rating_count', 0),
        );

        return round($weightedTotal / $ratingVotes, 1);
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function roleOrder(?string $role): int
    {
        return match ($role) {
            UserRole::ADMIN->value => 0,
            UserRole::AUTHOR->value => 1,
            UserRole::READER->value => 2,
            default => 3,
        };
    }
}
