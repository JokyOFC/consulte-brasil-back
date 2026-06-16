@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">Olá, <strong>{{ $userName }}</strong>!</p>

    <p style="margin:0 0 16px;">
        Sua conta no Consulte Brasil foi criada com sucesso. A partir de agora você pode
        integrar consultas de CPF, CNPJ e mais ao seu sistema com saldo prepago transparente.
    </p>

    <p style="margin:0 0 16px;">
        Antes de começar, confirme seu endereço de e-mail clicando no link que enviamos em seguida.
    </p>

    @include('mail.partials.button', [
        'url' => $dashboardUrl,
        'label' => 'Acessar o painel',
        'color' => '#009c3b',
    ])

    <p style="margin:0;color:#6b7280;font-size:13px;">
        Se o botão não funcionar, acesse: <a href="{{ $dashboardUrl }}" style="color:#002776;">{{ $dashboardUrl }}</a>
    </p>
@endsection
