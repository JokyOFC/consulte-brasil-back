@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">Olá, <strong>{{ $userName }}</strong>!</p>

    <p style="margin:0 0 20px;">{{ $message }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0 0 8px;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Resumo</p>
                <p style="margin:0 0 4px;font-size:15px;color:#111827;">
                    <strong>Valor pago:</strong> {{ $amountFormatted }}
                </p>
                <p style="margin:0;font-size:15px;color:#111827;">
                    <strong>Créditos adicionados:</strong> {{ $creditsFormatted }}
                </p>
            </td>
        </tr>
    </table>

    @include('mail.partials.button', [
        'url' => $billingUrl,
        'label' => 'Ver financeiro',
        'color' => '#009c3b',
    ])
@endsection
