@props(['variant' => 'brand', 'title' => null])
@php
    [$bg, $fg, $titleColor] = match ($variant) {
        'warning' => ['#FFFAEB', '#93370D', '#7A2E0E'],
        'danger' => ['#FEF3F2', '#B42318', '#912018'],
        'success' => ['#ECFDF3', '#067647', '#05603A'],
        'neutral' => ['#F9FAFB', '#344054', '#101828'],
        default => [$brand['tint'], '#344054', '#101828'],
    };
@endphp
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px;">
    <tr>
        <td style="background: {{ $bg }}; border-radius: 12px; padding: 18px 22px; font-size: 14px; line-height: 1.6; color: {{ $fg }};">
            @if ($title)
                <div style="font-weight: 600; color: {{ $titleColor }}; margin-bottom: 4px;">{{ $title }}</div>
            @endif
            {{ $slot }}
        </td>
    </tr>
</table>
