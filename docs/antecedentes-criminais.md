# Consulte Brasil — Documentação de Integração

> **Público-alvo:** equipe externa que vai integrar o **endpoint de antecedentes criminais** em outra plataforma.
> Este documento descreve como o sistema funciona, como autenticar, e especifica em detalhe o endpoint de antecedentes criminais (entrada, saída, erros, custos e fluxo).

---

## 1. Visão geral do sistema

O **Consulte Brasil** é uma plataforma de consulta de dados oficiais brasileiros (CPF, CNPJ, veículos, antecedentes, etc.) que expõe uma **API HTTP pública** baseada em **créditos pré-pagos**.

| Item | Descrição |
|------|-----------|
| Backend | PHP 8.3 + Laravel 13 |
| Modelo de cobrança | Créditos pré-pagos (carteira por conta), cobrança por consulta em centavos de BRL |
| Autenticação da API | Chave de API (Bearer token `cb_live_…`) |
| Formato | JSON em todas as requisições e respostas |
| Provedores de dados | CPF.CNPJ e API Brasil (transparente para o integrador) |
| Catálogo | ~125 tipos de consulta, todos no mesmo endpoint dinâmico |
| Notificações | Webhook de saída opcional (`consultation.completed`) |
| Documentação interativa | `/docs/api` (Scalar/OpenAPI), requer login no painel |

### 1.1 Arquitetura (resumo)

A API segue um modelo modular (DDD). Para o integrador, o que importa é o fluxo de uma consulta:

```
Requisição HTTP (Bearer API key)
   → Autenticação por API key (auth:api)
   → Rate limit (throttle)
   → Validação dos parâmetros (ex.: dígito verificador do CPF)
   → Reserva de crédito na carteira
   → Cache de resultado (se houver, retorna do cache)
   → Roteamento para o provedor de dados (com failover)
   → Sucesso: confirma cobrança + grava consulta + (opcional) webhook
   → Falha total do provedor: ESTORNA o crédito (cliente não paga)
   → Resposta JSON
```

**Garantia importante:** o cliente **nunca é cobrado** por falha do provedor (ex.: documento não encontrado, indisponibilidade). Nesses casos a reserva de crédito é estornada automaticamente.

### 1.2 Base URL e versionamento

Todas as rotas da API pública ficam sob o prefixo:

```
{BASE_URL}/api/v1
```

`{BASE_URL}` é o domínio do ambiente (ex.: `https://api.consultebrasil.com` em produção, ou o host configurado em `APP_URL`).

---

## 2. Autenticação

A API pública usa **chaves de API** no formato `cb_live_{segredo}`, enviadas no header `Authorization`:

```http
Authorization: Bearer cb_live_xxxxxxxxxxxxxxxxxxxxxxxx
```

Características:

- **Não há JWT nem OAuth.** A chave é um token opaco.
- A chave é gerada no **painel do cliente** (`/client/api-keys`) ou via CLI (`php artisan` — uso interno).
- A chave é validada por prefixo + hash SHA-256; verifica-se expiração, status da chave e se a conta está ativa.
- Em qualquer falha de autenticação, a resposta é **HTTP 401** (sem detalhar o motivo).

### 2.1 Validar a autenticação

Use `GET /api/v1/me` para confirmar que a chave está válida:

```http
GET /api/v1/me
Authorization: Bearer cb_live_xxxxxxxx
```

Resposta `200`:

```json
{
  "data": {
    "id": "01932a1b-8c4d-7000-8000-000000000001",
    "name": "Minha Empresa LTDA",
    "document": "12345678000199",
    "status": "active",
    "authenticated_via_api_key": "01932a1b-8c4d-7000-8000-000000000002"
  }
}
```

### 2.2 Consultar saldo de créditos

```http
GET /api/v1/credits
Authorization: Bearer cb_live_xxxxxxxx
```

---

## 3. Endpoint de antecedentes criminais

> **Atenção:** existem três tipos de consulta relacionados a "antecedentes/criminal". O endpoint principal — a **Certidão de Antecedentes Criminais da Polícia Federal** — é o **`cpf_cac`**. Não confundir com os outros dois (ver seção 3.8).

### 3.1 Rota e método

```http
POST /api/v1/consult/cpf_cac
Authorization: Bearer cb_live_xxxxxxxx
Content-Type: application/json
```

