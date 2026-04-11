@extends('reader.base')

@section('title', trim($__env->yieldContent('page_title')) !== '' ? trim($__env->yieldContent('page_title')).' | Mono Reader' : 'Mono Reader')
@section('body_class', 'reader-body')

@section('body')
    <div class="reader-app-shell">
        <aside class="reader-sidebar">
            <div class="reader-sidebar-head">
                <a class="reader-brand" href="{{ route('reader.home') }}">
                    <span class="reader-brand-mark">MR</span>
                    <span>
                        <small>Daily Reading</small>
                        <strong>Mono Reader Web</strong>
                    </span>
                </a>

                <div class="reader-sidebar-card">
                    <div class="reader-avatar reader-avatar-large">
                        @if ($bundle['user']['profile_photo_url'])
                            <img src="{{ $bundle['user']['profile_photo_url'] }}" alt="{{ $bundle['user']['name'] }}">
                        @else
                            <span>{{ $bundle['user']['initials'] }}</span>
                        @endif
                    </div>

                    <div class="reader-sidebar-copy">
                        <p class="reader-eyebrow">Signed in</p>
                        <h2>{{ $bundle['user']['name'] }}</h2>
                        <p>{{ $bundle['user']['username'] ? '@'.$bundle['user']['username'] : $bundle['user']['email'] }}</p>
                    </div>
                </div>
            </div>

            <section class="reader-sidebar-group">
                <div class="reader-sidebar-group-head">
                    <div>
                        <p class="reader-eyebrow">Navigate</p>
                        <strong>Reader workspace</strong>
                    </div>
                    <span>5 sections</span>
                </div>

                <nav class="reader-nav">
                    <a class="reader-nav-link {{ $activeTab === 'home' ? 'is-active' : '' }}" href="{{ route('reader.home') }}">Home</a>
                    <a class="reader-nav-link {{ $activeTab === 'search' ? 'is-active' : '' }}" href="{{ route('reader.search') }}">Search</a>
                    <a class="reader-nav-link {{ $activeTab === 'library' ? 'is-active' : '' }}" href="{{ route('reader.library') }}">Your Articles</a>
                    <a class="reader-nav-link {{ $activeTab === 'wallet' ? 'is-active' : '' }}" href="{{ route('reader.wallet') }}">Wallet</a>
                    <a class="reader-nav-link {{ $activeTab === 'profile' ? 'is-active' : '' }}" href="{{ route('reader.profile') }}">Profile</a>
                </nav>
            </section>

            <section class="reader-sidebar-group reader-sidebar-balance-group">
                <div class="reader-sidebar-group-head">
                    <div>
                        <p class="reader-eyebrow">Wallet</p>
                        <strong>Available balance</strong>
                    </div>
                    <span>Ready now</span>
                </div>

                <div class="reader-wallet-pill">
                    <span>Wallet Balance</span>
                    <strong>{{ number_format($bundle['wallet']['wallet_balance']) }} coins</strong>
                </div>
            </section>

            <div class="reader-sidebar-note">
                <p class="reader-eyebrow">Web + App</p>
                <p>The Flutter app stays for mobile readers, while this Blade version brings the same reading flow to the web.</p>
            </div>

            <div class="reader-sidebar-actions">
                <a class="reader-secondary-button" href="{{ route('admin.portal') }}">Open Admin Portal</a>

                <form method="POST" action="{{ route('reader.logout') }}">
                    @csrf
                    <button class="reader-ghost-button reader-danger-button" type="submit">Logout</button>
                </form>
            </div>
        </aside>

        <main class="reader-content-shell">
            <header class="reader-page-header">
                <div class="reader-page-header-copy">
                    <p class="reader-eyebrow">@yield('page_kicker', 'Reader Experience')</p>
                    <h1>@yield('page_heading', 'Reader')</h1>
                    @hasSection('page_subtitle')
                        <p class="reader-page-subtitle">@yield('page_subtitle')</p>
                    @endif

                    @hasSection('page_badges')
                        @yield('page_badges')
                    @endif
                </div>

                @hasSection('page_actions')
                    <div class="reader-page-header-side">
                        @yield('page_actions')
                    </div>
                @endif
            </header>

            @include('reader.partials.alerts')

            @yield('reader_content')
        </main>
    </div>

    <nav class="reader-bottom-nav">
        <a class="reader-bottom-link {{ $activeTab === 'home' ? 'is-active' : '' }}" href="{{ route('reader.home') }}">Home</a>
        <a class="reader-bottom-link {{ $activeTab === 'search' ? 'is-active' : '' }}" href="{{ route('reader.search') }}">Search</a>
        <a class="reader-bottom-link {{ $activeTab === 'library' ? 'is-active' : '' }}" href="{{ route('reader.library') }}">Articles</a>
        <a class="reader-bottom-link {{ $activeTab === 'wallet' ? 'is-active' : '' }}" href="{{ route('reader.wallet') }}">Wallet</a>
        <a class="reader-bottom-link {{ $activeTab === 'profile' ? 'is-active' : '' }}" href="{{ route('reader.profile') }}">Profile</a>
    </nav>
@endsection
