<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\AdminAlertNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(protected WalletService $walletService)
    {
    }

    public function request(User $author, int $amount): Withdrawal
    {
        if (! $author->hasRole(UserRole::AUTHOR)) {
            throw ValidationException::withMessages([
                'user' => ['Only authors can request withdrawals.'],
            ]);
        }

        if ($amount < config('monetization.min_withdrawal_credits')) {
            throw ValidationException::withMessages([
                'amount' => ['Withdrawal amount is below the minimum configured threshold.'],
            ]);
        }

        return DB::transaction(function () use ($author, $amount) {
            $referenceId = 'wd_'.Str::upper(Str::random(12));
            $transaction = $this->walletService->debit(
                $author,
                $amount,
                'withdrawal_request',
                $referenceId,
                ['author_id' => $author->id],
                null,
                TransactionStatus::PENDING,
            );

            $withdrawal = Withdrawal::create([
                'author_id' => $author->id,
                'amount' => $amount,
                'status' => WithdrawalStatus::PENDING,
                'reference_id' => $referenceId,
                'transaction_id' => $transaction->id,
            ]);

            Notification::send(
                User::query()->admins()->get(),
                new AdminAlertNotification(
                    title: 'Withdrawal request pending',
                    message: "{$author->name} requested {$amount} credits for withdrawal.",
                    data: [
                        'withdrawal_id' => $withdrawal->id,
                        'author_id' => $author->id,
                    ],
                ),
            );

            return $withdrawal->load(['author', 'transaction']);
        });
    }

    public function approve(Withdrawal $withdrawal, User $admin, ?string $notes = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $notes) {
            $lockedWithdrawal = Withdrawal::query()
                ->with('transaction')
                ->lockForUpdate()
                ->findOrFail($withdrawal->id);

            if ($lockedWithdrawal->status !== WithdrawalStatus::PENDING) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['This withdrawal request has already been processed.'],
                ]);
            }

            $lockedWithdrawal->transaction?->update([
                'status' => TransactionStatus::COMPLETED,
            ]);

            $lockedWithdrawal->forceFill([
                'status' => WithdrawalStatus::APPROVED,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'admin_notes' => $notes,
            ])->save();

            return $lockedWithdrawal->fresh(['author', 'transaction', 'processedBy']);
        });
    }

    public function reject(Withdrawal $withdrawal, User $admin, ?string $notes = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $notes) {
            $lockedWithdrawal = Withdrawal::query()
                ->with(['author', 'transaction'])
                ->lockForUpdate()
                ->findOrFail($withdrawal->id);

            if ($lockedWithdrawal->status !== WithdrawalStatus::PENDING) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['This withdrawal request has already been processed.'],
                ]);
            }

            $reversalTransaction = $this->walletService->credit(
                $lockedWithdrawal->author,
                $lockedWithdrawal->amount,
                'withdrawal_reversal',
                $lockedWithdrawal->reference_id,
                ['withdrawal_id' => $lockedWithdrawal->id],
                null,
                TransactionStatus::COMPLETED,
            );

            $lockedWithdrawal->transaction?->update([
                'status' => TransactionStatus::REVERSED,
            ]);

            $lockedWithdrawal->forceFill([
                'status' => WithdrawalStatus::REJECTED,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'admin_notes' => $notes,
                'reversal_transaction_id' => $reversalTransaction->id,
            ])->save();

            return $lockedWithdrawal->fresh([
                'author',
                'transaction',
                'reversalTransaction',
                'processedBy',
            ]);
        });
    }
}
