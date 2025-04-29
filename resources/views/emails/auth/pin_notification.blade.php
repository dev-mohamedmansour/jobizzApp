{{-- resources/views/emails/auth/pin_notification.blade.php --}}
{{--@component('mail::message')--}}
        <!DOCTYPE html>
<html>
<head>
    <title>    @if($type === 'verification')
            Email Verification
        @else
            Password Reset Request
        @endif</title>
</head>
<body>
<h1>Welcome, {{ $name }}!</h1>
<h2>
    @if($type === 'verification')
        Your Verification PIN
    @else
        Your security PIN
    @endif
</h2>
<p>Your pin code is: <strong>{{ $pin }}</strong></p>
<p>@if($type === 'verification')
        Please enter this code in your application to verify your email address.
    @else
        Use this code to complete your password reset.
    @endif</p>
<p>This code will expire in {{ $expiryMinutes}} minutes</p>
{{-- <P> --}}
{{--     @component('mail::button', [ --}}
{{--           'url' => config('app.url'), --}}
{{--           'color' => 'primary' --}}
{{--           ])Visit {{ config('app.name') }} --}}
{{--     @endcomponent --}}
{{-- </P> --}}
Thanks,
{{ config('app.name') }}
</body>
</html>

{{--    @component('mail::button', [--}}
{{--        'url' => config('app.url'),--}}
{{--        'color' => 'primary'--}}
{{--    ])--}}
{{--        Visit {{ config('app.name') }}--}}
{{--    @endcomponent--}}

{{--     If you didn't request this, please ignore this email. --}}

{{--@endcomponent--}}