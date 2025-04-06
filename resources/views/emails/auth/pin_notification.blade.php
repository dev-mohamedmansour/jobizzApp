{{-- resources/views/emails/auth/pin_notification.blade.php --}}
@component('mail::message')
    @if($type === 'verification')
        # Email Verification Required
        Your verification code is:
    @else
        # Password Reset Request
        Your security code is:
    @endif

    **{{ $pin }}**

    This code will expire in {{ $expiryMinutes }} minutes.

    @if($type === 'verification')
        Please enter this code in your application to verify your email address.
    @else
        Use this code to complete your password reset.
    @endif

    @component('mail::button', [
        'url' => config('app.url'),
        'color' => 'primary'
    ])
        Visit {{ config('app.name') }}
    @endcomponent

    If you didn't request this, please ignore this email.

    Thanks,<br>
    {{ config('app.name') }}
@endcomponent