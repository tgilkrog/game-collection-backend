@extends('emails.layout')

@section('content')
<p style="margin:0 0 16px; color:#b026ff; font-size:16px; font-weight:bold;">PASSWORD RESET REQUESTED</p>
<p style="margin:0 0 20px;">We received a request to reset the password for the account associated with {{ $user->email }}.</p>
<p style="margin:0 0 20px;">
<a href="{{ $resetUrl }}" style="display:inline-block; background-color:#5ce1e0; color:#0d0d14; padding:10px 20px; text-decoration:none; font-weight:bold; font-size:13px; letter-spacing:1px;">RESET PASSWORD</a>
</p>
<p style="margin:0 0 8px; color:#6b6b7d; font-size:12px;">Or paste this link into your browser:</p>
<p style="margin:0 0 20px; word-break:break-all; color:#5ce1e0; font-size:12px;">{{ $resetUrl }}</p>
<p style="margin:0;">This link expires in 60 minutes. If you didn't request a password reset, no action is needed &mdash; your password will not change.</p>
@endsection
