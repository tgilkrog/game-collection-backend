@extends('emails.layout')

@section('content')
<p style="margin:0 0 16px; color:#b026ff; font-size:16px; font-weight:bold;">PASSWORD CHANGED</p>
<p style="margin:0 0 16px;">The password for {{ $user->email }} was just changed.</p>
<p style="margin:0;">If this wasn't you, contact support immediately &mdash; someone else may have access to your account.</p>
@endsection
