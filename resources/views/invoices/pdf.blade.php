<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number ?? 'Fatura' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #0f766e; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; font-weight: 600; }
        .total { font-size: 16px; font-weight: 700; }
        .footer { margin-top: 28px; font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $appName }}</h1>
    <div class="muted">Fatura {{ $invoice->number ?? $invoice->id }}</div>

    <div class="box">
        <strong>Status:</strong>
        @php
            $labels = ['open' => 'Pendente', 'overdue' => 'Vencida', 'paid' => 'Paga', 'canceled' => 'Cancelada'];
        @endphp
        {{ $labels[$invoice->status] ?? $invoice->status }}
        <br>
        <strong>Emissão:</strong> {{ optional($invoice->created_at)?->format('d/m/Y') ?? '—' }}
        <br>
        <strong>Vencimento:</strong> {{ optional($invoice->due_date)?->format('d/m/Y') ?? '—' }}
    </div>

    <div class="box">
        <strong>Cliente</strong><br>
        {{ $account?->name ?? $user?->name ?? '—' }}<br>
        {{ $user?->email ?? '' }}
        @if (!empty($account?->document))
            <br>Documento: {{ $account->document }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Descrição</th>
                <th>Qtd</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>R$ {{ number_format($item->amount_cents / 100, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">{{ $invoice->description ?? 'Cobrança' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="box">
        <div class="muted">Período</div>
        {{ optional($invoice->period_start)?->format('d/m/Y') ?? '—' }}
        →
        {{ optional($invoice->period_end)?->format('d/m/Y') ?? '—' }}
        <br><br>
        <div class="total">Total: R$ {{ number_format($invoice->amount_cents / 100, 2, ',', '.') }}</div>
    </div>

    <div class="footer">
        @if ($invoice->status === 'paid' && $invoice->paid_at)
            Pagamento confirmado em {{ $invoice->paid_at->format('d/m/Y H:i') }}.
        @else
            Para pagar, acesse o painel em <strong>Minhas Faturas → Pagar agora</strong> (Financeiro).
            Este PDF não contém link de pagamento.
        @endif
    </div>
</body>
</html>
