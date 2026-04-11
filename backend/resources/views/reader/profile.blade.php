@extends('reader.layout')

@section('page_title', 'Profile')
@section('page_heading', 'Profile')
@section('page_kicker', 'Reader Profile')
@section('page_subtitle', 'Profile photo, account details, password management, and the same personal reader identity visible in the Flutter app.')

@section('reader_content')
    <section class="reader-profile-hero">
        <div class="reader-avatar reader-avatar-xl">
            @if ($bundle['user']['profile_photo_url'])
                <img src="{{ $bundle['user']['profile_photo_url'] }}" alt="{{ $bundle['user']['name'] }}">
            @else
                <span>{{ $bundle['user']['initials'] }}</span>
            @endif
        </div>

        <div>
            <p class="reader-eyebrow">Reader Identity</p>
            <h2>{{ $bundle['user']['name'] }}</h2>
            <p>{{ $bundle['user']['email'] }}</p>

            <div class="reader-chip-row">
                @if ($bundle['user']['username'])
                    <span class="reader-chip">{{ '@'.$bundle['user']['username'] }}</span>
                @endif
                <span class="reader-chip">{{ number_format($bundle['user']['wallet_balance']) }} coins</span>
                <span class="reader-chip">Joined {{ $bundle['user']['created_label'] }}</span>
            </div>
        </div>
    </section>

    <section class="reader-two-column">
        <article class="reader-panel">
            <p class="reader-eyebrow">Account Info</p>
            <h2>Reader details</h2>

            <div class="reader-info-list">
                @if ($bundle['user']['username'])
                    <div>
                        <span>Username</span>
                        <strong>{{ $bundle['user']['username'] }}</strong>
                    </div>
                @endif
                <div>
                    <span>Email</span>
                    <strong>{{ $bundle['user']['email'] }}</strong>
                </div>
                <div>
                    <span>Phone</span>
                    <strong>{{ $bundle['user']['phone'] ?: 'Not added yet' }}</strong>
                </div>
                <div>
                    <span>Joined</span>
                    <strong>{{ $bundle['user']['created_label'] }}</strong>
                </div>
            </div>
        </article>

        <article class="reader-panel">
            <p class="reader-eyebrow">Profile Photo</p>
            <h2>{{ $bundle['user']['profile_photo_url'] ? 'Change photo' : 'Upload photo' }}</h2>

            <form class="reader-form" method="POST" action="{{ route('reader.profile.photo') }}" enctype="multipart/form-data">
                @csrf

                <label>
                    <span>Choose image</span>
                    <input type="file" name="photo" accept="image/*" required>
                </label>

                <button class="reader-primary-button" type="submit">
                    {{ $bundle['user']['profile_photo_url'] ? 'Update Photo' : 'Upload Photo' }}
                </button>
            </form>
        </article>
    </section>

    <section class="reader-section">
        <div class="reader-section-head">
            <div>
                <p class="reader-eyebrow">Security</p>
                <h2>Change password</h2>
            </div>
        </div>

        <article class="reader-panel">
            <form class="reader-form" method="POST" action="{{ route('reader.profile.password') }}">
                @csrf
                @method('PUT')

                <div class="reader-form-grid">
                    <label>
                        <span>Current password</span>
                        <input type="password" name="current_password" required>
                    </label>

                    <label>
                        <span>New password</span>
                        <input type="password" name="password" required>
                    </label>
                </div>

                <label>
                    <span>Confirm new password</span>
                    <input type="password" name="password_confirmation" required>
                </label>

                <button class="reader-primary-button" type="submit">Update Password</button>
            </form>
        </article>
    </section>
@endsection
