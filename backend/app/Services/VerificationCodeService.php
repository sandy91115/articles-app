<?php

namespace App\Services;

use App\Enums\VerificationPurpose;
use App\Mail\EmailVerificationOtpMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class VerificationCodeService
{
    public function issue(User $user): array
    {
        VerificationCode::query()
            ->where('email', $user->email)
            ->where('purpose', VerificationPurpose::EMAIL_VERIFICATION)
            ->delete();

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification = VerificationCode::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'purpose' => VerificationPurpose::EMAIL_VERIFICATION,
            'code' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes(config('monetization.verification_code_ttl_minutes')),
        ]);

        Log::info('Verification code issued.', [
            'email' => $user->email,
            'code' => $plainCode,
        ]);

        try {
            Mail::to($user->email)->send(new EmailVerificationOtpMail(
                userName: $user->name,
                code: $plainCode,
                expiresInMinutes: (int) (config('monetization.verification_code_ttl_minutes') ?? 10),
            ));
        } catch (Throwable $exception) {
            Log::warning('Unable to send verification OTP email.', [
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }

        return [
            'verification' => $verification,
            'plain_code' => $plainCode,
        ];
    }

    public function verify(User $user, string $code): User
    {
        $verification = VerificationCode::query()
            ->where('email', $user->email)
            ->where('purpose', VerificationPurpose::EMAIL_VERIFICATION)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $verification || $verification->isExpired() || ! Hash::check($code, $verification->code)) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid or has expired.'],
            ]);
        }

        $verification->forceFill([
            'consumed_at' => now(),
        ])->save();

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        VerificationCode::query()
            ->where('email', $user->email)
            ->where('purpose', VerificationPurpose::EMAIL_VERIFICATION)
            ->where('id', '!=', $verification->id)
            ->delete();

        return $user->fresh();
    }
}
