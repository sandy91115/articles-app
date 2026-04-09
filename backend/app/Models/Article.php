<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $fillable = [
        'author_id',
        'approved_by',
        'category',
        'title',
        'slug',
        'image_url',
        'preview_text',
        'content',
        'price',
        'commission_type',
        'commission_value',
        'access_duration_hours',
        'status',
        'view_count',
        'unlock_count',
        'rating_average',
        'rating_count',
        'published_at',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'commission_type' => CommissionType::class,
            'status' => ArticleStatus::class,
            'price' => 'integer',
            'commission_value' => 'integer',
            'access_duration_hours' => 'integer',
            'view_count' => 'integer',
            'unlock_count' => 'integer',
            'rating_average' => 'float',
            'rating_count' => 'integer',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(ArticleUnlock::class);
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::PUBLISHED;
    }

    public function activeUnlockFor(?User $user): ?ArticleUnlock
    {
        if (! $user) {
            return null;
        }

        return $this->unlocks()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('expires_at')
            ->first();
    }
}
