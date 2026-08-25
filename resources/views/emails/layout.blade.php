<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#0d0d14; font-family: 'Courier New', Courier, monospace;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0d0d14; padding:32px 16px;">
<tr>
<td align="center">
<table role="presentation" width="100%" style="max-width:480px; background-color:#16161f; border:1px solid #2a2a38;">
<tr>
<td style="padding:24px 28px; border-bottom:2px solid #b026ff;">
<span style="color:#5ce1e0; font-size:12px; letter-spacing:2px; text-transform:uppercase;">// {{ config('app.name') }}</span>
</td>
</tr>
<tr>
<td style="padding:28px; color:#e4e4ec; font-size:14px; line-height:1.6;">
@yield('content')
</td>
</tr>
<tr>
<td style="padding:16px 28px; border-top:1px solid #2a2a38; color:#6b6b7d; font-size:11px; letter-spacing:1px;">
{{ config('app.name') }} &mdash; automated message, please do not reply.
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
