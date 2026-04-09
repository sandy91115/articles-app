<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function credit(
        User $user,
        int $amount,
        string $source,
        ?string $referenceId = null,
        array $meta = [],
        ?User $relatedUser = null,
        TransactionStatus $status = TransactionStatus::COMPLETED,
    ): Transaction {
        return $this->recordForUser(
            $user,
            $amount,
            TransactionType::CREDIT,
            $source,
            $referenceId,
            $meta,
            $relatedUser,
            $status,
        );
    }

    public function debit(
        User $user,
        int $amount,
        string $source,
        ?string $referenceId = null,
        array $meta = [],
        ?User $relatedUser = null,
        TransactionStatus $status = TransactionStatus::COMPLETED,
    ): Transaction {
        return $this->recordForUser(
            $user,
            $amount,
            TransactionType::DEBIT,
            $source,
            $referenceId,
            $meta,
            $relatedUser,
            $status,
        );
    }

    public function platformCredit(
        int $amount,
        string $source,
        ?string $referenceId = null,
        array $meta = [],
        ?User $relatedUser = null,
    ): Transaction {
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Transaction amount must be greater than zero.'],
            ]);
        }

        return Transaction::create([
            'user_id' => null,
            'related_user_id' => $relatedUser?->id,
            'type' => TransactionType::CREDIT,
            'amount' => $amount,
            'source' => $source,
            'status' => TransactionStatus::COMPLETED,
            'reference_id' => $referenceId,
            'balance_before' => 0,
            'balance_after' => 0,
            'meta' => $meta,
        ]);
    }

    protected function recordForUser(
        User $user,
        int $amount,
        TransactionType $type,
        string $source,
        ?string $referenceId,
        array $meta,
        ?User $relatedUser,
        TransactionStatus $status,
    ): Transaction {
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Transaction amount must be greater than zero.'],
            ]);
        }

        $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        $balanceBefore = (int) $lockedUser->wallet_balance;
        $balanceAfter = $type === TransactionType::CREDIT
            ? $balanceBefore + $amount
            : $balanceBefore - $amount;

        if ($balanceAfter < 0) {
            throw ValidationException::withMessages([
                'wallet' => ['Insufficient wallet balance.'],
            ]);
        }

        $lockedUser->forceFill([
            'wallet_balance' => $balanceAfter,
        ])->save();

        $user->setAttribute('wallet_balance', $balanceAfter);

        return Transaction::create([
            'user_id' => $lockedUser->id,
            'related_user_id' => $relatedUser?->id,
            'type' => $type,
            'amount' => $amount,
            'source' => $source,
            'status' => $status,
            'reference_id' => $referenceId,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'meta' => $meta,
        ]);
    }
}
