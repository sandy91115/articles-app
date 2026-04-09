<?php

namespace App\Services;

use App\Enums\CommissionType;
use App\Models\Article;

class CommissionService
{
    public function split(Article $article): array
    {
        $price = (int) $article->price;
        $commission = match ($article->commission_type) {
            CommissionType::PERCENTAGE => (int) round(($price * $article->commission_value) / 100),
            CommissionType::FIXED => min($price, (int) $article->commission_value),
        };

        $commission = min($price, max(0, $commission));

        return [
            'price' => $price,
            'admin_commission' => $commission,
            'author_earnings' => max(0, $price - $commission),
        ];
    }
}
