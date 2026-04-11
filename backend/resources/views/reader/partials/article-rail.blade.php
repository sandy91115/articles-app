<article class="reader-rail-card {{ !empty($dark) ? 'is-dark' : '' }}">
    <a class="reader-rail-media" href="{{ $article['reader_url'] }}">
        @if ($article['image_url'])
            <img src="{{ $article['image_url'] }}" alt="{{ $article['title'] }}">
        @else
            <div class="reader-card-fallback">
                <span>{{ $article['title'] }}</span>
            </div>
        @endif
    </a>

    <div class="reader-rail-body">
        <div class="reader-chip-row">
            <span class="reader-chip {{ !empty($dark) ? 'is-dark' : '' }}">{{ $article['category'] }}</span>
            <span class="reader-chip {{ $article['is_unlocked'] ? 'is-success' : '' }} {{ !empty($dark) ? 'is-dark' : '' }}">
                {{ $article['is_unlocked'] ? 'Unlocked' : $article['price_label'] }}
            </span>
        </div>

        <h3><a href="{{ $article['reader_url'] }}">{{ $article['title'] }}</a></h3>
        <p>{{ $article['preview_text'] }}</p>

        <div class="reader-card-footer">
            <span class="reader-card-author">{{ $article['author_name'] }}</span>

            <div class="reader-inline-actions">
                <a class="reader-inline-link {{ !empty($dark) ? 'is-dark' : '' }}" href="{{ $article['reader_url'] }}">Read more</a>
                <button
                    class="reader-inline-link is-button {{ !empty($dark) ? 'is-dark' : '' }}"
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
