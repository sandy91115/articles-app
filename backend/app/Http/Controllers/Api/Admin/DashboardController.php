<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentOrderStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleUnlock;
use App\Models\PaymentOrder;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalRevenuePaise = PaymentOrder::query()
            ->where('status', PaymentOrderStatus::PAID)
            ->sum('amount_in_paise');

        return response()->json([
            'summary' => [
                'total_revenue_paise' => $totalRevenuePaise,
                'total_revenue_rupees' => round($totalRevenuePaise / 100, 2),
                'total_credits_sold' => PaymentOrder::query()
                    ->where('status', PaymentOrderStatus::PAID)
                    ->sum('credit_amount'),
                'author_earnings' => ArticleUnlock::query()->sum('author_earnings'),
                'commission_earned' => ArticleUnlock::query()->sum('admin_commission'),
                'pending_withdrawals_count' => Withdrawal::query()
                    ->where('status', WithdrawalStatus::PENDING)
                    ->count(),
                'pending_withdrawals_amount' => Withdrawal::query()
                    ->where('status', WithdrawalStatus::PENDING)
                    ->sum('amount'),
            ],
            'article_performance' => Article::query()
                ->with('author:id,name')
                ->orderByDesc('unlock_count')
                ->orderByDesc('view_count')
                ->limit(10)
                ->get()
                ->map(fn (Article $article) => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'view_count' => $article->view_count,
                    'unlock_count' => $article->unlock_count,
                    'price' => $article->price,
                    'author' => [
                        'id' => $article->author?->id,
                        'name' => $article->author?->name,
                    ],
                ]),
        ]);
    }
}
