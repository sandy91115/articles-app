<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalApprovalController extends Controller
{
    public function __construct(protected WithdrawalService $withdrawalService)
    {
    }

    public function pending(): JsonResponse
    {
        return response()->json([
            'withdrawals' => Withdrawal::query()
                ->with(['author:id,name,email', 'transaction'])
                ->where('status', WithdrawalStatus::PENDING)
                ->latest('id')
                ->get(),
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string'],
        ]);

        $withdrawal = $this->withdrawalService->approve(
            $withdrawal,
            $request->user(),
            $validated['admin_notes'] ?? null,
        );

        return response()->json([
            'message' => 'Withdrawal approved.',
            'withdrawal' => $withdrawal,
        ]);
    }

    public function reject(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string'],
        ]);

        $withdrawal = $this->withdrawalService->reject(
            $withdrawal,
            $request->user(),
            $validated['admin_notes'] ?? null,
        );

        return response()->json([
            'message' => 'Withdrawal rejected and funds restored.',
            'withdrawal' => $withdrawal,
        ]);
    }
}
