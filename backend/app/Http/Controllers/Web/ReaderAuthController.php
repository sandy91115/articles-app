<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReaderAuthController extends Controller
{
    public function __construct(
        protected VerificationCodeService $verificationCodeService,
    ) {}

    public function entry(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('reader.auth');
        }

        if ($user->hasRole(UserRole::READER)) {
            return redirect()->route('reader.home');
        }

        return redirect()->route('admin.portal');
    }

    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $this->entry($request);
        }

        $mode = $request->query('mode', 'login');
        if (! in_array($mode, ['login', 'signup', 'verify'], true)) {
            $mode = 'login';
        }

        $showVerifyTab = $mode === 'verify' || session()->has('debug_code') || session()->has('status');

        return view('reader.auth', [
            'mode' => $mode,
            'knownEmail' => $request->query('email', old('email', old('signup_email'))),
            'debugCode' => session('debug_code'),
            'showVerifyTab' => $showVerifyTab,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->hasRole(UserRole::READER)) {
            throw ValidationException::withMessages([
                'email' => ['This web app is reserved for readers. Admins and authors should use the portal at /admin.'],
            ]);
        }

        if (! $user->email_verified_at) {
            $verification = $this->verificationCodeService->issue($user);

            return redirect()
                ->route('reader.auth', ['mode' => 'verify', 'email' => $user->email])
                ->with('status', 'Verify your account before logging in. We sent a fresh OTP to your inbox.')
                ->with('debug_code', $this->debugCode($verification['plain_code']));
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->flash('reader_prompt_wallet', true);

        return redirect()
            ->route('reader.home')
            ->with('status', 'Welcome back to the reader web app.');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => $request->filled('phone')
                ? trim((string) $request->input('phone'))
                : null,
            'username' => $request->filled('username')
                ? Str::lower(trim((string) $request->input('username')))
                : null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'username' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_.-]+$/',
                'unique:users,username',
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'username' => 'username',
            'password_confirmation' => 'confirm password',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'role' => UserRole::READER,
        ]);

        $verification = $this->verificationCodeService->issue($user);

        return redirect()
            ->route('reader.auth', ['mode' => 'verify', 'email' => $user->email])
            ->with('status', 'Account created. Verify the 6-digit OTP to continue.')
            ->with('debug_code', $this->debugCode($verification['plain_code']));
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('role', UserRole::READER)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['We could not find a reader account for that email address.'],
            ]);
        }

        if ($user->email_verified_at) {
            return redirect()
                ->route('reader.auth', ['mode' => 'login', 'email' => $user->email])
                ->with('status', 'This account is already verified. Please sign in.');
        }

        $this->verificationCodeService->verify($user, $validated['code']);

        Auth::login($user->fresh());
        $request->session()->regenerate();
        $request->session()->flash('reader_prompt_wallet', true);

        return redirect()
            ->route('reader.home')
            ->with('status', 'Account verified successfully. Welcome to the reader web app.');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('role', UserRole::READER)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['We could not find a reader account for that email address.'],
            ]);
        }

        if ($user->email_verified_at) {
            return redirect()
                ->route('reader.auth', ['mode' => 'login', 'email' => $user->email])
                ->with('status', 'This account is already verified. You can sign in now.');
        }

        $verification = $this->verificationCodeService->issue($user);

        return redirect()
            ->route('reader.auth', ['mode' => 'verify', 'email' => $user->email])
            ->with('status', 'A fresh OTP has been issued for this reader account.')
            ->with('debug_code', $this->debugCode($verification['plain_code']));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('reader.auth')
            ->with('status', 'You have been logged out.');
    }

    protected function debugCode(?string $code): ?string
    {
        return config('app.debug') && $code ? $code : null;
    }
}
