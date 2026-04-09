<?php

namespace App\Models;

use App\Enums\PaymentOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'reference',
        'provider_order_id',
        'provider_payment_id',
        'provider_signature',
        'credit_amount',
        'amount_in_paise',
        'currency',
        'status',
        'meta',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_amount' => 'integer',
            'amount_in_paise' => 'integer',
            'status' => PaymentOrderStatus::class,
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
