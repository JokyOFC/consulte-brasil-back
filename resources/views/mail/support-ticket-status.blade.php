@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">O status do chamado <strong>{{ $ticketTitle }}</strong> foi atualizado.</p>

    <p style="margin:0 0 20px;">
        <strong>{{ $previousStatus }}</strong> → <strong>{{ $currentStatus }}</strong>
    </p>

    @include('mail.partials.button', [
        'url' => $ticketUrl,
        'label' => 'Ver chamado',
        'color' => '#009c3b',
    ])
@endsection
