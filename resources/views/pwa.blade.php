@php($pwa = \App\Services\PwaBrand::current())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $pwa['theme_color'] }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $pwa['short_name'] }}">

    <title inertia>{{ $pwa['name'] }}</title>

    <link rel="manifest" href="{{ route('pwa.manifest', [], false) }}">
    <link rel="apple-touch-icon" href="{{ route('pwa.icon', ['size' => 180, 'purpose' => 'any', 'v' => $pwa['version']], false) }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ route('pwa.icon', ['size' => 192, 'purpose' => 'any', 'v' => $pwa['version']], false) }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @routes(nonce: \Illuminate\Support\Facades\Vite::cspNonce())
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased bg-white overscroll-none">
    @inertia

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/app/sw.js', { scope: '/app/' })
                    .catch(err => console.warn('SW registration failed:', err))
            })
        }
    </script>
</body>
</html>
