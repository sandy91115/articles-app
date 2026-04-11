<article class="reader-compact-card">
    <a class="reader-card-link" href="{{ $article['reader_url'] }}">
        @if ($article['image_url'])
            <img class="reader-compact-image" src="{{ $article['image_url'] }}" alt="{{ $article['title'] }}">
        @else
            <div class="reader-compact-fallback">
                <span>{{ $article['title'] }}</span>
            </div>
        @endif
    </a>

    <div class="reader-card-body">
        <div class="reader-chip-row">
            <span class="reader-chip">{{ $article['category'] }}</span>
            <span class="reader-chip {{ $article['is_unlocked'] ? 'is-success' : '' }}">
                {{ $article['is_unlocked'] ? 'Unlocked' : $article['price_label'] }}
            </span>
        </div>

        <h3><a href="{{ $article['reader_url'] }}">{{ $article['title'] }}</a></h3>
        <p>{{ $article['preview_text'] }}</p>

        <div class="reader-card-footer">
            <span class="reader-card-author">{{ $article['author_name'] }}</span>

            <div class="reader-inline-actions">
                <a class="reader-inline-link" href="{{ $article['reader_url'] }}">Open</a>
                <button
                    class="reader-inline-link is-button"
                    type="button"
                    data-share-url="{{ $article['share_url'] }}"
                    data-share-title="{{ $article['title'] }}"
                >
                    Share
                </button>
            </div>
        </div>
    </div>
</article>
