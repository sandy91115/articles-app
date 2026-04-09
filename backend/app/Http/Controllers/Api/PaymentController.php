<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected RazorpayService $razorpayService)
    {
    }

    public function confirmRazorpay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_order_id' => ['required', 'string'],
            'provider_payment_id' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $order = $this->razorpayService->confirmPayment(
            $request->user(),
            $validated['provider_order_id'],
            $validated['provider_payment_id'],
            $validated['signature'],
        );

        return response()->json([
            'message' => 'Payment verified successfully.',
            'order' => $order,
            'wallet_balance' => $request->user()->fresh()->wallet_balance,
        ]);
    }

    public function razorpayWebhook(Request $request): JsonResponse
    {
        $order = $this->razorpayService->handleWebhook(
            $request->getContent(),
            $request->header('X-Razorpay-Signature'),
        );

        return response()->json([
            'message' => $order ? 'Webhook processed.' : 'Webhook ignored.',
        ]);
    }
}
