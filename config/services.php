<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'api_brasil' => [
        // Gateway oficial da APIBrasil (https://doc.apibrasil.io).
        'base_url' => env('API_BRASIL_BASE_URL', 'https://gateway.apibrasil.io/api/v2'),

        // Bearer Token da conta (área "Credenciais" no painel). Autentica a
        // conta e é o que consome os créditos.
        'token' => env('API_BRASIL_TOKEN'),

        // Token de sandbox/homologação (opcional). Usado quando o provedor
        // está com environment = sandbox.
        'sandbox_token' => env('API_BRASIL_SANDBOX_TOKEN'),

        'timeout' => (int) env('API_BRASIL_TIMEOUT', 8),

        // Endpoints de crédito (path .../credits) agregam bureaus (SPC, Serasa,
        // Boa Vista, SCR Bacen) e costumam levar 10-30s. Um timeout curto faz a
        // conexão cair antes da resposta → ProviderUnavailable → 503 indevido.
        // Aplicado automaticamente a qualquer endpoint contendo "credits".
        'credit_timeout' => (int) env('API_BRASIL_CREDIT_TIMEOUT', 45),

        // Override fino de timeout (segundos) por query_type, quando necessário.
        // queryType => segundos. Vence a heurística de crédito acima.
        'timeouts' => [
            // 'cpf_analise_credito_basic' => 60,
        ],

        // QueryType code → path no gateway. CPF/CNPJ ficam sob /dados.
        'endpoints' => [
            'cpf' => 'dados/cpf',
            'cnpj' => 'dados/cnpj',
        ],

        // A APIBrasil exige um DeviceToken POR SERVIÇO (área "Dispositivos").
        // Para o catálogo expandido agrupamos os dispositivos por categoria
        // (device_group). Cada query_type resolve o token na ordem:
        // per-tipo (admin) → grupo (admin) → per-tipo (config) → grupo (config).
        'device_tokens' => [
            // Compatibilidade com os tipos legados cpf/cnpj.
            'cpf' => env('API_BRASIL_DEVICE_TOKEN_CPF'),
            'cnpj' => env('API_BRASIL_DEVICE_TOKEN_CNPJ'),

            // Grupos do catálogo (https://doc.apibrasil.io).
            'vehicles' => env('API_BRASIL_DEVICE_TOKEN_VEHICLES'),
            'cep' => env('API_BRASIL_DEVICE_TOKEN_CEP'),
            'fipe' => env('API_BRASIL_DEVICE_TOKEN_FIPE'),
            'holidays' => env('API_BRASIL_DEVICE_TOKEN_HOLIDAYS'),
            'ddd' => env('API_BRASIL_DEVICE_TOKEN_DDD'),
            'correios' => env('API_BRASIL_DEVICE_TOKEN_CORREIOS'),
            'geolocation' => env('API_BRASIL_DEVICE_TOKEN_GEOLOCATION'),
            'geomatrix' => env('API_BRASIL_DEVICE_TOKEN_GEOMATRIX'),
            'weather' => env('API_BRASIL_DEVICE_TOKEN_WEATHER'),
            'database' => env('API_BRASIL_DEVICE_TOKEN_DATABASE'),
            'ia' => env('API_BRASIL_DEVICE_TOKEN_IA'),
            'sms' => env('API_BRASIL_DEVICE_TOKEN_SMS'),
            'chip' => env('API_BRASIL_DEVICE_TOKEN_CHIP'),
            'ura' => env('API_BRASIL_DEVICE_TOKEN_URA'),
        ],

        // Tradução do parâmetro normalizado "document" para a chave esperada
        // no body de cada serviço da APIBrasil.
        'body_keys' => [
            'cpf' => 'cpf',
            'cnpj' => 'cnpj',
        ],
    ],

    'cpfcnpj' => [
        // API CPF.CNPJ (https://www.cpfcnpj.com.br/dev). GET por créditos com
        // token no path: /{token}/{pacote}/{documento}.
        'base_url' => env('CPFCNPJ_BASE_URL', 'https://api.cpfcnpj.com.br'),

        // Token gerado no painel (API > Tokens). O token de testes
        // 5ae973d7a997af13f0aaf2bf60e65803 retorna apenas dados fictícios.
        'token' => env('CPFCNPJ_TOKEN'),

        // Token de sandbox (dev). Default: token público de testes da doc,
        // que retorna dados fictícios sem consumir créditos.
        'sandbox_token' => env('CPFCNPJ_SANDBOX_TOKEN', '5ae973d7a997af13f0aaf2bf60e65803'),

        // A doc recomenda 60s para não consumir crédito em instabilidade.
        'timeout' => (int) env('CPFCNPJ_TIMEOUT', 60),

        // QueryType code → ID do pacote (ver tabela na doc). Pode ser
        // sobrescrito por provedor/tipo na tela de Provedores (campo endpoint).
        'packages' => [
            'cpf' => env('CPFCNPJ_PACKAGE_CPF', '2'),
            'cnpj' => env('CPFCNPJ_PACKAGE_CNPJ', '6'),
        ],
    ],

    'mercado_pago' => [
        // SDK oficial (mercadopago/dx-php). Credenciais no painel do
        // Mercado Pago > Suas integrações > Credenciais.
        // O ambiente (sandbox/produção) é resolvido pelo provider "mercado_pago"
        // na tabela providers (coluna environment); o token correspondente é
        // usado pelo gateway.
        'access_token' => env('MP_ACCESS_TOKEN'),
        'sandbox_access_token' => env('MP_SANDBOX_ACCESS_TOKEN'),

        // Public key (usada no front pelo Bricks/MP.js para tokenizar cartão).
        'public_key' => env('MP_PUBLIC_KEY'),
        'sandbox_public_key' => env('MP_SANDBOX_PUBLIC_KEY'),

        // Segredo da assinatura do webhook (painel > Webhooks). Valida o
        // header x-signature das notificações.
        'webhook_secret' => env('MP_WEBHOOK_SECRET'),

        'timeout' => (int) env('MP_TIMEOUT', 20),

        // Janela (em segundos) de expiração do PIX e dias do boleto.
        'pix_expiration_minutes' => (int) env('MP_PIX_EXPIRATION_MINUTES', 30),
        'boleto_expiration_days' => (int) env('MP_BOLETO_EXPIRATION_DAYS', 3),
    ],

];
