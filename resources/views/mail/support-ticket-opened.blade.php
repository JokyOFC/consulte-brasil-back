@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">Novo ticket aberto por <strong>{{ $clientName }}</strong> ({{ $clientEmail }}).</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0 0 8px;font-size:12px;color:#6b7280;text-transform:uppercase;">Categoria</p>
                <p style="margin:0 0 12px;font-size:15px;">{{ $category }}</p>
                <p style="margin:0 0 8px;font-size:12px;color:#6b7280;text-transform:uppercase;">Assunto</p>
                <p style="margin:0 0 12px;font-size:15px;"><strong>{{ $ticketTitle }}</strong></p>
                <p style="margin:0 0 8px;font-size:12px;color:#6b7280;text-transform:uppercase;">Descrição</p>
                <p style="margin:0;font-size:14px;white-space:pre-wrap;">{{ $ticketBody }}</p>
            </td>
        </tr>
    </table>

    @include('mail.partials.button', [
        'url' => $ticketUrl,
        'label' => 'Abrir no painel',
        'color' => '#009c3b',
    ])
@endsection