> Observação: a API usa **um endpoint dinâmico** (`POST /api/v1/consult/{queryType}`) para todos os ~125 tipos de consulta. Para antecedentes criminais, `queryType` = `cpf_cac`.

### 3.2 Corpo da requisição

```json
{
  "params": {
    "document": "11144477735"
  }
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `params` | object | Sim | Objeto com os parâmetros da consulta |
| `params.document` | string | Sim | CPF do titular. Pode ir com ou sem formatação (`111.444.777-35` ou `11144477735`). Validado por dígito verificador **antes** de cobrar crédito. |

### 3.3 Resposta de sucesso (HTTP 200)

A consulta retorna a **Certidão de Antecedentes Criminais (PF) em PDF (base64)**, além de campos estruturados que o próprio sistema extrai do PDF (parsing local, com OCR de fallback quando o PDF é uma imagem escaneada).

```json
{
  "data": {
    "consultation_id": "01932a1b-8c4d-7000-8000-000000000001",
    "provider": "cpfcnpj",
    "amount_charged": 50,
    "amount_charged_formatted": "R$ 0,50",
    "credits_charged": 50,
    "from_cache": false,
    "data": {
      "cpf": "785.111.519-15",
      "nome": "Fulano de Tal",
      "nadaConsta": true,
      "nrProtocolo": "126278162026",
      "cac": {
        "nrProtocolo": "126278162026",
        "nadaConsta": true,
        "comprovantePdfBase64": "JVBERi0xLjQKJ...",
        "certificado": {
          "tipo": "antecedentes_criminais",
          "titulo": "Certidão de Antecedentes Criminais",
          "conclusao": "NADA CONSTA",
          "nadaConsta": true,
          "nome": "Fulano de Tal",
          "cpf": "785.111.519-15",
          "nrProtocolo": "126278162026",
          "dataEmissao": "18/06/2026",
          "validade": "90 dias",
          "orgaoEmissor": "Polícia Federal",
          "textoResumo": "..."
        }
      },
      "raw": {
        "...": "payload original do provedor, sem metadados de billing"
      }
    }
  }
}
```

#### Campos do envelope (`data`)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `consultation_id` | string (UUID) | Identificador único da consulta (use para idempotência/conciliação) |
| `provider` | string | Provedor que atendeu (`cpfcnpj`) |
| `amount_charged` | int | Valor cobrado em **centavos de BRL** |
| `amount_charged_formatted` | string | Valor formatado (ex.: `R$ 0,50`) |
| `credits_charged` | int | Mesmo valor em centavos (mantido por compatibilidade) |
| `from_cache` | bool | `true` se a resposta veio do cache (ver seção 3.6) |
| `data` | object | Dados da consulta propriamente dita |

#### Campos do resultado (`data.data`)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cpf` | string | CPF consultado (formatado) |
| `nome` | string | Nome do titular |
| `nadaConsta` | bool | Resumo no nível raiz: `true` = nada consta |
| `nrProtocolo` | string | Número de protocolo da certidão |
| `cac` | object | Bloco principal da certidão |
| `cac.comprovantePdfBase64` | string | **PDF da certidão em base64** (documento oficial) |
| `cac.nadaConsta` | bool | Indicador de "nada consta" |
| `cac.nrProtocolo` | string | Protocolo |
| `cac.certificado` | object | Campos estruturados extraídos do PDF (ver abaixo) |
| `raw` | object | Payload bruto do provedor (sem metadados de saldo/billing) — útil para depuração |

#### Campos de `cac.certificado` (extraídos do PDF pelo sistema)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `tipo` | string | `antecedentes_criminais` |
| `titulo` | string | `Certidão de Antecedentes Criminais` |
| `conclusao` | string | `NADA CONSTA` ou `CONSTAM REGISTROS` |
| `nadaConsta` | bool\|null | `true` (negativa), `false` (positiva), `null` (indeterminado) |
| `nome` | string | Nome do titular |
| `cpf` | string | CPF formatado |
| `nrProtocolo` | string | Número de protocolo |
| `dataEmissao` | string | Data de emissão (`dd/mm/aaaa`) |
| `validade` | string | Validade (ex.: `90 dias` ou data) |
| `orgaoEmissor` | string | Ex.: `Polícia Federal` |
| `textoResumo` | string | Trecho resumido do texto da certidão |

