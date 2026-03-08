<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#10B981">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="LENDR">

    <title inertia>{{ config('app.name', 'LENDR') }}</title>

    <link rel="manifest" href="/pwa/manifest.json">
    <link rel="apple-touch-icon" href="/pwa/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @routes
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased bg-white overscroll-none">
    @inertia

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/pwa/sw.js')
                    .catch(err => console.warn('SW registration failed:', err))
            })
        }
    </script>
</body>
</html>
