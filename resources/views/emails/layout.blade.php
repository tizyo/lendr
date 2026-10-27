<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', $brand['name'])</title>
    {{--
        Email-safe layout: table structure + inline styles (Outlook/Gmail strip most CSS).
        The <style> block only carries progressive enhancements. $brand comes from
        App\Services\Mail\EmailBrand — tenant branding in tenant context, LENDR otherwise.
    --}}
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        .content p { margin: 0 0 16px; }
        .content strong { color: #101828; }
        .content a { color: {{ $brand['accent'] }}; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .card-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .card-top { padding-top: 32px !important; }
            .heading { font-size: 22px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background: #F2F4F7;">
    {{-- Inbox preview text (hidden) --}}
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; mso-hide: all;">
        @yield('preheader')&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;
    </div>

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#F2F4F7" style="background: #F2F4F7;">
        <tr>
            <td align="center" style="padding: 40px 12px;">
                <table role="presentation" class="container" border="0" cellpadding="0" cellspacing="0" width="600"
                       style="width: 600px; max-width: 600px; border-collapse: separate; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, Roboto, Helvetica, Arial, sans-serif;">

                    {{-- Brand mark --}}
                    <tr>
                        <td align="center" style="padding: 0 0 28px;">
                            @if (! empty($brand['logo_url']))
                                <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] }}" height="44"
                                     style="display: block; height: 44px; width: auto; max-width: 220px;">
                            @else
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="center" valign="middle" width="36" height="36" bgcolor="{{ $brand['primary'] }}"
                                            style="width: 36px; height: 36px; border-radius: 10px; background: {{ $brand['primary'] }}; color: {{ $brand['on_primary'] }}; font-size: 18px; font-weight: 800; line-height: 36px;">
                                            {{ $brand['initial'] }}
                                        </td>
                                        <td style="padding-left: 12px; font-size: 20px; font-weight: 700; color: #101828; letter-spacing: -0.02em;">
                                            {{ $brand['name'] }}
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background: #FFFFFF; border-radius: 16px; border: 1px solid #EAECF0; box-shadow: 0 1px 3px rgba(16, 24, 40, 0.06); overflow: hidden;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate;">
                                <tr>
                                    <td height="5" bgcolor="{{ $brand['primary'] }}"
                                        style="height: 5px; line-height: 5px; font-size: 0; background: {{ $brand['primary'] }}; border-radius: 16px 16px 0 0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="card-pad card-top content" style="padding: 44px 48px 20px; font-size: 15px; line-height: 1.65; color: #475467;">
                                        @hasSection('eyebrow')
                                            <div style="font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: {{ $brand['accent'] }}; margin: 0 0 12px;">
                                                @yield('eyebrow')
                                            </div>
                                        @endif
                                        @hasSection('heading')
                                            <h1 class="heading" style="margin: 0 0 20px; font-size: 26px; line-height: 1.25; font-weight: 700; color: #101828; letter-spacing: -0.02em;">
                                                @yield('heading')
                                            </h1>
                                        @endif

                                        @yield('content')
                                    </td>
                                </tr>
                                <tr>
                                    <td class="card-pad" style="padding: 0 48px 40px; font-size: 15px; line-height: 1.6; color: #475467;">
                                        @hasSection('signoff')
                                            @yield('signoff')
                                        @else
                                            Warm regards,<br>
                                            <strong style="color: #101828;">The {{ $brand['name'] }} team</strong>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding: 28px 24px 0; font-size: 12px; line-height: 1.7; color: #98A2B3;">
                            @if (! empty($brand['footer']))
                                <div style="margin-bottom: 8px; color: #667085;">{{ $brand['footer'] }}</div>
                            @endif

                            @php
                                $contact = array_filter([
                                    $brand['phone'] ?? null,
                                    ! empty($brand['email']) ? '<a href="mailto:'.e($brand['email']).'" style="color: #667085; text-decoration: none;">'.e($brand['email']).'</a>' : null,
                                    ! empty($brand['website']) ? '<a href="'.e($brand['website']).'" target="_blank" style="color: #667085; text-decoration: none;">'.e(preg_replace('#^https?://(www\.)?#i', '', rtrim($brand['website'], '/'))).'</a>' : null,
                                ]);
                            @endphp
                            @if (count($contact))
                                <div>{!! implode(' &nbsp;·&nbsp; ', array_map(fn ($c) => str_starts_with($c, '<a ') ? $c : e($c), $contact)) !!}</div>
                            @endif

                            <div>
                                &copy; {{ date('Y') }} {{ $brand['name'] }}@if (! empty($brand['address'])) &nbsp;·&nbsp; {{ $brand['address'] }}@endif
                            </div>

                            @yield('footer_note')

                            @if ($brand['is_tenant'])
                                <div style="margin-top: 14px; font-size: 11px; color: #D0D5DD;">Sent by {{ $brand['name'] }} via LENDR</div>
                            @endif
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
