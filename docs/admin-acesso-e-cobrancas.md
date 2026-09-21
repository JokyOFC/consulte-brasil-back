# Admin: acesso dos clientes e cobranças PIX

Documentação operacional de duas funções do painel administrativo.

## Acesso dos clientes (2FA e confirmação de email)

Tela: **Admin → Configurações → Acesso dos clientes** (`/admin/settings`). Salvar exige confirmação de senha.

| Configuração (`settings.key`) | Padrão | Desligado significa |
|-------------------------------|--------|---------------------|
| `client_email_verification_enabled` | ligado | Cadastro não envia o email de confirmação e o cliente não verificado acessa o painel normalmente (middleware `verified` libera). |
| `client_two_factor_enabled` | ligado | Nenhum cliente passa pelo desafio de 2FA no login (mesmo quem já ativou) e a gestão de 2FA some de Configurações → Segurança. |

### Comportamento

- Vale **só para clientes**. Administradores continuam sempre com confirmação de email e 2FA.
- Desligar não altera dados: `email_verified_at` e o segredo de 2FA ficam como estão. Religar volta a exigir de quem ainda não confirmou / de quem tinha 2FA ativo.
- Usuário criado pelo admin (Clientes → Novo usuário) já nasce verificado, com a configuração ligada ou não.
- Pontos de extensão: `AppSettings`, `User::emailVerificationRequired()` / `User::twoFactorAvailable()`, `App\Http\Middleware\EnsureEmailIsVerified` (alias `verified`) e `App\Actions\Fortify\RedirectIfTwoFactorRequired` (pipeline de login do Fortify).

## Cobranças PIX pelo admin

Tela: **Admin → Clientes → (cliente)**. O admin gera o PIX e repassa o QR Code / copia e cola ao cliente. O saldo **só entra após o pagamento** (webhook do Mercado Pago ou polling da tela), pelo mesmo caminho do portal do cliente.

### Rotas

| Método | Rota | Função |
|--------|------|--------|
| POST | `/admin/accounts/{id}/charges/plan` | Atribui plano (assinatura manual) + 1ª fatura + PIX. Exige confirmação de senha. |
| POST | `/admin/accounts/{id}/charges/topup` | PIX de recarga avulsa (`amount` em R$). |
| POST | `/admin/accounts/{id}/charges/invoices/{invoiceId}` | Novo PIX para fatura em aberto/vencida (ex.: PIX anterior expirou, renovação). |
| GET | `/admin/accounts/{id}/charges/{paymentId}/status` | Polling: reconsulta no gateway e liquida se aprovado. |
| POST | `/admin/accounts/{id}/subscriptions/{subscriptionId}/cancel` | Cancela a assinatura. Exige confirmação de senha. |
| POST | `/admin/accounts/{id}/assign-plan` | (já existia) Atribuição por **cortesia**: libera o saldo do plano sem cobrar e sem renovação. |

### Comportamento

- **Pagador**: campo "Email do pagador (PIX)"; em branco usa o email do primeiro usuário (cliente) da conta. Conta sem usuário exige informar o email.
- **Uma assinatura por vez**: atribuir plano com PIX é bloqueado se a conta já tem assinatura ativa/em atraso — cancele antes no card "Assinatura". Evita duas faturas de renovação por ciclo.
- **Atômico**: se o gateway recusar o PIX do plano, assinatura e fatura não são criadas.
- Planos arquivados ou com preço zero não podem ser cobrados (para preço zero use cortesia).
- **Reabrir PIX**: enquanto pendente, o QR fica em "Pagamentos recentes" → *Ver PIX*. Ao abrir, a tela reconsulta o gateway; PIX vencido passa a "Cancelado" e é preciso gerar outro.
- **Validade do PIX**: `MP_PIX_EXPIRATION_MINUTES` (padrão 30 min). Se o cliente costuma demorar a pagar o PIX enviado pelo admin, aumente esse valor no `.env`.
