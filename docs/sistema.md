# Consulte Brasil — Documentação completa do sistema

Este documento descreve o que a plataforma faz, todos os endpoints HTTP (API pública e painel web) e os processos de fundo. Valores de preço no catálogo de consultas são **custo do provedor**; o preço cobrado do cliente é esse custo **mais 10% de margem**, e pode ser alterado no painel admin. Sempre use `GET /api/v1/services` ou o campo `amount_charged` da resposta para o valor vigente.

Documentação interativa (OpenAPI / Scalar): **`/docs/api`** (login no painel). JSON: **`/docs/api.json`**.

Complementos:

- [Webhook de consultas](./webhook.md)
- [Integração de antecedentes criminais (`cpf_cac`)](./antecedentes-criminais.md)
- [Faturas e suporte](./minhas-faturas-e-suporte.md)

---

## 1. O que o sistema faz

O **Consulte Brasil** é uma plataforma B2B de consulta a dados oficiais e comerciais do Brasil (CPF, CNPJ, crédito, veículos, localização, etc.).

| Capacidade | Descrição |
|------------|-----------|
| Consultas | Um endpoint dinâmico `POST /api/v1/consult/{queryType}` cobre dezenas de tipos. O mesmo fluxo roda no painel web. |
| Créditos | Carteira por conta (centavos de BRL). Reserva → commit em sucesso → estorno se o provedor falhar. |
| API keys | Token opaco `cb_live_…` (Bearer). Gerado no painel ou via Artisan. |
| Planos | Assinatura com saldo incluso, faturas e recarga avulsa (PIX / boleto / cartão via Mercado Pago). |
| Webhook de saída | Notificação opcional `consultation.completed` (HMAC) após cada consulta. |
| Painel cliente | Dashboard, consultas, chaves, webhook, financeiro, faturas, tickets, logs. |
| Painel admin | Contas, planos, provedores, tipos de consulta, financeiro, tickets, logs, timeout de sessão. |
| Auditoria | Toda request da API pública (e consultas do painel) vai para `request_logs`. |
| Provedores | CPF.CNPJ e API Brasil, com failover e preço por *capability*. |

**Garantia de cobrança:** o cliente **não é cobrado** se o documento for inválido, o saldo for insuficiente, o tipo não existir ou **todos os provedores falharem**. Só há débito definitivo em sucesso (incluindo acerto de cache).

### 1.1 Stack e módulos

- PHP 8.3, Laravel 13, Inertia.js + React, Vite, Tailwind.
- Domínio em `src/Modules/`: **Identity**, **Billing**, **Consultation**, **Provider**, **Audit**, **Support**.

### 1.2 Fluxo de uma consulta

```
HTTP (API key ou sessão web)
  → validação de params (CPF/CNPJ com DV quando aplicável)
  → reserva atômica de saldo
  → cache (fingerprint tipo + params)
  → roteamento de provedor (failover)
  → sucesso: commit + persistência + webhook opcional
  → falha total: refund + persistência (status refunded) + webhook opcional
```

Cache padrão: **24 h** (`CONSULTATION_CACHE_TTL_SECONDS`). `cpf` e `cnpj` básicos: **7 dias**. Acerto de cache **ainda cobra**. PDFs de certidão podem ser enriquecidos localmente (parse + OCR opcional com Ghostscript/Tesseract).

### 1.3 Base URL

- API: `{APP_URL}/api/v1`
- Painel: `{APP_URL}` (Inertia)
- Health Laravel: `GET /up`

---

## 2. Autenticação

### 2.1 API pública

Header:

```http
Authorization: Bearer cb_live_xxxxxxxx
```

Não há JWT/OAuth. A chave é validada por prefixo + hash SHA-256 (expiração, status, conta ativa). Falha → **401** genérico.

### 2.2 Painel web (sessão)

Laravel Fortify: registro, login, logout, reset de senha, verificação de e-mail, 2FA, passkeys (WebAuthn). Após login bem-sucedido, a conta recebe alerta de login por e-mail quando o contexto muda.

Registro cria:

1. Conta (tenant) com CPF/CNPJ
2. Usuário com `role=client`
3. Carteira de créditos (evento `AccountRegistered`)

