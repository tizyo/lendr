@props(['steps' => []])
{{-- Numbered checklist: each step is ['title' => ..., 'text' => ...]. --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px;">
    @foreach ($steps as $step)
        <tr>
            <td width="40" valign="top" style="padding: 0 0 18px;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" valign="middle" width="28" height="28"
                            style="width: 28px; height: 28px; border-radius: 14px; background: {{ $brand['tint'] }}; color: {{ $brand['accent'] }}; font-size: 13px; font-weight: 700; line-height: 28px;">
                            {{ $loop->iteration }}
                        </td>
                    </tr>
                </table>
            </td>
            <td valign="top" style="padding: 3px 0 18px;">
                <div style="font-size: 14px; font-weight: 600; color: #101828; line-height: 1.4;">{{ $step['title'] }}</div>
                @if (! empty($step['text']))
                    <div style="font-size: 13px; color: #667085; line-height: 1.55; margin-top: 2px;">{{ $step['text'] }}</div>
                @endif
            </td>
        </tr>
    @endforeach
</table>
