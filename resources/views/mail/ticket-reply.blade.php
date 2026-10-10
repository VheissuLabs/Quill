<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.5; color: #1f2937;">
    {!! $body !!}
    @if ($author)
        <p style="color: #6b7280;">{{ $author }}<br>{{ config('services.resend.support_name') }}</p>
    @endif
</body>
</html>
