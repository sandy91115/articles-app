<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected VerificationCodeService $verificationCodeService) {}

    public function register(Request $request): JsonResponse
    {
        $role = $request->input('role', 'reader');

        $request->merge([
            'role' => $role,
            'phone' => $request->filled('phone')
                ? trim((string) $request->input('phone'))
                : null,
            'username' => $request->filled('username') || $request->filled('user')
                ? Str::lower(
                    trim((string) ($request->input('username') ?? $request->input('user') ?? ''))
                )
                : null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => [
                Rule::requiredIf($role === 'reader'),
                'nullable',
                'string',
                'max:20',
            ],
            'username' => [
                Rule::requiredIf($role === 'reader'),
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_.-]+$/',
                'unique:users,username',
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', 'in:author,reader'],
        ]);

        $user = User::create($validated);
        $verification = $this->verificationCodeService->issue($user);

        return response()->json([
            'message' => 'Account created. Verify the OTP to continue.',
            'user' => $user,
            ...$this->debugCode($verification['plain_code']),
        ], 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'issue_token' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->where('email', $validated['email'])->firstOrFail();
        $user = $this->verificationCodeService->verify($user, $validated['code']);
        $shouldIssueToken = array_key_exists('issue_token', $validated)
            ? (bool) $validated['issue_token']
            : true;

        if (! $shouldIssueToken) {
            return response()->json([
                'message' => 'Account verified successfully.',
                'user' => $user,
            ]);
        }

        $token = $user->createToken($validated['device_name'] ?? 'mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Account verified successfully.',
            ...$this->authPayload($user, $token),
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'This account is already verified.',
            ]);
        }

        $verification = $this->verificationCodeService->issue($user);

        return response()->json([
            'message' => 'A new verification code has been issued.',
            ...$this->debugCode($verification['plain_code']),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->email_verified_at) {
            $verification = $this->verificationCodeService->issue($user);

            return response()->json([
                'message' => 'Verify your account before logging in.',
                ...$this->debugCode($verification['plain_code']),
            ], 403);
        }

        $token = $user->createToken($validated['device_name'] ?? 'mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            ...$this->authPayload($user, $token),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function updateProfilePhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $user = $request->user();
        $photo = $validated['photo'];
        $directory = public_path('uploads/profile-photos');

        File::ensureDirectoryExists($directory);

        $filename = (string) Str::uuid().'.'.$photo->getClientOriginalExtension();
        $photo->move($directory, $filename);

        if ($user->profile_photo_url) {
            $existingPath = public_path(ltrim($user->profile_photo_url, '/'));
            if (File::exists($existingPath)) {
                File::delete($existingPath);
            }
        }

        $user->forceFill([
            'profile_photo_url' => '/uploads/profile-photos/'.$filename,
        ])->save();

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    protected function authPayload(User $user, string $token): array
    {
        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    protected function debugCode(?string $code): array
    {
        return config('app.debug') && $code
            ? ['debug_code' => $code]
            : [];
    }
}
