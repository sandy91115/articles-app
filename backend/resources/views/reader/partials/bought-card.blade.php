<article class="reader-bought-card">
    <a class="reader-bought-media" href="{{ $item['article']['reader_url'] }}">
        @if ($item['article']['image_url'])
            <img src="{{ $item['article']['image_url'] }}" alt="{{ $item['article']['title'] }}">
        @else
            <div class="reader-card-fallback">
                <span>{{ $item['article']['title'] }}</span>
            </div>
        @endif
    </a>

    <div class="reader-bought-body">
        <div class="reader-chip-row">
            <span class="reader-chip">{{ $item['article']['category'] }}</span>
            <span class="reader-chip {{ $item['is_active'] ? 'is-success' : 'is-muted' }}">
                {{ $item['is_active'] ? 'Active' : 'Expired' }}
            </span>
        </div>

        <h3><a href="{{ $item['article']['reader_url'] }}">{{ $item['article']['title'] }}</a></h3>
        <p>{{ $item['article']['author_name'] }}</p>
        <p class="reader-subtle-text">
            Bought with {{ $item['credits_spent_label'] }} • {{ $item['status_label'] }}
        </p>

        <div class="reader-card-footer">
            <a class="reader-inline-link" href="{{ $item['article']['reader_url'] }}">Read more</a>

            <button
                class="reader-inline-link is-button"
                type="button"
                data-share-url="{{ $item['article']['share_url'] }}"
                data-share-title="{{ $item['article']['title'] }}"
            >
                Share
            </button>
        </div>
    </div>
</article>
