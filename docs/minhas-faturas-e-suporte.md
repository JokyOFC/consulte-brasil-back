# Minhas Faturas e Suporte / Chamados

Documentação operacional dos módulos implementados no Consulte Brasil (React/Inertia + Laravel).

## Minhas Faturas

### Rotas (cliente)

| Método | Rota | Função |
|--------|------|--------|
| GET | `/client/invoices` | Lista (vencimentos, planos, histórico) |
| GET | `/client/invoices/{id}` | Detalhe |
| GET | `/client/invoices/{id}/pdf` | Download PDF |
| POST | `/client/billing/invoices/pay` | Pagar (fluxo existente no Financeiro) |

### Comportamento

- Status: `open` (Pendente), `overdue`, `paid`, `canceled`
- Número humano: `FAT-{ano}-{sequencial 6 dígitos}`
- Renovação: assinaturas manuais ativas recebem fatura `open` com `metadata.origin=renewal` (comando diário + ao abrir a tela)
- Pagamento: PIX/boleto/cartão via API transparente (não Checkout Pro)

### Comandos

```bash
php artisan billing:run-recurring              # overdue + faturas de ciclo + ensure renewals
php artisan billing:backfill-invoice-numbers  # atribui número às faturas antigas
```

## Suporte / Chamados

### Rotas

**Cliente**

- `GET/POST /client/tickets`
- `GET /client/tickets/create`
- `GET /client/tickets/{id}`
- `POST /client/tickets/{id}/reply`

**Admin**

- `GET /admin/tickets`
- `GET /admin/tickets/{id}`
- `PATCH /admin/tickets/{id}` (status)
- `POST /admin/tickets/{id}/reply`

**Anexos**

- `GET /support-tickets/{ticket}/attachments/{attachment}/download`

### Regras

- Categorias: técnico, financeiro, dúvidas
- Status: aberto → em andamento (1ª resposta da equipe) → encerrado
- Cliente respondendo ticket encerrado **reabre** como aberto
- Anexos: até 5, 10 MB, JPG/PNG/PDF, disco privado
- Badge de não lidos no menu (cliente e admin)

### Env

```env
SUPPORT_NOTIFY_EMAIL=suporte@consultebrasil.com.br
```

### Dependências

- PDF de fatura: `barryvdh/laravel-dompdf`
- E-mails enfileirados (`ShouldQueue`) — manter `queue:work` ativo
