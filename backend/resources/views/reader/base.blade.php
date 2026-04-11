<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mono Reader Web')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class', 'reader-body')">
    @yield('body')

    <div class="reader-toast" id="readerToast" aria-live="polite"></div>
</body>
</html>
