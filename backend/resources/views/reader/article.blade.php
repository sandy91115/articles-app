@extends('reader.layout')

@section('page_title', $article['title'])
@section('page_heading', 'Article')
@section('page_kicker', 'Story Detail')
@section('page_subtitle', 'Preview, premium access, unlock action, and article stats for the selected story.')

@section('reader_content')
    <div class="reader-detail-grid">
        <article class="reader-detail-main">
            @if ($article['image_url'])
                <img class="reader-detail-image" src="{{ $article['image_url'] }}" alt="{{ $article['title'] }}">
            @else
                <div class="reader-detail-fallback">{{ $article['title'] }}</div>
            @endif

            <div class="reader-chip-row">
                @foreach ($article['meta_chips'] as $chip)
                    <span class="reader-chip">{{ $chip }}</span>
                @endforeach
            </div>

            <h2 class="reader-detail-title">{{ $article['title'] }}</h2>

            <section class="reader-panel">
                <p class="reader-eyebrow">Preview</p>
                <p class="reader-story-copy">{{ $article['preview_text'] }}</p>
            </section>

            @if ($article['content'])
                <section class="reader-panel">
                    <p class="reader-eyebrow">Full Story</p>
                    <div class="reader-story-copy reader-story-rich">
                        {!! nl2br(e($article['content'])) !!}
                    </div>
                </section>
            @else
                <section class="reader-panel">
                    <p class="reader-eyebrow">Premium Access</p>
                    <h3>Unlock the full article</h3>
                    <p class="reader-subtle-text">
                        The full article is currently locked. Coins will be deducted from the wallet when you unlock it.
                    </p>

                    <form method="POST" action="{{ route('reader.articles.unlock', ['article' => $article['slug']]) }}">
                        @csrf
                        <button class="reader-primary-button" type="submit">Unlock for {{ $article['price_label'] }}</button>
                    </form>
                </section>
            @endif
        </article>

        <aside class="reader-detail-sidebar">
            <article class="reader-panel">
                <p class="reader-eyebrow">Story Stats</p>
                <div class="reader-order-grid">
                    <div>
                        <span>Views</span>
                        <strong>{{ number_format($article['view_count']) }}</strong>
                    </div>
                    <div>
                        <span>Unlocks</span>
                        <strong>{{ number_format($article['unlock_count']) }}</strong>
                    </div>
                    <div>
                        <span>Rating</span>
                        <strong>{{ number_format($article['rating_average'], 1) }} / 5</strong>
                    </div>
                    <div>
                        <span>Ratings</span>
                        <strong>{{ number_format($article['rating_count']) }}</strong>
                    </div>
                    <div>
                        <span>Access</span>
                        <strong>{{ $article['access_expires_label'] }}</strong>
                    </div>
                </div>
            </article>

            <article class="reader-panel">
                <p class="reader-eyebrow">Share</p>
                <h3>Preview this story</h3>
                <p class="reader-subtle-text">Share the public preview page exactly like the Flutter app does.</p>

                <div class="reader-stack">
                    <button
                        class="reader-secondary-button"
                        type="button"
                        data-share-url="{{ $article['share_url'] }}"
                        data-share-title="{{ $article['title'] }}"
                    >
                        Share Story Preview
                    </button>
                    <a class="reader-ghost-button" href="{{ $article['share_url'] }}" target="_blank" rel="noreferrer">Open Preview Page</a>
                </div>
            </article>
        </aside>
    </div>
@endsection
