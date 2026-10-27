@props(['href', 'variant' => 'primary', 'align' => 'center'])
@php
    [$bg, $fg, $border] = match ($variant) {
        'danger' => ['#D92D20', '#FFFFFF', '#D92D20'],
        'secondary' => ['#FFFFFF', '#344054', '#D0D5DD'],
        default => [$brand['primary'], $brand['on_primary'], $brand['primary']],
    };
@endphp
{{-- Bulletproof button: the coloured <td> renders even where clients ignore padding on <a>. --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 8px 0 24px;">
    <tr>
        <td align="{{ $align }}">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate;">
                <tr>
                    <td align="center" bgcolor="{{ $bg }}" style="border-radius: 10px; background: {{ $bg }};">
                        <a href="{{ $href }}" target="_blank"
                           style="display: inline-block; padding: 13px 32px; font-size: 15px; font-weight: 600; line-height: 20px; color: {{ $fg }}; text-decoration: none; border-radius: 10px; border: 1px solid {{ $border }}; letter-spacing: -0.01em;">
                            {{ $slot }}
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
