@if (session('status'))
    <div class="reader-flash-card is-success">
        <strong>Status</strong>
        <p>{{ session('status') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="reader-flash-card is-error">
        <strong>Please check the form</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