> **Para integração, recomenda-se usar os campos estruturados de `cac.certificado`** (`nadaConsta`, `conclusao`, `nrProtocolo`, etc.) para a lógica de negócio, e o `comprovantePdfBase64` para exibir/armazenar o documento oficial.
>
> **Importante:** os campos de `certificado` são extraídos por parsing de texto do PDF (com OCR de fallback). Em PDFs atípicos, algum campo pode vir ausente ou `null`. O `comprovantePdfBase64` é sempre a fonte de verdade oficial.

### 3.4 Custo

- O valor cobrado vem em `amount_charged` / `credits_charged` (centavos de BRL) na resposta.
- O preço de venda é configurável pelo administrador (custo do provedor + margem). O custo de provedor do `cpf_cac` no catálogo é de R$ 0,27; o preço final de venda é definido pela plataforma.
- **Sempre leia o valor cobrado na resposta** em vez de assumir um valor fixo.
- Para conhecer o preço vigente sem cobrar, consulte `GET /api/v1/services/cpf_cac` (público, ver seção 4).

### 3.5 Respostas de erro

Todos os erros da API seguem o envelope:

```json
{
  "error": {
    "type": "insufficient_credits",
    "message": "Mensagem amigável."
  }
}
```

| HTTP | `error.type` | Quando ocorre | Cliente é cobrado? |
|------|--------------|---------------|--------------------|
| 401 | — | Chave de API ausente, inválida ou revogada | Não |
| 402 | `insufficient_credits` | Saldo insuficiente na carteira | Não |
| 404 | `unknown_query_type` | Tipo `cpf_cac` inexistente/desabilitado | Não |
| 422 | `invalid_document` | CPF ausente ou com dígito verificador inválido | Não |
| 422 | (validação Laravel) | `params` ausente/mal formado | Não |
| 503 | `no_provider_available` | Nenhum provedor habilitado para o tipo | Não |
| 503 | `all_providers_failed` | Provedor falhou / documento não encontrado | **Não (estorno automático)** |

Exemplo de erro 422 (CPF inválido):

```json
{
  "error": {
    "type": "invalid_document",
    "message": "O CPF informado é inválido."
  }
}
```

### 3.6 Cache

- Resultados são cacheados por padrão por **24 horas** (`CONSULTATION_CACHE_TTL_SECONDS`, configurável).
- A chave de cache é um fingerprint de `tipo + parâmetros` (hash do CPF + tipo).
- Em um acerto de cache, `from_cache` vem `true`. **A consulta ainda é cobrada** (o cache reduz latência e custo de provedor, não o preço ao cliente).

### 3.7 Rate limiting

- Endpoint de consulta: **60 requisições por minuto** por chave/IP.
- Exceder o limite retorna **HTTP 429** (resposta padrão do Laravel: `Too Many Requests`).

### 3.8 Tipos relacionados (não confundir)

| Código | O que retorna | Provedor |
|--------|---------------|----------|
| **`cpf_cac`** | **Certidão de Antecedentes Criminais (PF) em PDF** — endpoint principal | CPF.CNPJ (pacote 27) |
| `cpf_antecedentes` | Mandados de prisão (BNMP) + lista INTERPOL — **não** é a certidão da PF | CPF.CNPJ (pacote 23) |
| `ab_cpf_cnh_criminals` | CNH + dados criminais combinados | API Brasil |

Para integrar a certidão oficial de antecedentes criminais, use **`cpf_cac`**.

---

## 4. Catálogo de serviços (descoberta e preços)

Endpoints **públicos** (sem autenticação) para descobrir tipos e preços vigentes:

```http
GET /api/v1/services            # lista todos os tipos ativos
GET /api/v1/services/cpf_cac    # detalhe + preço do antecedente criminal
```

Útil para a outra plataforma exibir o preço atualizado antes de disparar a consulta.

---

## 5. Integração assíncrona (webhook opcional)

Além da resposta síncrona, a conta pode configurar um **webhook de saída**. Ao concluir uma consulta (inclusive `cpf_cac`), o Consulte Brasil envia um `POST` para a URL cadastrada com o evento `consultation.completed`, assinado por HMAC-SHA256.

- Configuração: painel `/client/webhook` ou `PUT /api/v1/webhook`.
- Útil para reconciliar resultados e tratar reembolsos (`status: refunded`).
- **Não é necessário** para usar a API de forma síncrona.

