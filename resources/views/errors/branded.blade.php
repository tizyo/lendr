{{--
    Fallback for error screens that can't render through Inertia (the Vue "Error" page
    is the normal path; see App\Support\ErrorPage). Self-contained: no Vite assets.
--}}
@php
    $page = \App\Support\ErrorPage::props($status);
    $brand = $page['brand'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $page['title'] }} | {{ $brand['name'] }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        :root { --brand: {{ $brand['color'] }}; --brand-on: {{ $brand['on_color'] }}; --brand-tint: {{ $brand['tint'] }}; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 48px 16px;
               font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; background: #f9fafb; color: #111827; }
        main { width: 100%; max-width: 32rem; text-align: center; }
        .logo { height: 40px; width: auto; margin: 0 auto 40px; display: block; }
        .card { background: #fff; border-radius: 24px; padding: 40px 32px; box-shadow: 0 20px 40px -12px rgb(0 0 0 / .08); border: 1px solid rgb(0 0 0 / .05); }
        .badge { width: 64px; height: 64px; margin: 0 auto 24px; border-radius: 18px; display: flex; align-items: center; justify-content: center;
                 background: var(--brand-tint); color: var(--brand); font-weight: 800; font-size: 20px; }
        .eyebrow { margin: 0; font-size: 13px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); }
        h1 { margin: 8px 0 0; font-size: 30px; line-height: 1.2; letter-spacing: -.02em; }
        p.msg { margin: 12px 0 0; font-size: 16px; line-height: 1.6; color: #4b5563; }
        .actions { margin-top: 32px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 12px;
               font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: 0; font-family: inherit; }
        .btn-primary { background: var(--brand); color: var(--brand-on); }
        .btn-secondary { background: #fff; color: #374151; box-shadow: inset 0 0 0 1px #d1d5db; }
        footer { margin-top: 32px; font-size: 12px; color: #6b7280; }
        @media (prefers-color-scheme: dark) {
            body { background: #0a0a0a; color: #fff; }
            .card { background: #171717; border-color: rgb(255 255 255 / .08); }
            p.msg { color: #a3a3a3; }
            .btn-secondary { background: #262626; color: #e5e5e5; box-shadow: inset 0 0 0 1px #404040; }
        }
    </style>
</head>
<body>
<main>
    @if ($brand['is_tenant'])
        <img class="logo" src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">
    @else
        <picture>
            <source srcset="/brand/lendr-logo-white.png" media="(prefers-color-scheme: dark)">
            <img class="logo" src="/brand/lendr-logo.png" alt="LENDR">
        </picture>
    @endif

    <div class="card">
        <div class="badge">{{ $page['status'] }}</div>
        <p class="eyebrow">Error {{ $page['status'] }}</p>
        <h1>{{ $page['title'] }}</h1>
        <p class="msg">{{ $page['message'] }}</p>
        <div class="actions">
            <button type="button" class="btn btn-secondary" onclick="history.length > 1 ? history.back() : location.assign('{{ $page['homeUrl'] }}')">Go back</button>
            <a class="btn btn-primary" href="{{ $page['homeUrl'] }}">Take me home</a>
        </div>
    </div>

    <footer>{{ $brand['name'] }}@if ($brand['is_tenant']) · Powered by LENDR @endif</footer>
</main>
</body>
</html>