Admin acessa `/admin` (`role=admin`). Cliente autenticado em `/` vai para `/dashboard`.

---

## 3. Envelope de erros (API)

```json
{
  "error": {
    "type": "insufficient_credits",
    "message": "Mensagem amigável."
  }
}
```

| HTTP | `error.type` | Quando |
|------|--------------|--------|
| 401 | — | API key inválida / assinatura MP inválida |
| 402 | `insufficient_credits` | Saldo insuficiente |
| 404 | `unknown_query_type` / `service_not_found` / `plan_not_found` / `wallet_not_found` | Recurso inexistente |
| 422 | `invalid_document` / `domain_error` / validação Laravel | Params inválidos |
| 429 | — | Rate limit |
| 503 | `no_provider_available` / `all_providers_failed` | Sem provedor ou falha total (estorno) |

Rate limits típicos: **60/min** na API de consulta/catálogo; **30/min** consulta no painel; **10/min** recarga/assinatura; **120/min** webhook Mercado Pago; login Fortify limitado.

---

## 4. API pública (`/api/v1`)

Prefixo: `/api/v1`. Middleware: headers de segurança + auditoria (`LogRequest`).

### 4.1 Sem autenticação

| Método | Rota | Throttle | Descrição |
|--------|------|----------|-----------|
| GET | `/api/v1/ping` | — | Health: `{ status, service, version }` |
| GET | `/api/v1/services` | 60/min | Lista tipos ativos com capability habilitada (exclui SMS/chip/URA/IA do catálogo público) |
| GET | `/api/v1/services/{code}` | 60/min | Detalhe + preço. `code`: `[a-z][a-z0-9_]{1,49}` |
| GET | `/api/v1/plans` | 60/min | Planos ativos |
| GET | `/api/v1/plans/{slug}` | 60/min | Plano por slug |
| POST | `/api/v1/webhooks/mercadopago` | 120/min | Webhook **de entrada** do Mercado Pago (HMAC `x-signature`) |

**GET `/api/v1/ping`**

```json
{ "status": "ok", "service": "consulte-brasil", "version": "v1" }
```

**GET `/api/v1/services`**

```json
{
  "data": [
    {
      "code": "cpf",
      "name": "…",
      "description": "…",
      "group": "Pessoa Física (CPF)",
      "price_cents": 32,
      "price_formatted": "R$ 0,32"
    }
  ],
  "groups": [{ "name": "Pessoa Física (CPF)", "services": [] }]
}
```

Grupos: Pessoa Física (CPF), Pessoa Jurídica (CNPJ), Veículos, Tabela FIPE, Localização e utilidades, Feriados, Outros.

**GET `/api/v1/plans`** — campos: `id`, `name`, `slug`, `currency`, `price_cents`, `price_formatted`, `billing_period`, `included_balance_cents`, `overage_price_*`, `features`.

**POST `/api/v1/webhooks/mercadopago`** — uso interno do gateway. Valida `x-signature` / `x-request-id` (janela 5 min). Sem secret em não-produção, a checagem é relaxada. Resposta `{ "received": true }` ou 401.

### 4.2 Com API key

| Método | Rota | Throttle | Descrição |
|--------|------|----------|-----------|
| GET | `/api/v1/me` | — | Conta autenticada |
| GET | `/api/v1/credits` | 60/min | Saldo da carteira |
| POST | `/api/v1/consult/{queryType}` | 60/min | Executa consulta |
| GET | `/api/v1/webhook` | — | URL do webhook de saída |
| PUT | `/api/v1/webhook` | — | Cria / atualiza / remove / regenera secret |

**GET `/api/v1/me`**

```json
{
  "data": {
    "id": "uuid",
    "name": "Minha Empresa LTDA",
    "document": "12345678000199",
    "status": "active",
    "authenticated_via_api_key": "uuid-da-chave"
  }
}
```

**GET `/api/v1/credits`**

```json
{
  "data": {
    "account_id": "uuid",
    "currency": "BRL",
    "balance": 100000,
    "reserved": 500,
    "available": 99500,
    "balance_formatted": "R$ 1.000,00",
    "reserved_formatted": "R$ 5,00",
    "available_formatted": "R$ 995,00"
  }
}
```

Valores em **centavos**. `available` = saldo − reservado.

