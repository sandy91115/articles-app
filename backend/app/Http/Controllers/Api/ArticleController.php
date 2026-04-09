<?php

namespace App\Http\Controllers\Api;

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = auth('sanctum')->user();
        $search = trim((string) $request->input('search', ''));

        $query = Article::query()
            ->with('author:id,name')
            ->where('status', ArticleStatus::PUBLISHED);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('preview_text', 'like', "%{$search}%");
            });
        }

        $articles = $query
            ->latest('published_at')
            ->latest('id')
            ->get();

        $activeUnlockIds = [];

        if ($viewer) {
            $activeUnlockIds = $viewer->unlocks()
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->pluck('article_id')
                ->all();
        }

        return response()->json([
            'articles' => $articles->map(fn (Article $article) => [
                'id' => $article->id,
                'category' => $article->category,
                'title' => $article->title,
                'slug' => $article->slug,
                'image_url' => $article->image_url,
                'preview_text' => $article->preview_text,
                'price' => $article->price,
                'access_duration_hours' => $article->access_duration_hours,
                'view_count' => $article->view_count,
                'unlock_count' => $article->unlock_count,
                'rating_average' => $article->rating_average,
                'rating_count' => $article->rating_count,
                'author' => [
                    'id' => $article->author?->id,
                    'name' => $article->author?->name,
                ],
                'is_unlocked' => in_array($article->id, $activeUnlockIds, true),
            ])->values(),
        ]);
    }

    public function show(Request $request, Article $article): JsonResponse
    {
        $viewer = auth('sanctum')->user();
        $canModerate = $viewer?->hasRole(UserRole::ADMIN);
        $ownsArticle = $viewer?->id === $article->author_id;

        if ($article->status !== ArticleStatus::PUBLISHED && ! $canModerate && ! $ownsArticle) {
            abort(404);
        }

        $article->increment('view_count');
        $article->refresh()->load('author');

        $unlock = $article->activeUnlockFor($viewer);
        $canSeeFullContent = $canModerate || $ownsArticle || $unlock !== null;

        return response()->json([
            'article' => [
                'id' => $article->id,
                'category' => $article->category,
                'title' => $article->title,
                'slug' => $article->slug,
                'image_url' => $article->image_url,
                'preview_text' => $article->preview_text,
                'content' => $canSeeFullContent ? $article->content : null,
                'price' => $article->price,
                'commission_type' => $article->commission_type,
                'commission_value' => $article->commission_value,
                'access_duration_hours' => $article->access_duration_hours,
                'status' => $article->status,
                'view_count' => $article->view_count,
                'unlock_count' => $article->unlock_count,
                'rating_average' => $article->rating_average,
                'rating_count' => $article->rating_count,
                'published_at' => $article->published_at,
                'rejection_reason' => $article->rejection_reason,
                'author' => [
                    'id' => $article->author?->id,
                    'name' => $article->author?->name,
                ],
                'is_unlocked' => $unlock !== null,
                'access_expires_at' => $unlock?->expires_at,
            ],
        ]);
    }

    public function myArticles(Request $request): JsonResponse
    {
        $articles = Article::query()
            ->where('author_id', $request->user()->id)
            ->latest('updated_at')
            ->get();

        return response()->json([
            'articles' => $articles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateArticle($request);

        $article = Article::create([
            'author_id' => $request->user()->id,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'image_url' => $validated['image_url'] ?? null,
            'preview_text' => $validated['preview_text'],
            'content' => $validated['content'],
            'price' => $validated['price'],
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
            'access_duration_hours' => $validated['access_duration_hours'] ?? config('monetization.default_access_hours'),
            ...$this->publishingAttributes($request->user()),
        ]);

        return response()->json([
            'message' => 'Article published successfully.',
            'article' => $article->fresh(),
        ], 201);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        $this->ensureOwner($request->user(), $article);

        $validated = $this->validateArticle($request);

        $article->fill([
            'category' => $validated['category'],
            'title' => $validated['title'],
            'slug' => $validated['title'] !== $article->title
                ? $this->uniqueSlug($validated['title'], $article)
                : $article->slug,
            'image_url' => $validated['image_url'] ?? null,
            'preview_text' => $validated['preview_text'],
            'content' => $validated['content'],
            'price' => $validated['price'],
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
            'access_duration_hours' => $validated['access_duration_hours'] ?? config('monetization.default_access_hours'),
            ...$this->publishingAttributes($request->user(), $article),
        ])->save();

        return response()->json([
            'message' => 'Article updated and published successfully.',
            'article' => $article->fresh(),
        ]);
    }

    public function submit(Request $request, Article $article): JsonResponse
    {
        $this->ensureOwner($request->user(), $article);

        if ($article->status === ArticleStatus::PUBLISHED) {
            return response()->json([
                'message' => 'Article is already published.',
                'article' => $article->fresh(),
            ]);
        }

        $article->forceFill(
            $this->publishingAttributes($request->user(), $article),
        )->save();

        return response()->json([
            'message' => 'Article published successfully.',
            'article' => $article->fresh(),
        ]);
    }

    protected function validateArticle(Request $request): array
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'preview_text' => ['required', 'string'],
            'content' => ['required', 'string'],
            'price' => ['required', 'integer', 'min:1'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'commission_value' => ['required', 'integer', 'min:0'],
            'access_duration_hours' => ['nullable', 'integer', 'min:1'],
        ]);

        if (
            $validated['commission_type'] === CommissionType::PERCENTAGE->value
            && $validated['commission_value'] > 100
        ) {
            throw ValidationException::withMessages([
                'commission_value' => ['Percentage commission cannot exceed 100.'],
            ]);
        }

        if (
            $validated['commission_type'] === CommissionType::FIXED->value
            && $validated['commission_value'] > $validated['price']
        ) {
            throw ValidationException::withMessages([
                'commission_value' => ['Fixed commission cannot exceed the article price.'],
            ]);
        }

        $validated['category'] = trim((string) ($validated['category'] ?? ''));
        $validated['category'] = $validated['category'] !== ''
            ? $validated['category']
            : 'General';

        return $validated;
    }

    protected function ensureOwner(User $user, Article $article): void
    {
        if ($article->author_id !== $user->id) {
            abort(403, 'You can only manage your own articles.');
        }
    }

    protected function uniqueSlug(string $title, ?Article $ignore = null): string
    {
        $baseSlug = Str::slug($title);
        $slugBase = $baseSlug !== '' ? $baseSlug : Str::lower(Str::random(8));
        $slug = $slugBase;
        $counter = 1;

        while (
            Article::query()
                ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$slugBase}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function publishingAttributes(User $user, ?Article $article = null): array
    {
        return [
            'status' => ArticleStatus::PUBLISHED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'published_at' => $article?->published_at ?? now(),
            'rejection_reason' => null,
        ];
    }
}
