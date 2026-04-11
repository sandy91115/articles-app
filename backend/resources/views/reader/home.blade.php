@extends('reader.layout')

@section('page_title', 'Reader Home')
@section('page_heading', 'Home')
@section('page_kicker', 'Reader Experience')
@section('page_subtitle', 'Top articles, quick picks, category sections, and the same premium reading flow now available on the web.')
@section('page_badges')
    <div class="reader-page-badges">
        <span class="reader-page-badge">{{ $bundle['stats']['story_count'] }} stories live</span>
        <span class="reader-page-badge">{{ $bundle['stats']['premium_count'] }} premium ready</span>
        <span class="reader-page-badge">{{ number_format($bundle['wallet']['wallet_balance']) }} coins in wallet</span>
    </div>
@endsection

@section('page_actions')
    <div class="reader-page-actions">
        <a class="reader-secondary-button" href="{{ route('reader.search') }}">Explore search</a>
        <a class="reader-primary-button" href="{{ route('reader.library') }}">Continue reading</a>
    </div>
@endsection

@section('reader_content')
    <section class="reader-home-top">
        <form class="reader-search-card reader-search-form" method="GET" action="{{ route('reader.home') }}">
            <div class="reader-search-intro">
                <div>
                    <p class="reader-eyebrow">Content Desk</p>
                    <h2>Search and organize your reading feed</h2>
                    <p class="reader-subtle-text">Use one clean control panel to search, switch categories, and narrow by author without losing context.</p>
                </div>

                <div class="reader-search-stats">
                    <div class="reader-mini-stat">
                        <span>Visible now</span>
                        <strong>{{ $filteredArticles->count() }}</strong>
                    </div>
                    <div class="reader-mini-stat">
                        <span>Sections</span>
                        <strong>{{ $bundle['stats']['category_count'] }}</strong>
                    </div>
                    <div class="reader-mini-stat">
                        <span>Premium</span>
                        <strong>{{ $bundle['stats']['premium_count'] }}</strong>
                    </div>
                </div>
            </div>

            <div class="reader-search-row">
                <label class="reader-search-field">
                    <span>Search the feed</span>
                    <input type="search" name="q" value="{{ $query }}" placeholder="Search articles, authors, or categories">
                </label>
                <button class="reader-primary-button is-small" type="submit">Search</button>
            </div>

            @php
                $allCategories = collect(['All'])->concat($bundle['category_options']);
            @endphp

            <div class="reader-filter-block">
                <div class="reader-filter-meta">
                    <div>
                        <p class="reader-eyebrow">Browse by category</p>
                        <strong>Jump into a section fast</strong>
                    </div>
                    <span>{{ $allCategories->count() }} categories</span>
                </div>

                <div class="reader-filter-row">
                    @foreach ($allCategories as $category)
                        <a
                            class="reader-filter-chip {{ $selectedCategory === $category ? 'is-active' : '' }}"
                            href="{{ route('reader.home', ['q' => $query ?: null, 'category' => $category === 'All' ? null : $category, 'author' => $selectedAuthor !== 'All authors' ? $selectedAuthor : null]) }}"
                        >
                            {{ $category }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="reader-search-controls">
                <label class="reader-author-field">
                    <span>Author filter</span>
                    <select name="author">
                        <option {{ $selectedAuthor === 'All authors' ? 'selected' : '' }}>All authors</option>
                        @foreach ($bundle['author_options'] as $author)
                            <option value="{{ $author }}" {{ $selectedAuthor === $author ? 'selected' : '' }}>{{ $author }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="reader-search-actions">
                    <button class="reader-secondary-button" type="submit">Apply filters</button>
                    <a class="reader-ghost-button" href="{{ route('reader.home') }}">Reset</a>
                </div>
            </div>
        </form>

        <div class="reader-summary-card">
            <div class="reader-summary-head">
                <p class="reader-eyebrow">Daily Snapshot</p>
                <h2>Reader stats</h2>
                <p class="reader-subtle-text">Quick numbers from your live feed, premium mix, and current wallet state.</p>
            </div>

            <div class="reader-summary-grid">
                <div class="reader-stat-pill">
                    <span>Stories</span>
                    <strong>{{ $bundle['stats']['story_count'] }}</strong>
                    <small>Available in the feed</small>
                </div>
                <div class="reader-stat-pill">
                    <span>Premium</span>
                    <strong>{{ $bundle['stats']['premium_count'] }}</strong>
                    <small>Ready to unlock</small>
                </div>
                <div class="reader-stat-pill">
                    <span>Categories</span>
                    <strong>{{ $bundle['stats']['category_count'] }}</strong>
                    <small>Sections to browse</small>
                </div>
                <div class="reader-stat-pill">
                    <span>Wallet</span>
                    <strong>{{ number_format($bundle['wallet']['wallet_balance']) }} coins</strong>
                    <small>Current balance</small>
                </div>
            </div>

            <div class="reader-summary-footer">
                <a class="reader-inline-link" href="{{ route('reader.wallet') }}">Open wallet</a>
                <a class="reader-inline-link" href="{{ route('reader.profile') }}">View profile</a>
            </div>
        </div>
    </section>

    @if ($showRechargePrompt)
        <section class="reader-highlight-banner">
            <div>
                <p class="reader-eyebrow">Wallet Prompt</p>
                <h2>Recharge wallet to unlock full articles</h2>
                <p>Your current balance is low for premium stories. Add coins now so you can open full articles without interruption.</p>
            </div>

            <a class="reader-primary-button" href="{{ route('reader.wallet') }}">Recharge Wallet</a>
        </section>
    @endif

    @if ($filteredArticles->isEmpty())
        <section class="reader-empty-state">
            <h2>No stories match your current search yet.</h2>
            <p>Try another title, author, or category filter.</p>
        </section>
    @else
        <section class="reader-section">
            @php
                $railArticles = $highlightedArticles->take(2)->values();
                $trendingArticles = $topArticles
                    ->reject(fn ($article) => $leadArticle && $article['id'] === $leadArticle['id'])
                    ->slice(2, 3)
                    ->values();
            @endphp

            <div class="reader-section-head">
                <div>
                    <p class="reader-eyebrow">Top Articles</p>
                    <h2>Ranked for readers</h2>
                    <p class="reader-section-note">A featured story first, followed by fast supporting reads.</p>
                </div>
                <span>{{ $topArticles->count() }} ranked</span>
            </div>

            @if ($leadArticle)
                <div class="reader-hero-grid">
                    <article class="reader-hero-story">
                        <a class="reader-hero-media" href="{{ $leadArticle['reader_url'] }}">
                            @if ($leadArticle['image_url'])
                                <img src="{{ $leadArticle['image_url'] }}" alt="{{ $leadArticle['title'] }}">
                            @else
                                <div class="reader-hero-fallback">{{ $leadArticle['title'] }}</div>
                            @endif
                        </a>

                        <div class="reader-hero-body">
                            <div class="reader-chip-row">
                                <span class="reader-chip is-dark">{{ $leadArticle['category'] }}</span>
                                <span class="reader-chip is-dark {{ $leadArticle['is_unlocked'] ? 'is-success' : '' }}">
                                    {{ $leadArticle['is_unlocked'] ? 'Unlocked' : $leadArticle['price_label'] }}
                                </span>
                            </div>

                            <h3><a href="{{ $leadArticle['reader_url'] }}">{{ $leadArticle['title'] }}</a></h3>
                            <p>{{ $leadArticle['preview_text'] }}</p>

                            <div class="reader-hero-meta">
                                <span>{{ $leadArticle['author_name'] }}</span>
                                <span>{{ $leadArticle['view_count'] }} views</span>
                                <span>{{ $leadArticle['unlock_count'] }} unlocks</span>
                            </div>

                            <div class="reader-inline-actions">
                                <a class="reader-inline-link is-dark" href="{{ $leadArticle['reader_url'] }}">Read more</a>
                                <button
                                    class="reader-inline-link is-button is-dark"
                                    type="button"
                                    data-share-url="{{ $leadArticle['share_url'] }}"
                                    data-share-title="{{ $leadArticle['title'] }}"
                                >
                                    Share
                                </button>
                            </div>
                        </div>
                    </article>

                    <div class="reader-hero-rail">
                        @foreach ($railArticles as $article)
                            @include('reader.partials.article-rail', ['article' => $article, 'dark' => true])
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($trendingArticles->isNotEmpty())
                <div class="reader-top-grid">
                    @foreach ($trendingArticles as $article)
                        @include('reader.partials.article-rail', ['article' => $article, 'dark' => false])
                    @endforeach
                </div>
            @endif
        </section>

        @if ($quickPicks->isNotEmpty())
            <section class="reader-section">
                <div class="reader-section-head">
                    <div>
                        <p class="reader-eyebrow">Quick Picks</p>
                        <h2>Fast reads</h2>
                        <p class="reader-section-note">Shorter stories for quick reading sessions.</p>
                    </div>
                    <span>{{ $quickPicks->count() }} stories</span>
                </div>

                <div class="reader-horizontal-list">
                    @foreach ($quickPicks as $article)
                        @include('reader.partials.article-card-compact', ['article' => $article])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="reader-section">
            <div class="reader-section-head">
                <div>
                    <p class="reader-eyebrow">Latest Articles</p>
                    <h2>Fresh on the reader feed</h2>
                    <p class="reader-section-note">Newest additions published into the web reader.</p>
                </div>
                <span>{{ $latestArticles->count() }} fresh</span>
            </div>

            <div class="reader-article-grid">
                @foreach ($latestArticles as $article)
                    @include('reader.partials.article-card', ['article' => $article])
                @endforeach
            </div>
        </section>

        @foreach ($categoryGroups as $group)
            <section class="reader-section">
                <div class="reader-section-head">
                    <div>
                        <p class="reader-eyebrow">{{ $group['category'] }}</p>
                        <h2>{{ $group['category'] }} Articles</h2>
                    </div>
                    <span>{{ $group['articles']->count() }} stories</span>
                </div>

                <div class="reader-horizontal-list">
                    @foreach ($group['articles'] as $article)
                        @include('reader.partials.article-card-compact', ['article' => $article])
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="reader-section">
            <div class="reader-section-head">
                <div>
                    <p class="reader-eyebrow">All Articles</p>
                    <h2>Everything matching your filters</h2>
                </div>
                <span>{{ $filteredArticles->count() }} total</span>
            </div>

            <div class="reader-article-grid">
                @foreach ($filteredArticles as $article)
                    @include('reader.partials.article-card', ['article' => $article])
                @endforeach
            </div>
        </section>
    @endif
@endsection