**POST `/api/v1/consult/{queryType}`**

```http
Content-Type: application/json

{ "params": { "document": "11144477735" } }
```

- `params` é obrigatório (objeto).
- Maioria dos tipos: `params.document` (máscara aceita; normalizado para alfanumérico maiúsculo).
- `cnpj_razao`: `params.razao_social`.
- FIPE, feriados, clima, distância etc.: parâmetros extras conforme o tipo (o provedor valida).

Resposta 200:

```json
{
  "data": {
    "consultation_id": "uuid",
    "provider": "cpfcnpj",
    "amount_charged": 32,
    "amount_charged_formatted": "R$ 0,32",
    "credits_charged": 32,
    "from_cache": false,
    "data": { }
  }
}
```

O payload bruto do provedor (sem metadados de billing) fica em `data.data.raw` quando o adaptador o inclui.

**GET/PUT `/api/v1/webhook`** — ver [webhook.md](./webhook.md). Secret só aparece na criação ou com `regenerate_secret: true`. Produção exige HTTPS.

---

## 5. Catálogo de tipos de consulta

Endpoint único: `POST /api/v1/consult/{queryType}` e, no painel, `POST /client/consultations/{queryType}`.

Tipos com prefixo `ab_sms`, `ab_chip_`, `ab_ura_`, `ab_ia_` existem no banco (API Brasil) mas **não** aparecem em `GET /api/v1/services` nem no painel do cliente.

Códigos `ab_cpf_limite` e `ab_cpf_limite_positivo` são consultas **por CNPJ** (nome histórico do code).

### 5.1 CPF.CNPJ (provedor `cpfcnpj`)

Custos abaixo = preço do provedor em BRL (venda ≈ +10%).

| `queryType` | Descrição | Custo |
|-------------|-----------|-------|
| `cpf_nome` | Nome completo | 0,17 |
| `cpf_nascimento` | Nome e nascimento | 0,25 |
| `cpf` | Nome, nascimento, mãe, gênero | 0,29 |
| `cpf_situacao` | Situação RF + PDF | 0,41 |
| `cpf_completo` | Completo RF em tempo real | 0,53 |
| `cpf_endereco` | Com endereço | 1,35 |
| `cpf_ppe` | Pessoa politicamente exposta | 0,23 |
| `cpf_empresas` | CNPJs em que é sócio | 0,23 |
| `cpf_endereco_situacao` | Endereço + situação | 1,57 |
| `cpf_contatos` | E-mails, telefones, WhatsApp | 0,27 |
| `cpf_family` | Mandados BNMP/CNJ | 1,13 |
| `cpf_programas_sociais` | Programas sociais | 0,17 |
| `cpf_antecedentes` | Mandados BNMP + INTERPOL (**não** é a certidão PF) | 1,46 |
| `cpf_situacao_simples` | Nome, nascimento, situação | 0,37 |
| `cpf_cac` | Certidão de antecedentes PF (PDF) | 0,27 |
| `cpf_cns` | Cartão Nacional de Saúde | 0,24 |
| `cpf_risco` | Score de risco | 0,50 |
| `cnpj_razao` | Razão social (CNPJ ou nome) | 0,15 |
| `cnpj_endereco` | Endereço | 0,27 |
| `cnpj_contato` | Contatos e situação | 0,36 |
| `cnpj` | Completo RF + cartão PDF | 0,51 |
| `cnpj_simples` | Simples/SIMEI/Suframa | 0,34 |
| `cnpj_ie` | Inscrições estaduais | 0,17 |
| `cnpj_contatos` | Contatos dos sócios | 0,30 |
| `cnpj_qsa` | QSA detalhado | 2,25 |
| `cnpj_risco` | Score de risco | 0,50 |
| `nfe` | NFe unificada PF/PJ | 0,04 |

Detalhe de `cpf_cac`: [antecedentes-criminais.md](./antecedentes-criminais.md).

### 5.2 API Brasil (prefixo `ab_` e alguns codes sem prefixo)

