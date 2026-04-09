<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(protected RazorpayService $razorpayService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'wallet_balance' => $request->user()->wallet_balance,
            'credits_per_rupee' => config('monetization.credits_per_rupee'),
            'min_purchase_credits' => config('monetization.min_purchase_credits'),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        return response()->json([
            'transactions' => $request->user()
                ->transactions()
                ->latest('id')
                ->get(),
        ]);
    }

    public function createPurchaseOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credits' => ['required', 'integer', 'min:1'],
        ]);

        $order = $this->razorpayService->createOrder($request->user(), $validated['credits']);

        return response()->json([
            'message' => 'Purchase order created successfully.',
            'order' => $order,
            'razorpay_key_id' => config('services.razorpay.key_id'),
        ], 201);
    }
}
