@extends('reader.layout')

@section('page_title', 'Search')
@section('page_heading', 'Search')
@section('page_kicker', 'Reader Search')
@section('page_subtitle', 'Search by title, author, category, or story preview and jump straight into the reader flow.')

@section('reader_content')
    <section class="reader-search-card">
        <form class="reader-form reader-form-inline" method="GET" action="{{ route('reader.search') }}">
            <label class="reader-grow">
                <span>Search</span>
                <input type="search" name="q" value="{{ $query }}" placeholder="Search by title, author, category, or story">
            </label>

            <label>
                <span>Category</span>
                <select name="category">
                    <option {{ $selectedCategory === 'All' ? 'selected' : '' }}>All</option>
                    @foreach ($bundle['category_options'] as $category)
                        <option value="{{ $category }}" {{ $selectedCategory === $category ? 'selected' : '' }}>{{ $category }}</option>
                    @endforeach
                </select>
            </label>

            <div class="reader-inline-actions">
                <button class="reader-primary-button" type="submit">Search</button>
                <a class="reader-ghost-button" href="{{ route('reader.search') }}">Reset</a>
            </div>
        </form>
    </section>

    <section class="reader-section">
        <div class="reader-section-head">
            <div>
                <p class="reader-eyebrow">{{ trim($query) === '' ? 'Browse Results' : 'Search Results' }}</p>
                <h2>{{ trim($query) === '' ? 'Explore articles' : 'Matching stories' }}</h2>
            </div>
            <span>{{ $filteredArticles->count() }} found</span>
        </div>

        @if ($filteredArticles->isEmpty())
            <div class="reader-empty-state">
                <h2>No articles matched this search yet.</h2>
                <p>Try another title or category.</p>
            </div>
        @else
            <div class="reader-article-grid">
                @foreach ($filteredArticles as $article)
                    @include('reader.partials.article-card', ['article' => $article])
                @endforeach
            </div>
        @endif
    </section>
@endsection
