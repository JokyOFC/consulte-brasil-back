@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">Há uma nova mensagem no chamado <strong>{{ $ticketTitle }}</strong>.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
        <tr>
            <td style="padding:16px 20px;font-size:14px;white-space:pre-wrap;">{{ $messageBody }}</td>
        </tr>
    </table>

    @include('mail.partials.button', [
        'url' => $ticketUrl,
        'label' => $ctaLabel,
        'color' => '#009c3b',
    ])
@endsection
