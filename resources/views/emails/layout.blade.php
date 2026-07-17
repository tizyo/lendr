<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $branding['company_name'] ?? 'LENDR')</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.08); }

        /* Header */
        .header { background: {{ $branding['primary_color'] ?? '#059669' }}; padding: 28px 40px; text-align: center; }
        .header img  { max-height: 48px; max-width: 180px; object-fit: contain; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto; }
        .header h1   { color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .header .sub { color: rgba(255,255,255,0.82); margin: 5px 0 0; font-size: 12px; }

        /* Body */
        .body { padding: 32px 40px; color: #374151; }
        .body h2 { font-size: 18px; margin: 0 0 12px; color: #111827; }
        .body p  { font-size: 15px; line-height: 1.65; margin: 0 0 16px; }

        /* Highlight box */
        .highlight { background: #F0FDF4; border-left: 4px solid {{ $branding['primary_color'] ?? '#059669' }}; border-radius: 4px; padding: 14px 18px; margin: 20px 0; font-size: 14px; color: #065F46; }
        .highlight.alert { background: #FEF2F2; border-left-color: #DC2626; color: #991B1B; }

        /* CTA button */
        .btn { display: inline-block; background: {{ $branding['primary_color'] ?? '#059669' }}; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: 600; margin: 8px 0 24px; }
        .btn.alert { background: #DC2626; }

        /* Message box (broadcast) */
        .message-box { background: #F0FDF4; border-left: 4px solid {{ $branding['primary_color'] ?? '#059669' }}; border-radius: 4px; padding: 16px 20px; margin: 20px 0; font-size: 15px; color: #1F2937; line-height: 1.7; white-space: pre-line; }

        /* Footer */
        .footer { padding: 20px 40px; background: #F9FAFB; border-top: 1px solid #E5E7EB; font-size: 11px; color: #9CA3AF; text-align: center; line-height: 1.6; }
        .footer a { color: #6B7280; text-decoration: none; }
        .footer .company-details { margin-top: 6px; }
    </style>
    @stack('styles')
</head>
<body>
    <div class="wrapper">

        {{-- Header --}}
        <div class="header">
            @if(!empty($branding['logo_url']))
                <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['company_name'] }}" />
            @else
                <h1>{{ $branding['company_name'] ?? 'LENDR' }}</h1>
            @endif
            @if(!empty($branding['tagline']))
                <p class="sub">{{ $branding['tagline'] }}</p>
            @else
                @yield('header_sub')
            @endif
        </div>

        {{-- Content --}}
        <div class="body">
            @yield('content')
        </div>

        {{-- Footer --}}
        <div class="footer">
            @if(!empty($branding['email_footer']))
                <div>{{ $branding['email_footer'] }}</div>
            @endif
            <div class="company-details">
                &copy; {{ date('Y') }} {{ $branding['company_name'] ?? 'LENDR' }}.
                @if(!empty($branding['address'])) · {{ $branding['address'] }} @endif
                @if(!empty($branding['phone'])) · {{ $branding['phone'] }} @endif
                @if(!empty($branding['email']))
                    · <a href="mailto:{{ $branding['email'] }}">{{ $branding['email'] }}</a>
                @endif
                @if(!empty($branding['website']))
                    · <a href="{{ $branding['website'] }}" target="_blank">{{ $branding['website'] }}</a>
                @endif
            </div>
        </div>

    </div>
</body>
</html>