| `queryType` | Descrição | Custo |
|-------------|-----------|-------|
| `ab_cpf_cadastrais` | Dados cadastrais | 0,34 |
| `ab_cpf_receita_federal` | Situação RF | 0,34 |
| `ab_cpf_spc_boavista` | SPC Boa Vista | 5,00 |
| `ab_cpf_spc_serasa` | SCPC Net (code legado) | 1,40 |
| `ab_cpf_quod` | Quod restrições PF | 6,50 |
| `ab_cpf_acerta` | Acerta Essencial | 3,50 |
| `cpf_analise_credito_basic` | Análise de crédito básica PF | 10,06 |
| `ab_cpf_acerta_positivo` | Acerta Essencial+ | 4,49 |
| `ab_cpf_serasa_premium` | Relatório positivo PF | 36,30 |
| `ab_cpf_serasa_score` | Quod Score | 7,50 |
| `ab_cpf_scr_bacen` | SCR Bacen + score | 6,19 |
| `ab_cpf_limite` | Define limite PJ (**CNPJ**) | 10,79 |
| `ab_cpf_limite_positivo` | Define limite positivo PJ (**CNPJ**) | 12,39 |
| `ab_cpf_cnh` | CNH por CPF | 5,20 |
| `ab_cpf_cnh_criminals` | CNH + criminais | 4,70 |
| `ab_cpf_processos` | Processos judiciais | 6,19 |
| `ab_cnpj` | Cadastral | 0,55 |
| `ab_cnpj_socios` | Lista de sócios | 3,49 |
| `ab_cnpj_capital_social` | Por capital social | 3,60 |
| `ab_cnpj_cnae` | Por CNAE | 1,60 |
| `ab_cnpj_uf` | Por UF | 1,60 |
| `ab_cnpj_cep` | Por CEP | 1,60 |
| `ab_cnpj_lista_cnaes` | Tabela de CNAEs | 0,04 |
| `ab_cnpj_quod` | Quod PJ | 5,50 |
| `cnpj_creditos_simples` | Créditos simples PJ | 14,96 |
| `ab_cnpj_serasa_premium` | Relatório positivo PJ | 36,30 |
| `ab_veiculos_dados` | Placa — dados | 2,50 |
| `ab_veiculos_fipe` | Placa — FIPE | 0,10 |
| `ab_veiculos_agregados` | Agregados básica | 0,14 |
| `ab_veiculos_agregados_propria` | Agregados própria | 0,08 |
| `ab_veiculos_dados_v1` | Placa dados V1 | 2,50 |
| `ab_veiculos_crlv` | CRLV-e | 24,00 |
| `ab_veiculos_busca_documento` | Veículos por documento | 9,50 |
| `ab_veiculos_checklist` | Check list | 2,40 |
| `ab_veiculos_ficha_tecnica` | Ficha técnica | 2,98 |
| `ab_veiculos_vip_car` | Vip Car | 29,00 |
| `ab_veiculos_csv_completa` | Renainf/Renajud/Bin/proprietário | 4,00 |
| `ab_veiculos_proprietario` | Proprietário atual | 0,70 |
| `ab_veiculos_nacional` | Base nacional | 1,80 |
| `ab_veiculos_estadual` | Base estadual | 2,50 |
| `ab_veiculos_telefones` | Telefones + endereço | 3,89 |
| `ab_veiculos_leilao_score` | Leilão + score | 13,00 |
| `ab_veiculos_leilao` | Leilão | 11,50 |
| `ab_veiculos_debitos_v3` | Débitos V3 | 8,00 |
| `ab_veiculos_debitos` | Débitos (IPVA etc.) | 4,79 |
| `ab_veiculos_multas` | Multas | 3,45 |
| `ab_veiculos_multas_prf` | Multas PRF | 3,45 |
| `ab_veiculos_recall` | Recall | 0,62 |
| `ab_veiculos_roubo_furto` | Roubo/furto | 3,86 |
| `ab_veiculos_gravame` | Gravame | 2,71 |
| `ab_veiculos_renajud` | Renajud | 3,40 |
| `ab_veiculos_renainf` | Renainf | 3,45 |
| `ab_veiculos_fipe_placa` | FIPE por placa | 0,06 |
| `ab_cep` | Endereço por CEP | 0,04 |
| `ab_cep_cidades_ddd` | Cidades por DDD | 0,05 |
| `ab_cep_bairros` | Bairros por cidade | 0,05 |
| `ab_cep_cidades` | Cidades por UF | 0,05 |
| `ab_cep_estados` | Estados | 0,05 |
| `ab_fipe_tabela_referencia` | Tabelas FIPE | 0,06 |
| `ab_fipe_marcas` | Marcas | 0,06 |
| `ab_fipe_modelos` | Modelos | 0,06 |
| `ab_fipe_ano_modelo` | Ano/modelo | 0,06 |
| `ab_fipe_modelos_ano` | Modelos por ano | 0,06 |
| `ab_fipe_valor` | Valor FIPE | 0,06 |
| `ab_feriados` | Feriados | 0,02 |
| `ab_feriados_hoje` | É feriado hoje? | 0,02 |
| `ab_ddd` | Dados do DDD | 0,03 |
| `ab_ddd_lista` | Lista de DDDs | 0,02 |
| `ab_rastreio` | Rastreio Correios | 0,04 |
| `ab_geocode` | Geocode por endereço | 0,05 |
| `ab_geocode_reverse` | Reverse geocode | 0,05 |
| `ab_distancia` | Distância entre CEPs | 0,03 |
| `ab_clima_cidade` | Clima por cidade | 0,05 |
| `ab_clima_coordenadas` | Clima por lat/lon | 0,05 |
| `ab_ip` | Geolocalização de IP | 0,05 |

