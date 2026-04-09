<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ArticleUnlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnlockController extends Controller
{
    public function __construct(protected ArticleUnlockService $articleUnlockService)
    {
    }

    public function store(Request $request, Article $article): JsonResponse
    {
        $unlock = $this->articleUnlockService->unlock($request->user(), $article);

        return response()->json([
            'message' => 'Article unlocked successfully.',
            'unlock' => $unlock,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $unlocks = $request->user()
            ->unlocks()
            ->with('article.author:id,name')
            ->latest('unlocked_at')
            ->get()
            ->map(fn ($unlock) => [
                'id' => $unlock->id,
                'credits_spent' => $unlock->credits_spent,
                'author_earnings' => $unlock->author_earnings,
                'admin_commission' => $unlock->admin_commission,
                'unlocked_at' => $unlock->unlocked_at,
                'expires_at' => $unlock->expires_at,
                'is_active' => $unlock->isActive(),
                'article' => [
                    'id' => $unlock->article?->id,
                    'title' => $unlock->article?->title,
                    'slug' => $unlock->article?->slug,
                    'image_url' => $unlock->article?->image_url,
                    'author' => [
                        'id' => $unlock->article?->author?->id,
                        'name' => $unlock->article?->author?->name,
                    ],
                ],
            ]);

        return response()->json([
            'unlocks' => $unlocks,
        ]);
    }
}
