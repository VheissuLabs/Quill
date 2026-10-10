{!! $body !!}

@if ($author)
{{ $author }}
{{ config('services.resend.support_name') }}
@endif
