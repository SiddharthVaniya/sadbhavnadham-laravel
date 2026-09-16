<x-mail::message>
# Verification code

Hello {{ $donorName }},

Use this code to autofill your saved donation details:

**{{ $otp }}**

This code expires in {{ $ttlMinutes }} minutes. If you did not request it, you can ignore this email.

Thanks,<br>
{{ config('branding.name', config('app.name')) }}
</x-mail::message>
