# Consulte Brasil — Documentação completa do sistema

Este documento descreve o que a plataforma faz, todos os endpoints HTTP (API pública e painel web), o modelo de dados, o frontend, segurança e os processos de fundo. Valores de preço no catálogo de consultas são **custo do provedor**; o preço cobrado do cliente é esse custo **mais 10% de margem**, e pode ser alterado no painel admin. Sempre use `GET /api/v1/services` ou o campo `amount_charged` da resposta para o valor vigente.

Documentação interativa (OpenAPI / Scalar): **`/docs/api`** (login no painel). JSON: **`/docs/api.json`**.

Sumário: [1 sistema](#1-o-que-o-sistema-faz) · [2 autenticação](#2-autenticação) · [3 erros](#3-envelope-de-erros-api) · [4 API](#4-api-pública-apiv1) · [5 catálogo](#5-catálogo-de-tipos-de-consulta) · [6 painel](#6-painel-web) · [7 billing](#7-billing-comportamento) · [8 webhook](#8-webhook-de-saída-consultas) · [9 auditoria](#9-auditoria-e-lgpd) · [10 Artisan](#10-comandos-artisan-e-agenda) · [11 e-mails](#11-e-mails-transacionais) · [12 env](#12-variáveis-de-ambiente-relevantes) · [13 testes](#13-testes-e-qualidade) · [14 arquitetura](#14-arquitetura-e-estrutura-de-pastas) · [15 dados](#15-modelo-de-dados) · [16 provedores](#16-provedores-failover-e-circuit-breaker) · [17 identidade](#17-identidade-em-detalhe) · [18 billing detalhado](#18-billing-em-detalhe) · [19 frontend](#19-frontend-páginas-e-navegação) · [20 segurança](#20-segurança) · [21 eventos](#21-eventos-jobs-e-e-mails) · [22 seeders](#22-seeders-e-dados-iniciais) · [23 operação](#23-operação-e-ci)

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

Coleção Postman: `postman/Consulte-Brasil-API.postman_collection.json`. Ambiente local: `postman/Consulte-Brasil-Local.postman_environment.json`.

CI: `.github/workflows/tests.yml` e `lint.yml`.

---

## 14. Arquitetura e estrutura de pastas

A aplicação é um backend Laravel com **bounded contexts** em `src/Modules/` (DDD). Cada módulo tem:

| Camada | Pasta | Conteúdo |
|--------|-------|----------|
| Domain | `Domain/` | Entidades, value objects, eventos, exceções, portas |
| Application | `Application/` | Use cases, DTOs, serviços de orquestração |
| Infrastructure | `Infrastructure/` | HTTP, Eloquent, jobs, adapters de provedor, console |

O kernel compartilhado fica em `src/Shared/` (UUID, relógio, event bus, CPF/CNPJ, e-mail transacional).

O Laravel clássico em `app/` cobre Fortify, middlewares globais, e-mails, settings e o dashboard.

```
app/                      Kernel, Fortify, mail, settings, middlewares
bootstrap/                app.php (rotas, exceptions, middleware)
config/                   Laravel + consultation, audit, scramble, fortify
database/                 migrations globais, seeders, factories
docs/                     Esta documentação
postman/                  Collection da API pública
public/                   Front controller
resources/js/             React + Inertia (páginas, hooks, tipos)
resources/views/          Blade (app, e-mails, Scalar)
routes/                   web, api, admin, client, settings, console
src/Modules/              Identity, Billing, Consultation, Provider, Audit, Support
src/Shared/               Contratos e utilitários de domínio
tests/                    Feature e Unit (PHPUnit)
```

Rotas da API de cada módulo: `src/Modules/*/Infrastructure/Http/routes.php`, carregadas automaticamente por `routes/api.php` sob `/api/v1`.

---

## 15. Modelo de dados

IDs de domínio são UUID. Timestamps de logs/consultas podem ser tratados como UTC na UI (`APP_DISPLAY_TIMEZONE`).

| Tabela | Módulo | Função |
|--------|--------|--------|
| `users` | Auth | Usuário do painel (e-mail, senha, 2FA, `account_id`, `role`, último login) |
| `accounts` | Identity | Tenant (nome, documento, status, `webhook_url`, `webhook_secret` cifrado) |
| `api_keys` | Identity | Prefixo, hash SHA-256, últimos 4 dígitos, status, expiração |
| `passkeys` | Fortify | Credenciais WebAuthn |
| `wallets` | Billing | `balance` e `reserved` em centavos |
| `credit_transactions` | Billing | Extrato (`grant`, `reserve`, `commit`, `refund`, `expire`, `adjustment`) |
| `plans` | Billing | Catálogo de assinatura |
| `subscriptions` | Billing | Assinatura da conta (status, próximo faturamento, snapshot de preço) |
| `payments` | Billing | Pagamentos Mercado Pago (PIX/cartão/boleto) |
| `invoices` / `invoice_items` | Billing | Faturas (`FAT-{ano}-{seq}`) |
| `query_types` | Consultation | Código, nome, preço default, TTL de cache, status |
| `consultations` | Consultation | Histórico: tipo, status, custo, hash do request, latência (sem payload em claro) |
| `providers` | Provider | `api_brasil`, `cpfcnpj`, `mercado_pago` (ambiente sandbox/produção) |
| `provider_capabilities` | Provider | Tipo × provedor: prioridade, `cost_cents`, `price_cents`, config JSON |
| `request_logs` | Audit | Request/response cifrados (`encrypted:array`), duração, status |
| `support_tickets` (+ mensagens/anexos) | Support | Chamados |
| `settings` | App | Chave/valor (ex.: timeout de sessão) |
| `jobs` / `failed_jobs` / `cache` | Laravel | Fila e cache |

### 15.1 Status importantes

| Conceito | Valores |
|----------|---------|
| Consulta | `success`, `failed`, `refunded` |
| Conta | `active` (e equivalentes de domínio; API key só autentica conta ativa) |
| API key | ativa / revogada / expirada (`isUsable`) |
| Papel | `admin`, `client` |
| Fatura | `open`, `overdue`, `paid`, `canceled` |
| Pagamento | pendente / processando / aprovado / rejeitado / cancelado (domínio `PaymentStatus`) |
| Método de pagamento | `pix`, `credit_card`, `boleto` |
| Ticket | aberto → em andamento → encerrado |
| Provedor | enabled/disabled + `sandbox` / `production` |
| Tipo de consulta | `active` (só ativos com capability ligada entram no catálogo público) |

---

## 16. Provedores, failover e circuit breaker

Três registros em `providers`:

| Identifier | Papel |
|------------|--------|
| `api_brasil` | Consultas (prioridade menor = tenta primeiro nos tipos `cpf`/`cnpj` do seeder: 1 e 2) |
| `cpfcnpj` | Consultas (failover nos tipos básicos: prioridade 10 e 11). Catálogo completo via pacotes |
| `mercado_pago` | Só ambiente do gateway de pagamento (sem capabilities de consulta) |

`ProviderRouter`:

1. Lista capabilities **enabled** do tipo, ordenadas por prioridade.
2. Pula provedor com **circuit breaker aberto**.
3. Primeiro sucesso fecha o circuito.
4. Falha transitória (timeout/5xx) incrementa falhas; 5 falhas em 120 s abrem o circuito por 60 s.
5. Documento não encontrado / erro de negócio do provedor **não** abre o circuito (não é outage).
6. Se ninguém atende → `AllProvidersFailed` → estorno.

Adapters: `CpfCnpjAdapter` (pacote no `config` da capability) e `ApiBrasilAdapter` (endpoint, DeviceToken por grupo, timeout maior em rotas `/credits`).

Ambiente sandbox usa tokens de teste (`*_SANDBOX_TOKEN`) e não deve consumir crédito real do provedor até o admin promover para produção.

---

## 17. Identidade em detalhe

- Guard `web`: sessão (Fortify).
- Guard `api`: driver `api-key` (`Auth::viaRequest` no IdentityServiceProvider). O `user()` da API é o **AccountModel**, não o User.
- Token: `cb_live_` + segredo. Lookup pelo **prefixo** (label + 8 caracteres) + verificação SHA-256 do restante. Painel mostra só os 4 últimos dígitos.
- Token em texto claro só no create (flash `plain_token`) ou no Artisan `identity:issue-key`.
- Webhook secret cifrado com `APP_KEY`; flash `plain_secret` na primeira vez / regeneração.
- Registro exige CPF/CNPJ válido (`ValidDocument`) e aceite de termos.
- Excluir perfil (`DELETE /settings/profile`) faz logout.

---

## 18. Billing em detalhe

Planos seedados (mensais):

| Slug | Nome | Preço | Saldo incluso |
|------|------|-------|----------------|
| `starter` | Starter | R$ 49,00 | R$ 100,00 |
| `growth` | Growth | R$ 149,00 | R$ 500,00 |
| `scale` | Scale | R$ 499,00 | R$ 2.000,00 |

Fluxos no painel Financeiro:

- Recarga avulsa (`topup`) gera `Payment` e instruções PIX/boleto ou tokenização de cartão (SDK MP no browser, CSP libera `sdk.mercadopago.com`).
- Polling em `GET /client/billing/payments/{id}/status`.
- PIX expira em `MP_PIX_EXPIRATION_MINUTES` (padrão 30). Boleto em `MP_BOLETO_EXPIRATION_DAYS` (padrão 3).
- Assinar plano credita `included_balance` e agenda ciclo.
- Cancelar fatura: se houver PIX/boleto pendente, espera **120 s** (`CancelInvoice::CANCEL_GRACE_SECONDS`) e sincroniza o MP antes; se já aprovado, não cancela.
- Ajuste admin: transação `adjustment` (exige senha).
- `billing:run-recurring`: marca overdue e gera faturas de renovação.

---

## 19. Frontend: páginas e navegação

SPA Inertia + React + TypeScript + Tailwind (`resources/js/pages`).

**Menu cliente:** Painel, Consultas, Financeiro, Minhas Faturas, Suporte, Minhas chaves, Webhook, Logs.

**Menu admin:** Painel, Clientes, Financeiro, Planos, Tickets, Tipos de consulta, Provedores, Logs, Configurações.

Rodapé: link para `/docs/api`. Badge de tickets não lidos (cliente e admin).

Páginas de auth: login, registro, forgot/reset password, verify-email, 2FA challenge, confirm-password.

Consultas no painel: formulário por tipo (`document` ou `razao_social`); resultado em sessão (`consultation_result`); exportação PDF no cliente (`export-consultation-pdf.ts`); anexos/base64 quando o resultado traz certidão.

Hooks relevantes: `use-session-timeout` (inatividade alinhada ao admin), `use-flash-toast`, `use-two-factor-auth`, `use-clipboard`.

Props globais Inertia: usuário, timeout, flash (`success`/`error`/`plain_token`/`plain_secret`/`payment`), timezone, URL do site de marketing, `adminShell` (consumo no header admin).

---

## 20. Segurança

- Headers: `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP, HSTS em produção, CSP (relaxada em `docs/*` e local por causa do Vite/Scalar).
- CSRF nas mutações web.
- Rate limit nas rotas críticas (API, consultas do painel, billing, login).
- Senha confirmada em: ajuste de créditos, atribuição de plano, update de provedor/capabilities, settings admin, tela de segurança.
- Auditoria: corpos/headers/respostas de `request_logs` **cifrados**; CPF/CNPJ mascarados se `AUDIT_MASK_DOCUMENTS=true` (padrão). Cartão/credenciais sempre mascarados.
- Consultas persistem só `request_hash`, não o documento em claro; purge após 180 dias.
- Timeout de sessão: default **120 min**, intervalo 1–1440, persistido em `settings`. `SESSION_LIFETIME` no `.env` deve ser ≥ o máximo admin (1440).
- API key e webhook secret nunca voltam em `GET` depois de emitidos.

---

## 21. Eventos, jobs e e-mails

| Evento | Listener | Efeito |
|--------|----------|--------|
| `Registered` (Fortify) | `SendWelcomeEmail` | E-mail de boas-vindas |
| `Login` | `SendLoginAlertEmail` | Alerta se IP/UA mudou |
| `AccountRegistered` | `ProvisionWalletForAccount` | Cria carteira zerada |
| `PaymentSettled` | `SendPaymentConfirmedEmail` | Recibo de pagamento |
| `ConsultationCompleted` | `DispatchConsultationWebhook` | Enfileira `DeliverConsultationWebhookJob` |

Job de webhook: 3 tentativas, backoff 30 / 120 / 300 s, timeout HTTP 10 s, `User-Agent: ConsulteBrasil-Webhook/1.0`.

E-mails (fila): welcome, verify-email, login-alert, payment-confirmed, ticket opened / reply / status. Layout em `resources/views/mail/`.

Canal de log `consultation` para falhas de entrega do webhook.

---

## 22. Seeders e dados iniciais

`php artisan db:seed` (via `DatabaseSeeder`):

- Usuário admin `admin@consultebrasil.test` / senha `password` (só desenvolvimento).
- Planos Starter / Growth / Scale.
- Tipos `cpf` e `cnpj` básicos + catálogos CPF.CNPJ e API Brasil.
- Provedores `api_brasil`, `cpfcnpj` (sandbox), `mercado_pago` (sandbox).

Comando `catalog:reprice` realinha `cost_cents`/`price_cents` aos seeders + margem.

---

## 23. Operação e CI

Scripts Composer:

| Comando | O que faz |
|---------|-----------|
| `composer setup` | install, `.env`, `key:generate`, migrate, npm build |
| `composer dev` | `artisan serve` + `queue:listen` + Vite |
| `composer test` | Pint + PHPUnit |
| `composer ci:check` | ESLint, Prettier, TypeScript + testes |

Produção: `queue:work`, `schedule:work` (ou cron `* * * * * php artisan schedule:run`), `queue:restart` após deploy. Redis recomendado para cache de saldo, circuit breaker e cache de consultas.

Health:

- `GET /up` — Laravel
- `GET /api/v1/ping` — API de negócio

Fuso: `APP_TIMEZONE` (jobs/métricas) e `APP_DISPLAY_TIMEZONE` (telas). Padrão `America/Sao_Paulo`.

Site institucional: `MARKETING_SITE_URL` (link no painel).

---

## 24. Variáveis de ambiente (lista estendida)

Além da tabela da [seção 12](#12-variáveis-de-ambiente-relevantes):

| Chave | Uso |
|-------|-----|
| `MARKETING_SITE_URL` | URL do site comercial |
| `APP_TIMEZONE` / `APP_DISPLAY_TIMEZONE` | Fuso app vs. UI |
| `AUDIT_MASK_DOCUMENTS` | Máscara de CPF/CNPJ nos logs (true em produção) |
| `SESSION_LIFETIME` | Cookie de sessão (minutos; ≥ timeout admin) |
| `CACHE_STORE` / `REDIS_*` | Cache (circuit breaker, saldo, consultas) |
| `API_BRASIL_TIMEOUT` / `API_BRASIL_CREDIT_TIMEOUT` | Timeouts HTTP (crédito/bureau até 45 s) |
| `API_BRASIL_DEVICE_TOKEN_*` | DeviceToken por grupo (cpf, cnpj, vehicles, cep, …) |
| `CPFCNPJ_TIMEOUT` / `CPFCNPJ_PACKAGE_*` | Timeout e pacotes default |
| `MP_PUBLIC_KEY` / `MP_SANDBOX_PUBLIC_KEY` | SDK no browser |
| `MP_PIX_EXPIRATION_MINUTES` / `MP_BOLETO_EXPIRATION_DAYS` | Validade das cobranças |

Lista comentada completa: `.env.example`.

