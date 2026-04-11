@extends('reader.base')

@section('title', 'Mono Reader Login')
@section('body_class', 'reader-auth-body')

@section('body')
    <div class="reader-auth-shell">
        <section class="reader-auth-hero">
            <p class="reader-eyebrow">Daily Reading</p>
            <h1>A calmer home for headlines, features, interviews, and thoughtful reads.</h1>
            <p>
                Flutter app mobile ke liye rahega, aur ab wahi reader-side experience web par bhi mil raha hai.
                Login karo, signup karo, OTP verify karo, aur seedha article-reading flow me aa jao.
            </p>

            <div class="reader-auth-chip-row">
                <span class="reader-hero-chip">Daily stories</span>
                <span class="reader-hero-chip">Reader signup</span>
                <span class="reader-hero-chip">Email OTP</span>
            </div>

            <div class="reader-auth-showcase">
                <article>
                    <small>Home</small>
                    <strong>Top picks, quick search, category feeds</strong>
                </article>
                <article>
                    <small>Wallet</small>
                    <strong>Coins, top-up preview, unlock-ready balance</strong>
                </article>
                <article>
                    <small>Profile</small>
                    <strong>Photo upload, password update, reading identity</strong>
                </article>
            </div>
        </section>

        <section class="reader-auth-panel">
            <div class="reader-auth-panel-head">
                <p class="reader-eyebrow">Reader Access</p>
                <h2>
                    @if ($mode === 'signup')
                        Create reader account
                    @elseif ($mode === 'verify')
                        Verify email OTP
                    @else
                        Reader login
                    @endif
                </h2>
                <p>
                    @if ($mode === 'signup')
                        Set up your reader profile, then confirm the 6-digit OTP from email before the app opens.
                    @elseif ($mode === 'verify')
                        Enter the OTP sent to your email. Verification logs you straight into the reader web app.
                    @else
                        Open the latest features, opinion, culture, and long-form articles from your Laravel backend.
                    @endif
                </p>
            </div>

            <div class="reader-auth-tabs">
                <a class="reader-auth-tab {{ $mode === 'login' ? 'is-active' : '' }}" href="{{ route('reader.auth', ['mode' => 'login', 'email' => $knownEmail]) }}">Login</a>
                <a class="reader-auth-tab {{ $mode === 'signup' ? 'is-active' : '' }}" href="{{ route('reader.auth', ['mode' => 'signup']) }}">Sign up</a>
                @if ($showVerifyTab)
                    <a class="reader-auth-tab {{ $mode === 'verify' ? 'is-active' : '' }}" href="{{ route('reader.auth', ['mode' => 'verify', 'email' => $knownEmail]) }}">Verify OTP</a>
                @endif
            </div>

            @include('reader.partials.alerts')

            @if ($mode === 'signup')
                <form class="reader-form" method="POST" action="{{ route('reader.register') }}">
                    @csrf

                    <label>
                        <span>Name</span>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Reader One" required>
                    </label>

                    <div class="reader-form-grid">
                        <label>
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="reader.one@example.com" required>
                        </label>

                        <label>
                            <span>Phone</span>
                            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="9876543210" required>
                        </label>
                    </div>

                    <label>
                        <span>Username</span>
                        <input type="text" name="username" value="{{ old('username') }}" placeholder="reader.one" required>
                    </label>

                    <div class="reader-form-grid">
                        <label>
                            <span>Password</span>
                            <input type="password" name="password" placeholder="Minimum 8 characters" required>
                        </label>

                        <label>
                            <span>Confirm password</span>
                            <input type="password" name="password_confirmation" placeholder="Re-enter your password" required>
                        </label>
                    </div>

                    <p class="reader-helper-text">A verification OTP will be sent to this email before the account is activated.</p>

                    <button class="reader-primary-button" type="submit">Create account</button>
                </form>
            @elseif ($mode === 'verify')
                @if ($debugCode)
                    <div class="reader-debug-card">
                        <small>Debug OTP</small>
                        <strong>{{ $debugCode }}</strong>
                        <p>Visible only while the backend runs with <code>APP_DEBUG=true</code>.</p>
                    </div>
                @endif

                <form class="reader-form" method="POST" action="{{ route('reader.verify-otp') }}">
                    @csrf

                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email', $knownEmail) }}" placeholder="reader.one@example.com" required>
                    </label>

                    <label>
                        <span>OTP code</span>
                        <input type="text" name="code" value="{{ old('code') }}" maxlength="6" inputmode="numeric" placeholder="6-digit code" required>
                    </label>

                    <p class="reader-helper-text">Use the code from your inbox. Verification opens the reader web experience immediately.</p>

                    <button class="reader-primary-button" type="submit">Verify and continue</button>
                </form>

                <form class="reader-inline-form" method="POST" action="{{ route('reader.resend-otp') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ old('email', $knownEmail) }}">
                    <button class="reader-ghost-button" type="submit">Resend OTP</button>
                </form>
            @else
                <form class="reader-form" method="POST" action="{{ route('reader.login') }}">
                    @csrf

                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email', $knownEmail) }}" placeholder="aarav.mehta@example.com" required>
                    </label>

                    <label>
                        <span>Password</span>
                        <input type="password" name="password" placeholder="password" required>
                    </label>

                    <p class="reader-helper-text">If this reader account is still unverified, login will issue a fresh OTP and move you to the verification step.</p>

                    <button class="reader-primary-button" type="submit">Open Reader Web</button>
                </form>

                <div class="reader-auth-footer-links">
                    <a href="{{ route('reader.auth', ['mode' => 'signup']) }}">Create reader account</a>
                    <a href="{{ route('reader.auth', ['mode' => 'verify', 'email' => $knownEmail]) }}">Already have an OTP?</a>
                </div>
            @endif
        </section>
    </div>
@endsection
