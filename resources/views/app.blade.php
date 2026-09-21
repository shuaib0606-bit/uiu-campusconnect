<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('img/logo-64.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/logo-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <script>
        (function () {
            var m = document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);
            if (m && m[1] === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/inertia.jsx'])
    <x-inertia::head />
</head>
<body class="min-h-screen">
    <x-inertia::app />
</body>
</html>
