<?php

namespace App\Services;

use App\Enums\PaymentOrderStatus;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;

class RazorpayService
{
    public function __construct(protected WalletService $walletService)
    {
    }

    public function createOrder(User $user, int $credits): PaymentOrder
    {
        if ($credits < config('monetization.min_purchase_credits')) {
            throw ValidationException::withMessages([
                'credits' => ['Requested credits are below the minimum purchase limit.'],
            ]);
        }

        $reference = 'po_'.Str::upper(Str::random(16));
        $amountInPaise = $this->creditsToPaise($credits);
        $gatewayOrder = $this->api()->order->create([
            'receipt' => $reference,
            'amount' => $amountInPaise,
            'currency' => config('services.razorpay.currency', 'INR'),
            'notes' => [
                'user_id' => $user->id,
                'credits' => $credits,
            ],
        ]);

        return PaymentOrder::create([
            'user_id' => $user->id,
            'provider' => 'razorpay',
            'reference' => $reference,
            'provider_order_id' => $gatewayOrder['id'],
            'credit_amount' => $credits,
            'amount_in_paise' => $amountInPaise,
            'currency' => config('services.razorpay.currency', 'INR'),
            'status' => PaymentOrderStatus::PENDING,
            'meta' => [
                'gateway_order' => $this->normalizePayload($gatewayOrder),
            ],
        ]);
    }

    public function confirmPayment(
        User $user,
        string $providerOrderId,
        string $providerPaymentId,
        string $signature,
    ): PaymentOrder {
        $order = PaymentOrder::query()
            ->where('user_id', $user->id)
            ->where('provider_order_id', $providerOrderId)
            ->firstOrFail();

        if ($order->status === PaymentOrderStatus::PAID) {
            return $order;
        }

        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $providerOrderId,
                'razorpay_payment_id' => $providerPaymentId,
                'razorpay_signature' => $signature,
            ]);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'payment' => ['Unable to verify the payment signature.'],
            ]);
        }

        return $this->markAsPaid($order, [
            'provider_payment_id' => $providerPaymentId,
            'provider_signature' => $signature,
            'meta' => [
                'confirmed_via' => 'client',
            ],
        ]);
    }

    public function handleWebhook(string $payload, ?string $signature): ?PaymentOrder
    {
        $webhookSecret = (string) config('services.razorpay.webhook_secret');

        if ($webhookSecret === '' || ! $signature) {
            throw ValidationException::withMessages([
                'webhook' => ['Webhook secret or signature is missing.'],
            ]);
        }

        try {
            $this->api()->utility->verifyWebhookSignature($payload, $signature, $webhookSecret);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'webhook' => ['Webhook signature could not be verified.'],
            ]);
        }

        $decoded = json_decode($payload, true);
        $event = $decoded['event'] ?? null;

        if (! in_array($event, ['payment.captured', 'order.paid'], true)) {
            return null;
        }

        $payment = $decoded['payload']['payment']['entity'] ?? null;

        if (! $payment || empty($payment['order_id'])) {
            return null;
        }

        $order = PaymentOrder::query()
            ->where('provider_order_id', $payment['order_id'])
            ->first();

        if (! $order) {
            return null;
        }

        return $this->markAsPaid($order, [
            'provider_payment_id' => $payment['id'] ?? null,
            'provider_signature' => $signature,
            'meta' => [
                'webhook_event' => $event,
                'webhook_payload' => $decoded,
            ],
        ]);
    }

    protected function markAsPaid(PaymentOrder $order, array $payload): PaymentOrder
    {
        return DB::transaction(function () use ($order, $payload) {
            $lockedOrder = PaymentOrder::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status === PaymentOrderStatus::PAID) {
                return $lockedOrder;
            }

            $lockedOrder->forceFill([
                'provider_payment_id' => $payload['provider_payment_id'] ?? $lockedOrder->provider_payment_id,
                'provider_signature' => $payload['provider_signature'] ?? $lockedOrder->provider_signature,
                'status' => PaymentOrderStatus::PAID,
                'paid_at' => now(),
                'meta' => array_merge($lockedOrder->meta ?? [], $payload['meta'] ?? []),
            ])->save();

            $this->walletService->credit(
                $lockedOrder->user,
                $lockedOrder->credit_amount,
                'credit_purchase',
                $lockedOrder->reference,
                [
                    'payment_order_id' => $lockedOrder->id,
                    'provider_order_id' => $lockedOrder->provider_order_id,
                    'provider_payment_id' => $lockedOrder->provider_payment_id,
                ],
            );

            return $lockedOrder->fresh();
        });
    }

    protected function creditsToPaise(int $credits): int
    {
        $creditsPerRupee = max(1, (int) config('monetization.credits_per_rupee', 1));

        return (int) round(($credits / $creditsPerRupee) * 100);
    }

    protected function api(): Api
    {
        $keyId = (string) config('services.razorpay.key_id');
        $keySecret = (string) config('services.razorpay.key_secret');

        if ($keyId === '' || $keySecret === '') {
            throw ValidationException::withMessages([
                'payment' => ['Razorpay is not configured yet. Add the API keys to continue.'],
            ]);
        }

        return new Api($keyId, $keySecret);
    }

    protected function normalizePayload(mixed $payload): array
    {
        $encoded = json_encode($payload);

        return $encoded ? json_decode($encoded, true) ?? [] : [];
    }
}
