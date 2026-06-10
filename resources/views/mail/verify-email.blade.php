@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">Olá, <strong>{{ $userName }}</strong>!</p>

    <p style="margin:0 0 16px;">
        Clique no botão abaixo para confirmar seu endereço de e-mail e ativar sua conta.
    </p>

    @include('mail.partials.button', [
        'url' => $verificationUrl,
        'label' => 'Confirmar e-mail',
        'color' => '#002776',
    ])

    <p style="margin:0 0 8px;color:#6b7280;font-size:13px;">
        Este link expira em {{ $expireMinutes }} minutos.
    </p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        Se você não criou uma conta, ignore este e-mail.
    </p>
@endsection
