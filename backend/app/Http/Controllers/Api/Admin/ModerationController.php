<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ModerationController extends Controller
{
    public function pending(): JsonResponse
    {
        return response()->json([
            'articles' => Article::query()
                ->with('author:id,name,email')
                ->where('status', ArticleStatus::PENDING_APPROVAL)
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function approve(Request $request, Article $article): JsonResponse
    {
        if ($article->status !== ArticleStatus::PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'article' => ['Only pending articles can be approved.'],
            ]);
        }

        $validated = $request->validate([
            'price' => ['sometimes', 'integer', 'min:1'],
            'commission_type' => ['sometimes', 'in:percentage,fixed'],
            'commission_value' => ['sometimes', 'integer', 'min:0'],
            'access_duration_hours' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $price = $validated['price'] ?? $article->price;
        $commissionType = $validated['commission_type'] ?? $article->commission_type->value;
        $commissionValue = $validated['commission_value'] ?? $article->commission_value;

        if ($commissionType === CommissionType::PERCENTAGE->value && $commissionValue > 100) {
            throw ValidationException::withMessages([
                'commission_value' => ['Percentage commission cannot exceed 100.'],
            ]);
        }

        if ($commissionType === CommissionType::FIXED->value && $commissionValue > $price) {
            throw ValidationException::withMessages([
                'commission_value' => ['Fixed commission cannot exceed the article price.'],
            ]);
        }

        $article->forceFill([
            'price' => $price,
            'commission_type' => $commissionType,
            'commission_value' => $commissionValue,
            'access_duration_hours' => $validated['access_duration_hours'] ?? $article->access_duration_hours,
            'status' => ArticleStatus::PUBLISHED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'published_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return response()->json([
            'message' => 'Article approved and published.',
            'article' => $article->fresh(['author', 'approver']),
        ]);
    }

    public function reject(Request $request, Article $article): JsonResponse
    {
        if ($article->status !== ArticleStatus::PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'article' => ['Only pending articles can be rejected.'],
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $article->forceFill([
            'status' => ArticleStatus::REJECTED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'published_at' => null,
            'rejection_reason' => $validated['reason'],
        ])->save();

        return response()->json([
            'message' => 'Article rejected.',
            'article' => $article->fresh(['author', 'approver']),
        ]);
    }
}
