<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleUnlock extends Model
{
    protected $fillable = [
        'user_id',
        'article_id',
        'transaction_id',
        'credits_spent',
        'author_earnings',
        'admin_commission',
        'unlocked_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'credits_spent' => 'integer',
            'author_earnings' => 'integer',
            'admin_commission' => 'integer',
            'unlocked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
