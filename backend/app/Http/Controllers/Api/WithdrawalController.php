<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(protected WithdrawalService $withdrawalService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'withdrawals' => $request->user()
                ->withdrawals()
                ->with(['transaction', 'reversalTransaction', 'processedBy:id,name'])
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $withdrawal = $this->withdrawalService->request($request->user(), $validated['amount']);

        return response()->json([
            'message' => 'Withdrawal request submitted for admin approval.',
            'withdrawal' => $withdrawal,
        ], 201);
    }
}
