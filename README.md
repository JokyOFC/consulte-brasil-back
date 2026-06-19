# Consulte Brasil — Backend

Plataforma de consulta de dados oficiais do Brasil via API. Integra consultas de CPF, CNPJ e outros serviços com sistema de créditos, chaves de API e painéis administrativo e do cliente.

## Stack

- **PHP 8.3** + **Laravel 13**
- **React** + **Inertia.js** + **TypeScript**
- **Vite** + **Tailwind CSS**
- **SQLite** (desenvolvimento) / banco relacional em produção

## Módulos

A aplicação segue arquitetura modular em `src/Modules/`:

| Módulo | Responsabilidade |
|--------|------------------|
| **Identity** | Contas, usuários, autenticação e chaves de API |
| **Billing** | Planos, assinaturas, carteira de créditos e pagamentos (Mercado Pago) |
| **Consultation** | Execução de consultas e integração com provedores de dados |
| **Provider** | Gestão de provedores externos (API Brasil, CPF.CNPJ, etc.) |
| **Audit** | Registro de eventos e auditoria |

## Pré-requisitos

- PHP 8.3+ com extensões: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) 20+

## Setup rápido

```bash
composer setup
```

Esse comando instala dependências PHP, cria o `.env` a partir do exemplo, gera a chave da aplicação, roda as migrations e compila os assets do frontend.

## Desenvolvimento

```bash
composer dev
```

Sobe em paralelo o servidor Laravel, a fila de jobs e o Vite (hot reload).

Acesse: [http://localhost:8000](http://localhost:8000)

## Testes e qualidade

```bash
composer test        # PHPUnit + Pint
composer ci:check    # ESLint, Prettier, TypeScript e testes
```

## Variáveis de ambiente

Copie `.env.example` para `.env` e configure conforme o ambiente:

- **MAIL_*** — envio de e-mails transacionais (`MAIL_MAILER=log` em desenvolvimento grava no log)
- **APP_*** — aplicação Laravel
- **API_BRASIL_*** — provedor [API Brasil](https://doc.apibrasil.io)
- **CPFCNPJ_*** — provedor [CPF.CNPJ](https://www.cpfcnpj.com.br/dev)
- **MP_*** — [Mercado Pago](https://www.mercadopago.com.br/developers)

## Estrutura do projeto

```
app/                  # Kernel Laravel e providers globais
src/Modules/          # Domínio modular (DDD)
resources/js/         # Frontend React/Inertia
routes/               # Rotas web, API e admin
tests/                # Testes de feature e unidade
database/             # Migrations e seeders
```

## Documentação da API

Com a aplicação rodando, a documentação interativa (Scalar) fica disponível em `/docs/api`.

- [Webhook de consultas](docs/webhook.md) — notificações de saída, payload, assinatura HMAC e configuração

## Licença

MIT
