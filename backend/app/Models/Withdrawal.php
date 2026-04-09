<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'author_id',
        'amount',
        'status',
        'reference_id',
        'transaction_id',
        'reversal_transaction_id',
        'processed_by',
        'processed_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => WithdrawalStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reversal_transaction_id');
    }
}
