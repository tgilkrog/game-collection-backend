@extends('emails.layout')

@section('content')
<p style="margin:0 0 16px; color:#b026ff; font-size:16px; font-weight:bold;">WELCOME, {{ strtoupper($user->name) }}</p>
<p style="margin:0 0 16px;">Your RetroVault account is live. Start cataloguing your physical game collection, browse the shared game database, and follow other collectors.</p>
<p style="margin:0;">If you didn't create this account, you can safely ignore this email.</p>
@endsection