### 5.3 Tipos fora do catálogo público (existem no seeder)

| `queryType` | Descrição |
|-------------|-----------|
| `ab_ia_llama` / `ab_ia_ollama` / `ab_ia_tts` | IA (chat / TTS) |
| `ab_sms` | Envio de SMS |
| `ab_chip_*` | Chip virtual |
| `ab_ura_*` | Discador URA |

---

## 6. Painel web

Inertia (sessão `web`). Cookie CSRF nas mutações.

### 6.1 Raiz, health e docs

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/` | — | Login ou dashboard |
| GET | `/up` | — | Health check Laravel |
| GET | `/docs/api` | Login | Scalar |
| GET | `/docs/api.json` | Login | OpenAPI |

### 6.2 Fortify (auth)

Rotas típicas (Laravel Fortify):

| Método | Rota | Descrição |
|--------|------|-----------|
| GET/POST | `/login` | Login (`login` / `login.store`) |
| POST | `/logout` | Encerrar sessão |
| GET/POST | `/register` | Cadastro (nome, e-mail, telefone, documento, senha, termos) |
| GET/POST | `/forgot-password` | Pedido de reset |
| GET/POST | `/reset-password/{token}` | Nova senha |
| GET | `/email/verify` | Tela de verificação |
| GET | `/email/verify/{id}/{hash}` | Confirma e-mail |
| POST | `/email/verification-notification` | Reenvia e-mail |
| GET/POST | `/user/confirm-password` | Confirma senha (ações sensíveis) |
| GET | `/two-factor-challenge` | Desafio 2FA |
| POST | `/two-factor-challenge` | Valida 2FA |
| POST/DELETE | `/user/two-factor-authentication` | Liga/desliga 2FA |
| GET | `/user/two-factor-qr-code` | QR TOTP |
| GET | `/user/two-factor-recovery-codes` | Códigos de recuperação |
| * | rotas de **passkeys** Fortify | WebAuthn |

Login: throttle Fortify (e-mail + IP). E-mail de boas-vindas no registro; alerta de login em novo contexto.

### 6.3 Área autenticada (e-mail verificado)

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/dashboard` | Resumo: saldo, consultas, taxa de sucesso, consumo 7 dias. Admin é redirecionado a `/admin`. |
| GET | `/support-tickets/{ticket}/attachments/{attachment}/download` | Download de anexo (cliente dono ou admin) |

### 6.4 Configurações do usuário

| Método | Rota | Auth extra | Descrição |
|--------|------|------------|-----------|
| GET | `/settings` | auth | Redirect para perfil |
| GET/PATCH | `/settings/profile` | auth | Nome, e-mail, telefone |
| DELETE | `/settings/profile` | verified | Exclui conta |
| GET | `/settings/security` | verified + senha | 2FA e passkeys |
| PUT | `/settings/password` | verified, 6/min | Troca senha |
| GET | `/settings/appearance` | verified | Tema |