Detalhes completos (payload, assinatura, retentativas) em [`docs/webhook.md`](./webhook.md).

---

## 6. Exemplos práticos

### 6.1 cURL

```bash
curl -X POST "https://api.consultebrasil.com/api/v1/consult/cpf_cac" \
  -H "Authorization: Bearer cb_live_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"params":{"document":"11144477735"}}'
```

### 6.2 PHP (Guzzle / Laravel HTTP)

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken('cb_live_xxxxxxxx')
    ->acceptJson()
    ->post('https://api.consultebrasil.com/api/v1/consult/cpf_cac', [
        'params' => ['document' => '11144477735'],
    ]);

if ($response->successful()) {
    $cert = $response->json('data.data.cac.certificado');
    $pdfBase64 = $response->json('data.data.cac.comprovantePdfBase64');
    $nadaConsta = $cert['nadaConsta'] ?? null;
} else {
    $errorType = $response->json('error.type');
    // tratar 401 / 402 / 422 / 503
}
```

### 6.3 Node.js (fetch)

```javascript
const res = await fetch(
  'https://api.consultebrasil.com/api/v1/consult/cpf_cac',
  {
    method: 'POST',
    headers: {
      Authorization: 'Bearer cb_live_xxxxxxxx',
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ params: { document: '11144477735' } }),
  }
);

const json = await res.json();

if (res.ok) {
  const cert = json.data.data.cac.certificado;
  const pdfBase64 = json.data.data.cac.comprovantePdfBase64;
  console.log('Nada consta?', cert?.nadaConsta);
} else {
  console.error('Erro', res.status, json.error?.type);
}
```

### 6.4 Salvar o PDF da certidão (Node.js)

```javascript
import { writeFileSync } from 'fs';

const pdfBase64 = json.data.data.cac.comprovantePdfBase64;
writeFileSync('certidao.pdf', Buffer.from(pdfBase64, 'base64'));
```

---

## 7. Viabilidade da integração — pontos de atenção

Para a avaliação da equipe que vai integrar:

1. **Autenticação simples:** basta uma chave `cb_live_…` no header `Authorization`. Sem fluxo OAuth.
2. **Modelo de custo por crédito:** é preciso manter saldo na carteira; tratar o erro `402 insufficient_credits` e monitorar `GET /api/v1/credits`.
3. **Resposta inclui PDF em base64:** o payload pode ser grande. Planeje armazenamento/streaming do `comprovantePdfBase64`.
4. **Campos estruturados são derivados de OCR/parsing:** confiáveis para a maioria dos casos, mas o PDF é a fonte oficial. Para decisões críticas, considere validar o `comprovantePdfBase64`.
5. **Cobrança segura:** falhas de provedor geram estorno (não há cobrança), retornando `503 all_providers_failed`. Implemente retry com backoff.
6. **Cache de 24h:** consultas repetidas do mesmo CPF retornam do cache (mais rápido), mas ainda são cobradas.
7. **Rate limit de 60/min:** dimensione o volume; trate `429`.
8. **Idempotência/conciliação:** use `consultation_id` para correlacionar consultas e eventuais webhooks.
9. **Webhook opcional:** permite processamento assíncrono e reconciliação de reembolsos.
10. **LGPD:** trata-se de dado pessoal sensível (antecedentes criminais). Garanta base legal, minimização e armazenamento seguro do PDF e dos campos retornados.

---

## 8. Referência rápida de endpoints

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/api/v1/ping` | Não | Health check |
| GET | `/api/v1/services` | Não | Lista de tipos/preços |
| GET | `/api/v1/services/{code}` | Não | Detalhe de um tipo |
| GET | `/api/v1/me` | API key | Dados da conta |
| GET | `/api/v1/credits` | API key | Saldo da carteira |
| **POST** | **`/api/v1/consult/cpf_cac`** | **API key** | **Antecedentes criminais (certidão PF)** |
| POST | `/api/v1/consult/{queryType}` | API key | Qualquer outro tipo de consulta |
| GET/PUT | `/api/v1/webhook` | API key | Configuração de webhook de saída |

---

## 9. Documentação relacionada

- **API interativa (Scalar/OpenAPI):** `/docs/api` (requer login no painel) — JSON em `/docs/api.json`
- **Webhook de saída:** [`docs/webhook.md`](./webhook.md)
