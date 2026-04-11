@extends('reader.layout')

@section('page_title', 'Wallet')
@section('page_heading', 'Wallet')
@section('page_kicker', 'Coin Balance')
@section('page_subtitle', 'Add money, preview payment orders, and track the recent wallet activity tied to premium article access.')

@section('reader_content')
    <section class="reader-wallet-grid">
        <article class="reader-balance-card">
            <p class="reader-eyebrow">Available Balance</p>
            <h2>{{ number_format($bundle['wallet']['wallet_balance']) }} coins</h2>
            <p>Coins convert at {{ $bundle['wallet']['credits_per_rupee'] }} coin(s) per Rs 1.</p>

            <div class="reader-chip-row">
                <span class="reader-chip">Minimum purchase {{ number_format($bundle['wallet']['min_purchase_credits']) }} coins</span>
                <span class="reader-chip">Approx Rs {{ $minimumTopUpRupees }} minimum</span>
            </div>
        </article>

        <article class="reader-panel">
            <p class="reader-eyebrow">Add Money</p>
            <h2>Create a payment order preview</h2>
            <p class="reader-subtle-text">
                Same as the Flutter app, this first creates a payment order. After payment confirmation, the amount converts into coins and gets added to this wallet.
            </p>

            <form class="reader-form" method="POST" action="{{ route('reader.wallet.orders') }}">
                @csrf

                <label>
                    <span>Credits to add</span>
                    <input
                        type="number"
                        min="{{ $bundle['wallet']['min_purchase_credits'] }}"
                        step="1"
                        name="credits"
                        value="{{ old('credits', $bundle['wallet']['min_purchase_credits']) }}"
                        placeholder="{{ $bundle['wallet']['min_purchase_credits'] }}"
                        required
                    >
                </label>

                <button class="reader-primary-button" type="submit">Create Payment Order</button>
            </form>
        </article>
    </section>

    @if (session('wallet_order'))
        @php($order = session('wallet_order'))
        <section class="reader-order-preview">
            <p class="reader-eyebrow">Latest Order Preview</p>
            <h2>{{ $order['reference'] }}</h2>
            <div class="reader-order-grid">
                <div>
                    <span>Coins</span>
                    <strong>{{ number_format($order['credit_amount']) }} coins</strong>
                </div>
                <div>
                    <span>Amount</span>
                    <strong>{{ $order['amount_label'] }}</strong>
                </div>
                <div>
                    <span>Gateway Order</span>
                    <strong>{{ $order['provider_order_id'] ?: 'Not available' }}</strong>
                </div>
            </div>
        </section>
    @endif

    <section class="reader-section">
        <div class="reader-section-head">
            <div>
                <p class="reader-eyebrow">Recent Transactions</p>
                <h2>Wallet activity</h2>
            </div>
            <span>{{ $bundle['transactions']->count() }} total</span>
        </div>

        @if ($bundle['transactions']->isEmpty())
            <div class="reader-empty-state">
                <h2>No wallet activity found yet.</h2>
                <p>Your purchase orders and unlock deductions will appear here.</p>
            </div>
        @else
            <div class="reader-stack">
                @foreach ($bundle['transactions']->take(8) as $transaction)
                    @include('reader.partials.transaction-card', ['transaction' => $transaction])
                @endforeach
            </div>
        @endif
    </section>
@endsection