Timeout de sessão: middleware `EnforceSessionTimeout`; minutos configuráveis pelo admin.

### 6.5 Cliente (`/client/*`) — `auth` + `verified`

| Método | Rota | Throttle | Descrição |
|--------|------|----------|-----------|
| GET | `/client/api-keys` | — | Lista chaves |
| POST | `/client/api-keys` | — | Cria chave (token visível **uma vez**) |
| DELETE | `/client/api-keys/{apiKeyId}` | — | Revoga |
| GET | `/client/webhook` | — | Configuração |
| PUT | `/client/webhook` | — | Define URL |
| POST | `/client/webhook/regenerate-secret` | — | Novo HMAC secret |
| GET | `/client/logs` | — | Logs da própria conta |
| GET | `/client/consultations` | — | Catálogo + resultado da última consulta |
| POST | `/client/consultations/{queryType}` | 30/min | Executa consulta (mesmo use case da API) |
| GET | `/client/billing` | — | Carteira, recarga, assinatura, faturas abertas |
| GET | `/client/billing/payments/{paymentId}/status` | — | Polling de pagamento |
| POST | `/client/billing/topup` | 10/min | Recarga (PIX/boleto/cartão) |
| POST | `/client/billing/invoices/pay` | 10/min | Paga fatura |
| POST | `/client/billing/invoices/{invoiceId}/cancel` | 10/min | Cancela fatura (quando permitido) |
| POST | `/client/billing/subscribe` | 10/min | Assina plano |
| POST | `/client/billing/subscriptions/{id}/cancel` | 10/min | Cancela assinatura |
| POST | `/client/billing/subscriptions/{id}/change` | 10/min | Troca de plano |
| GET | `/client/invoices` | — | Lista faturas |
| GET | `/client/invoices/{invoiceId}` | — | Detalhe |
| GET | `/client/invoices/{invoiceId}/pdf` | — | PDF (`FAT-{ano}-{seq}`) |
| GET | `/client/tickets` | — | Chamados |
| GET | `/client/tickets/create` | — | Formulário |
| POST | `/client/tickets` | — | Abre chamado |
| GET | `/client/tickets/{ticket}` | — | Thread |
| POST | `/client/tickets/{ticket}/reply` | — | Resposta (reabre se encerrado) |

Pagamentos: API transparente Mercado Pago (não Checkout Pro). Status de fatura: `open`, `overdue`, `paid`, `canceled`.

### 6.6 Admin (`/admin/*`) — `auth` + `verified` + `role:admin` + 60/min

Várias mutações exigem **senha confirmada** (`RequirePassword`).

| Método | Rota | Senha | Descrição |
|--------|------|-------|-----------|
| GET | `/admin` | — | Dashboard operacional |
| GET | `/admin/accounts` | — | Clientes |
| GET | `/admin/accounts/{accountId}` | — | Detalhe da conta |
| POST | `/admin/accounts` | — | Cria conta |
| POST | `/admin/accounts/{accountId}/adjust` | sim | Ajuste manual de créditos |
| POST | `/admin/accounts/{accountId}/assign-plan` | sim | Atribui plano |
| GET | `/admin/finance` | — | Visão financeira |
| GET | `/admin/plans` | — | Planos |
| GET | `/admin/plans/{planId}` | — | Detalhe |
| POST | `/admin/plans` | — | Cria plano |
| PUT | `/admin/plans/{planId}` | — | Atualiza plano |
| GET | `/admin/providers` | — | Provedores |
| GET | `/admin/providers/{providerId}` | — | Detalhe |
| GET | `/admin/providers/{providerId}/balance` | — | Saldo no provedor |
| POST | `/admin/providers` | — | Cadastra provedor |
| PUT | `/admin/providers/{providerId}` | sim | Credenciais / config |
| POST | `/admin/providers/{providerId}/toggle` | — | Liga/desliga |
| POST | `/admin/providers/{providerId}/environment` | — | Sandbox ↔ produção |
| POST | `/admin/providers/{providerId}/capabilities` | sim | Upsert de capability (preço/rota) |
| GET | `/admin/query-types` | — | Catálogo de tipos |
| PUT | `/admin/query-types/{queryTypeId}` | — | Status, TTL, preço default |
| GET | `/admin/tickets` | — | Fila de suporte |
| GET | `/admin/tickets/{ticket}` | — | Detalhe |
| PATCH | `/admin/tickets/{ticket}` | — | Status |
| POST | `/admin/tickets/{ticket}/reply` | — | Resposta da equipe |
| GET | `/admin/logs` | — | Auditoria global |
| GET | `/admin/settings` | — | Timeout de sessão etc. |
| PUT | `/admin/settings` | sim | Salva configurações |

