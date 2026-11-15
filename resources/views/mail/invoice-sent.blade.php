<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.5; color: #1f2937;">
    <p>{{ __('Invoice :number from :organization', ['number' => $number, 'organization' => $organization]) }}</p>
    <p>
        {{ __('Total') }}: {{ $total }}<br>
        {{ __('Due date') }}: {{ $dueDate }}<br>
        {{ __('Amount due') }}: {{ $amountDue }}
    </p>
    <p><a href="{{ $url }}" style="display: inline-block; padding: 10px 18px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 6px;">{{ __('View invoice') }}</a></p>
</body>
</html>
