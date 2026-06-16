<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\HttpSecurityScheme;
use Dedoc\Scramble\Support\Generator\Tag;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewApiDocs', fn (?User $user = null) => $user !== null && $user->role === 'admin');

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $openApi->info->setDescription($this->introduction());

            foreach ($openApi->components->securitySchemes as $scheme) {
                if ($scheme instanceof HttpSecurityScheme && $scheme->scheme === 'bearer') {
                    $scheme->setDescription(
                        'Chave de API gerada no painel (formato `cb_live_...` ou `cb_test_...`). '
                        .'Envie no header: `Authorization: Bearer {sua_chave}`.'
                    );
                }
            }

            $openApi->tags = [
                new Tag('Sistema', 'Endpoints de saúde e metadados da API.'),
                new Tag('Planos', 'Catálogo público de planos de assinatura.'),
                new Tag('Conta', 'Informações da conta autenticada pela chave de API.'),
                new Tag('Carteira', 'Saldo em reais (R$) da conta autenticada.'),
                new Tag('Consultas', 'Execução de consultas de dados (CPF, CNPJ, etc.).'),
            ];
        });
    }

    private function introduction(): string
    {
        return <<<'MD'
## Visão geral

A API do **Consulte Brasil** permite integrar consultas de dados oficiais do Brasil ao seu sistema. Cada consulta consome saldo da sua carteira conforme o tipo e o provedor utilizado.

## Autenticação

1. Crie uma conta e gere uma chave em **Painel → Minhas chaves**.
2. Envie a chave em todas as requisições autenticadas:

```
Authorization: Bearer cb_live_sua_chave_aqui
```

## Fluxo básico

1. `GET /ping` — verifica se a API está no ar.
2. `GET /me` — confirma que a chave é válida e retorna dados da conta.
3. `POST /consult/{tipo}` — executa a consulta desejada.

## Exemplo — consulta CPF

```bash
curl -X POST https://seu-dominio.com/api/v1/consult/cpf \
  -H "Authorization: Bearer cb_live_..." \
  -H "Content-Type: application/json" \
  -d '{"params": {"document": "11144477735"}}'
```

### Resposta de sucesso

```json
{
  "data": {
    "consultation_id": "uuid",
    "provider": "api_brasil",
    "credits_charged": 1,
    "data": {
      "name": "JOAO DA SILVA",
      "status": "REGULAR",
      "raw": {}
    }
  }
}
```

## Tipos de consulta (`queryType`)

| Tipo | Parâmetros em `params` | Descrição |
|------|------------------------|-----------|
| `cpf` | `document` (CPF, só números) | Dados cadastrais de pessoa física |
| `cnpj` | `document` (CNPJ, só números) | Dados cadastrais de empresa |
| `credito` | conforme provedor | Consultas de crédito |

## Erros comuns

| HTTP | Significado |
|------|-------------|
| `401` | Chave ausente, inválida ou revogada |
| `402` | Saldo insuficiente na carteira |
| `422` | Parâmetros inválidos ou tipo de consulta inexistente |
| `429` | Limite de requisições excedido (60/min por chave) |
| `503` | Provedores indisponíveis no momento |

## Limites

- **Rate limit:** 60 requisições por minuto por chave autenticada.
- **Saldo:** debitado apenas em consultas concluídas com sucesso.
- **Cache:** consultas idênticas podem retornar resposta recente com `from_cache: true`. O TTL é configurável por tipo (`query_types.cache_ttl_seconds`); expira automaticamente e é invalidado ao alterar provedor ou TTL do tipo.
MD;
    }
}
