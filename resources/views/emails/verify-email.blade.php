@extends('emails.layout')

@section('content')
<p style="margin:0 0 16px; color:#b026ff; font-size:16px; font-weight:bold;">CONFIRM YOUR EMAIL</p>
<p style="margin:0 0 20px;">Please confirm that {{ $user->email }} belongs to you.</p>
<p style="margin:0 0 20px;">
<a href="{{ $verifyUrl }}" style="display:inline-block; background-color:#5ce1e0; color:#0d0d14; padding:10px 20px; text-decoration:none; font-weight:bold; font-size:13px; letter-spacing:1px;">VERIFY EMAIL</a>
</p>
<p style="margin:0 0 8px; color:#6b6b7d; font-size:12px;">Or paste this link into your browser:</p>
<p style="margin:0 0 20px; word-break:break-all; color:#5ce1e0; font-size:12px;">{{ $verifyUrl }}</p>
<p style="margin:0;">This link expires in 60 minutes. If you didn't create this account, you can safely ignore this email.</p>
@endsection