Suporte: categorias técnico / financeiro / dúvidas; status aberto → em andamento (1ª resposta admin) → encerrado. Anexos: até 5, 10 MB, JPG/PNG/PDF, disco privado. E-mail: `SUPPORT_NOTIFY_EMAIL`.

---

## 7. Billing (comportamento)

- **Carteira:** `balance`, `reserved`, `available` em centavos BRL. Redis pode cachear saldo; `billing:reconcile-balances` reconcilia com o banco.
- **Consulta:** reserva → commit ou refund. Transações do tipo `commit` alimentam “créditos gastos” no dashboard.
- **Recarga:** cria pagamento MP; webhook ou `billing:sync-payments` liquida e credita.
- **Assinatura:** ciclo gera fatura (`billing:run-recurring` às 06:00). Renovações manuais: `metadata.origin=renewal`.
- **Margem:** `Pricing::MARKUP_PERCENT = 10`.

---

## 8. Webhook de saída (consultas)

Enviado **depois** da persistência, via fila Laravel. Evento: `consultation.completed`. Header `X-Consulte-Signature`. Não dispara em 402/404.

Detalhes, payload, HMAC, retries: [webhook.md](./webhook.md).

---

## 9. Auditoria e LGPD

- Middleware `LogRequest` em todas as rotas `api/*`.
- Consultas do painel também geram log.
- `consultation:purge-request-hash --days=180` (agendado 03:30) anonimiza fingerprint antigo.

---

## 10. Comandos Artisan e agenda

| Comando | Função |
|---------|--------|
| `billing:reconcile-balances` | Reconcilia cache de saldo (a cada 15 min) |
| `billing:run-recurring` | Overdue + faturas de ciclo (diário 06:00) |
| `billing:backfill-invoice-numbers` | Números `FAT-…` em faturas antigas |
| `billing:sync-payments` | Reconsulta pagamentos pendentes no MP |
| `consultation:purge-request-hash` | Anonimiza hashes (diário 03:30) |
| `consultation:refresh-cached-pdf` | Reenriquece PDFs em cache |
| `catalog:reprice` (`providers:sync-pricing`) | Recalcula venda = custo + 10% |
| `provider:toggle {identifier}` | Liga/desliga provedor |
| `identity:issue-key {name} {document}` | Cria conta + API key (token uma vez) |

Scheduler: `php artisan schedule:work` (ou cron `schedule:run`). Fila: `php artisan queue:work` (webhooks, e-mails).

---

## 11. E-mails transacionais

Fila (`ShouldQueue`): boas-vindas, verificação de e-mail, alerta de login, pagamento confirmado, notificações de ticket. Em desenvolvimento, `MAIL_MAILER=log`.

---

## 12. Variáveis de ambiente relevantes

| Prefixo / chave | Uso |
|-----------------|-----|
| `APP_*` | App Laravel (`APP_KEY` criptografa webhook secret) |
| `MAIL_*` | E-mail |
| `API_BRASIL_*` | Gateway API Brasil |
| `CPFCNPJ_*` | Provedor CPF.CNPJ |
| `MP_*` | Mercado Pago (incl. webhook secret) |
| `QUEUE_CONNECTION` | `database` ou `redis` |
| `CONSULTATION_CACHE_TTL_SECONDS` | TTL padrão de cache |
| `CONSULTATION_OCR_*` | OCR local de PDFs |
| `SUPPORT_NOTIFY_EMAIL` | Destino de novos tickets |
| `TRUSTED_PROXIES` | Proxies (TLS termination) |

---

## 13. Testes e qualidade

```bash
composer test        # PHPUnit + Pint
composer ci:check    # ESLint, Prettier, TypeScript e testes
```

Coleção Postman: `postman/Consulte-Brasil-API.postman_collection.json`.
