@extends('reader.layout')

@section('page_title', 'Your Articles')
@section('page_heading', 'Your Articles')
@section('page_kicker', 'Bought Library')
@section('page_subtitle', 'Every premium article this reader has already unlocked, including active and expired access records.')

@section('reader_content')
    <section class="reader-summary-card">
        <div class="reader-stat-pill">
            <span>Bought</span>
            <strong>{{ $boughtItems->count() }}</strong>
        </div>
        <div class="reader-stat-pill">
            <span>Active</span>
            <strong>{{ $boughtItems->where('is_active', true)->count() }}</strong>
        </div>
        <div class="reader-stat-pill">
            <span>Wallet</span>
            <strong>{{ number_format($bundle['wallet']['wallet_balance']) }} coins</strong>
        </div>
    </section>

    <section class="reader-section">
        <div class="reader-section-head">
            <div>
                <p class="reader-eyebrow">Bought Articles</p>
                <h2>Your unlocked reading shelf</h2>
            </div>
            <span>{{ $boughtItems->count() }} total</span>
        </div>

        @if ($boughtItems->isEmpty())
            <div class="reader-empty-state">
                <h2>No premium articles bought yet.</h2>
                <p>Unlock a story from the home feed and it will appear here.</p>
            </div>
        @else
            <div class="reader-stack">
                @foreach ($boughtItems as $item)
                    @include('reader.partials.bought-card', ['item' => $item])
                @endforeach
            </div>
        @endif
    </section>
@endsection
