<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\TransactionType;
use App\Models\Article;
use App\Models\ArticleUnlock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class ReaderPortalService
{
    public function bundle(User $user): array
    {
        $user = $user->fresh();
        $articles = Article::query()
            ->with('author:id,name')
            ->where('status', ArticleStatus::PUBLISHED)
            ->latest('published_at')
            ->latest('id')
            ->get();

        $activeUnlockIds = $user->unlocks()
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->pluck('article_id')
            ->all();

        $articleSummaries = $articles
            ->map(fn (Article $article) => $this->serializeArticleSummary(
                $article,
                in_array($article->id, $activeUnlockIds, true),
            ))
            ->values();

        $transactions = $user->transactions()
            ->latest('id')
            ->get()
            ->map(fn (Transaction $transaction) => $this->serializeTransaction($transaction))
            ->values();

        $unlocks = $user->unlocks()
            ->with('article.author:id,name')
            ->latest('unlocked_at')
            ->get()
            ->map(fn (ArticleUnlock $unlock) => $this->serializeUnlock($unlock))
            ->values();

        return [
            'user' => $this->serializeUser($user),
            'wallet' => $this->walletSummary($user),
            'articles' => $articleSummaries,
            'transactions' => $transactions,
            'unlocks' => $unlocks,
            'bought_items' => $this->buildBoughtItems($articleSummaries, $unlocks),
            'category_options' => $articleSummaries
                ->pluck('category')
                ->unique()
                ->sort()
                ->values(),
            'author_options' => $articleSummaries
                ->pluck('author_name')
                ->unique()
                ->sort()
                ->values(),
            'stats' => [
                'story_count' => $articleSummaries->count(),
                'premium_count' => $articleSummaries->where('is_unlocked', false)->count(),
                'category_count' => $articleSummaries->pluck('category')->unique()->count(),
                'active_unlocks_count' => $unlocks->where('is_active', true)->count(),
                'cheapest_locked_price' => $articleSummaries
                    ->where('is_unlocked', false)
                    ->min('price'),
            ],
        ];
    }

    public function detail(User $user, Article $article): array
    {
        abort_unless($article->status === ArticleStatus::PUBLISHED, 404);

        $article->increment('view_count');
        $article->refresh()->load('author');

        $unlock = $article->activeUnlockFor($user);

        return $this->serializeArticleDetail($article, $unlock);
    }

    public function filteredArticles(
        Collection $articles,
        string $query = '',
        ?string $selectedCategory = null,
        ?string $selectedAuthor = null,
    ): Collection {
        $needle = mb_strtolower(trim($query));
        $selectedCategory = $selectedCategory && $selectedCategory !== 'All'
            ? $selectedCategory
            : null;
        $selectedAuthor = $selectedAuthor && $selectedAuthor !== 'All authors'
            ? $selectedAuthor
            : null;

        return $articles
            ->filter(function (array $article) use ($needle, $selectedCategory, $selectedAuthor): bool {
                $matchesQuery = $needle === ''
                    || str_contains(mb_strtolower($article['title']), $needle)
                    || str_contains(mb_strtolower($article['author_name']), $needle)
                    || str_contains(mb_strtolower($article['preview_text']), $needle)
                    || str_contains(mb_strtolower($article['category']), $needle);

                $matchesCategory = $selectedCategory === null
                    || $article['category'] === $selectedCategory;
                $matchesAuthor = $selectedAuthor === null
                    || $article['author_name'] === $selectedAuthor;

                return $matchesQuery && $matchesCategory && $matchesAuthor;
            })
            ->values();
    }

    public function sortTopArticles(Collection $articles): Collection
    {
        return $articles
            ->sort(function (array $left, array $right): int {
                $rating = $right['rating_average'] <=> $left['rating_average'];
                if ($rating !== 0) {
                    return $rating;
                }

                $ratingCount = $right['rating_count'] <=> $left['rating_count'];
                if ($ratingCount !== 0) {
                    return $ratingCount;
                }

                $unlocks = $right['unlock_count'] <=> $left['unlock_count'];
                if ($unlocks !== 0) {
                    return $unlocks;
                }

                return $right['view_count'] <=> $left['view_count'];
            })
            ->values();
    }

    public function categoryGroups(Collection $articles, string $selectedCategory = 'All'): Collection
    {
        $categories = $articles
            ->pluck('category')
            ->unique()
            ->values();

        $visibleCategories = $selectedCategory === 'All'
            ? $categories->take(4)
            : collect([$selectedCategory]);

        return $visibleCategories
            ->map(function (string $category) use ($articles): array {
                return [
                    'category' => $category,
                    'articles' => $articles
                        ->where('category', $category)
                        ->take(6)
                        ->values(),
                ];
            })
            ->filter(fn (array $group): bool => $group['articles']->isNotEmpty())
            ->values();
    }

    public function shouldPromptRecharge(array $bundle): bool
    {
        $cheapestLockedPrice = $bundle['stats']['cheapest_locked_price'];

        return $cheapestLockedPrice !== null
            && $bundle['wallet']['wallet_balance'] < $cheapestLockedPrice;
    }

    public function minimumTopUpRupees(array $wallet): int
    {
        $creditsPerRupee = max(1, (int) $wallet['credits_per_rupee']);

        return (int) ceil($wallet['min_purchase_credits'] / $creditsPerRupee);
    }

    protected function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name ?: 'Reader',
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role?->value ?? (string) $user->role,
            'wallet_balance' => (int) $user->wallet_balance,
            'profile_photo_url' => $this->resolveAssetUrl($user->profile_photo_url),
            'created_at' => $user->created_at,
            'created_label' => $user->created_at?->format('d M, h:i A') ?? 'Recently',
            'initials' => $this->initialsFor($user->name),
        ];
    }

    protected function walletSummary(User $user): array
    {
        return [
            'wallet_balance' => (int) $user->wallet_balance,
            'credits_per_rupee' => max(1, (int) config('monetization.credits_per_rupee', 1)),
            'min_purchase_credits' => max(1, (int) config('monetization.min_purchase_credits', 50)),
        ];
    }

    protected function serializeArticleSummary(Article $article, bool $isUnlocked): array
    {
        $category = trim((string) $article->category);
        $category = $category !== '' ? $category : 'General';

        return [
            'id' => $article->id,
            'category' => $category,
            'title' => $article->title ?: 'Untitled',
            'slug' => $article->slug,
            'image_url' => $this->resolveAssetUrl($article->image_url),
            'preview_text' => $article->preview_text ?: '',
            'price' => (int) $article->price,
            'price_label' => $this->formatCoins((int) $article->price),
            'access_duration_hours' => $article->access_duration_hours,
            'view_count' => (int) $article->view_count,
            'unlock_count' => (int) $article->unlock_count,
            'rating_average' => (float) $article->rating_average,
            'rating_count' => (int) $article->rating_count,
            'author_name' => $article->author?->name ?: 'Unknown Author',
            'author_initials' => $this->initialsFor($article->author?->name ?: 'Reader'),
            'is_unlocked' => $isUnlocked,
            'share_url' => route('stories.show', $article),
            'reader_url' => route('reader.articles.show', $article),
        ];
    }

    protected function serializeArticleDetail(Article $article, ?ArticleUnlock $unlock): array
    {
        $summary = $this->serializeArticleSummary($article, $unlock !== null);

        return [
            ...$summary,
            'content' => $unlock ? $article->content : null,
            'status' => $article->status->value,
            'access_expires_at' => $unlock?->expires_at,
            'access_expires_label' => $unlock?->expires_at?->format('d M, h:i A') ?? 'Active',
            'meta_chips' => array_values(array_filter([
                $summary['author_name'],
                $summary['category'],
                number_format($summary['rating_average'], 1).' • '.$summary['rating_count'].' ratings',
                $summary['price_label'],
                $article->access_duration_hours
                    ? $article->access_duration_hours.' hrs'
                    : 'Lifetime access',
                $unlock ? 'Unlocked' : null,
            ])),
        ];
    }

    protected function serializeTransaction(Transaction $transaction): array
    {
        $isCredit = $transaction->type === TransactionType::CREDIT;

        return [
            'id' => $transaction->id,
            'source' => $transaction->source,
            'title' => $this->transactionTitle($transaction),
            'amount' => (int) $transaction->amount,
            'amount_label' => ($isCredit ? '+' : '-').$this->formatCoins((int) $transaction->amount),
            'is_credit' => $isCredit,
            'status' => $transaction->status?->value ?? (string) $transaction->status,
            'status_label' => ucfirst((string) ($transaction->status?->value ?? $transaction->status)),
            'created_at' => $transaction->created_at,
            'created_label' => $transaction->created_at?->format('d M, h:i A') ?? 'Recently',
        ];
    }

    protected function serializeUnlock(ArticleUnlock $unlock): array
    {
        $article = $unlock->article;
        $isActive = $unlock->isActive();

        return [
            'id' => $unlock->id,
            'credits_spent' => (int) $unlock->credits_spent,
            'credits_spent_label' => $this->formatCoins((int) $unlock->credits_spent),
            'unlocked_at' => $unlock->unlocked_at,
            'unlocked_label' => $unlock->unlocked_at?->format('d M, h:i A'),
            'expires_at' => $unlock->expires_at,
            'expires_label' => $unlock->expires_at?->format('d M, h:i A'),
            'is_active' => $isActive,
            'status_label' => $unlock->expires_at
                ? ($isActive ? 'Active until '.$unlock->expires_at->format('d M, h:i A') : 'Expired on '.$unlock->expires_at->format('d M, h:i A'))
                : ($unlock->unlocked_at ? 'Bought on '.$unlock->unlocked_at->format('d M, h:i A') : 'Access active'),
            'article' => $article
                ? $this->serializeArticleSummary($article, $isActive)
                : [
                    'id' => null,
                    'category' => 'Premium',
                    'title' => 'Premium Article',
                    'slug' => '',
                    'image_url' => null,
                    'preview_text' => 'Open your bought article from this saved reader library.',
                    'price' => (int) $unlock->credits_spent,
                    'price_label' => $this->formatCoins((int) $unlock->credits_spent),
                    'access_duration_hours' => null,
                    'view_count' => 0,
                    'unlock_count' => 0,
                    'rating_average' => 0.0,
                    'rating_count' => 0,
                    'author_name' => 'Unknown Author',
                    'author_initials' => 'U',
                    'is_unlocked' => true,
                    'share_url' => '#',
                    'reader_url' => '#',
                ],
        ];
    }

    protected function buildBoughtItems(Collection $articles, Collection $unlocks): Collection
    {
        $articlesBySlug = $articles->keyBy('slug');
        $items = $unlocks
            ->map(function (array $unlock) use ($articlesBySlug): array {
                $article = $articlesBySlug->get($unlock['article']['slug'], $unlock['article']);

                return [
                    'article' => $article,
                    'credits_spent' => $unlock['credits_spent'],
                    'credits_spent_label' => $unlock['credits_spent_label'],
                    'unlocked_at' => $unlock['unlocked_at'],
                    'expires_at' => $unlock['expires_at'],
                    'is_active' => $unlock['is_active'],
                    'status_label' => $unlock['status_label'],
                ];
            })
            ->values();

        $seenSlugs = $items
            ->pluck('article.slug')
            ->filter()
            ->values();

        $fallbackUnlocked = $articles
            ->where('is_unlocked', true)
            ->reject(fn (array $article): bool => $seenSlugs->contains($article['slug']))
            ->map(fn (array $article): array => [
                'article' => $article,
                'credits_spent' => $article['price'],
                'credits_spent_label' => $article['price_label'],
                'unlocked_at' => null,
                'expires_at' => null,
                'is_active' => true,
                'status_label' => 'Access active',
            ]);

        return $items
            ->concat($fallbackUnlocked)
            ->sort(function (array $left, array $right): int {
                $active = ($right['is_active'] ? 1 : 0) <=> ($left['is_active'] ? 1 : 0);
                if ($active !== 0) {
                    return $active;
                }

                $rightTime = $right['unlocked_at']?->getTimestamp() ?? 0;
                $leftTime = $left['unlocked_at']?->getTimestamp() ?? 0;
                $unlocked = $rightTime <=> $leftTime;
                if ($unlocked !== 0) {
                    return $unlocked;
                }

                return strcmp($left['article']['title'], $right['article']['title']);
            })
            ->values();
    }

    protected function transactionTitle(Transaction $transaction): string
    {
        return match ($transaction->source) {
            'credit_purchase' => 'Added money to wallet',
            'article_unlock' => 'Unlocked premium article',
            'article_sale' => 'Article earnings received',
            'withdrawal_request' => 'Withdrawal requested',
            'withdrawal_reversal' => 'Withdrawal restored',
            default => ucfirst(str_replace('_', ' ', $transaction->source)),
        };
    }

    protected function initialsFor(string $name): string
    {
        $parts = array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            static fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            return 'R';
        }

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 1));
        }

        return strtoupper(substr($parts[0], 0, 1).substr($parts[count($parts) - 1], 0, 1));
    }

    protected function resolveAssetUrl(?string $pathOrUrl): ?string
    {
        $value = trim((string) $pathOrUrl);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return url(ltrim($value, '/'));
    }

    protected function formatCoins(int $value): string
    {
        return number_format($value).' coins';
    }
}
