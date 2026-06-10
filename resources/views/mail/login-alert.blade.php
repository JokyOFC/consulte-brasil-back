@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">Olá, <strong>{{ $userName }}</strong>!</p>

    <p style="margin:0 0 20px;">
        Detectamos um novo acesso à sua conta. Se foi você, pode ignorar este e-mail.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0 0 8px;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Detalhes do acesso</p>
                <p style="margin:0 0 4px;font-size:14px;color:#111827;"><strong>Data:</strong> {{ $loggedAt }}</p>
                <p style="margin:0 0 4px;font-size:14px;color:#111827;"><strong>IP:</strong> {{ $ipAddress }}</p>
                <p style="margin:0;font-size:14px;color:#111827;"><strong>Dispositivo:</strong> {{ $userAgent }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;color:#6b7280;font-size:13px;">
        Se não reconhece este acesso, altere sua senha imediatamente.
    </p>

    @include('mail.partials.button', [
        'url' => $passwordUrl,
        'label' => 'Alterar senha',
        'color' => '#002776',
    ])
@endsection
