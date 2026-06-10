<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f8;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,39,118,0.08);">
                    @include('mail.partials.header')

                    <tr>
                        <td style="padding:32px 40px 24px;color:#1a1a1a;font-size:15px;line-height:1.6;">
                            @if (!empty($preview))
                                <p style="margin:0 0 20px;color:#6b7280;font-size:13px;">{{ $preview }}</p>
                            @endif

                            @if (!empty($title))
                                <h1 style="margin:0 0 16px;font-size:22px;font-weight:600;color:#111827;line-height:1.3;">{{ $title }}</h1>
                            @endif

                            @yield('content')
                        </td>
                    </tr>

                    @include('mail.partials.footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
