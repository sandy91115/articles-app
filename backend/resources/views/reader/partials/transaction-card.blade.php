<article class="reader-transaction-card">
    <div>
        <p class="reader-transaction-title">{{ $transaction['title'] }}</p>
        <p class="reader-subtle-text">{{ $transaction['created_label'] }} • {{ $transaction['status_label'] }}</p>
    </div>

    <strong class="reader-transaction-amount {{ $transaction['is_credit'] ? 'is-credit' : 'is-debit' }}">
        {{ $transaction['amount_label'] }}
    </strong>
</article>
