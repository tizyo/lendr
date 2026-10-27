@props(['url'])
<p style="margin: 0 0 24px; font-size: 12px; line-height: 1.6; color: #98A2B3;">
    Button not working? Copy and paste this link into your browser:<br>
    <a href="{{ $url }}" target="_blank" style="color: {{ $brand['accent'] }}; word-break: break-all; text-decoration: underline;">{{ $url }}</a>
</p>
