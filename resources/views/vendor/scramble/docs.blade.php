<!doctype html>
<html lang="pt-BR" data-theme="{{ $config->renderer()->get('theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->renderer()->get('theme', 'light') }}">
    <title>{{ $config->get('ui.title') ?? 'Consulte Brasil — Documentação da API' }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="description" content="Documentação da API Consulte Brasil — consultas de CPF, CNPJ e mais via REST.">

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">

    <script>
        const originalFetch = window.fetch;

        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = 'XSRF-TOKEN';
            const CSRF_TOKEN_HEADER_KEY = 'X-XSRF-TOKEN';
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((c) => c.trim().startsWith(key));
                return cookie?.split('=')[1];
            };

            const updateFetchHeaders = (headers, headerKey, headerValue) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };

            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                const { headers = new Headers() } = options || {};
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
                return originalFetch(url, { ...options, headers });
            }

            return originalFetch(url, options);
        };
    </script>

    <style>
        :root {
            --brand-green: #009c3b;
            --docs-header-height: 3.5rem;
        }

        html, body {
            margin: 0;
            height: 100%;
            overflow: hidden;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        }

        body {
            background-color: var(--color-canvas);
        }

        .docs-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            height: var(--docs-header-height);
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            background: rgba(255, 255, 255, 0.97);
            padding: 0 1.25rem;
            backdrop-filter: blur(8px);
        }

        .docs-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #111827;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .docs-brand img {
            height: 2rem;
            width: auto;
        }

        .docs-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .docs-nav a {
            border-radius: 0.5rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4b5563;
            text-decoration: none;
        }

        .docs-nav a:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .docs-nav a.primary {
            background: var(--brand-green);
            color: #fff;
        }

        #docs {
            display: block;
            position: fixed;
            top: var(--docs-header-height);
            left: 0;
            right: 0;
            bottom: 0;
            height: calc(100vh - var(--docs-header-height));
            height: calc(100dvh - var(--docs-header-height));
        }

        [data-theme="dark"] .token.property { color: rgb(128, 203, 196) !important; }
        [data-theme="dark"] .token.operator { color: rgb(255, 123, 114) !important; }
        [data-theme="dark"] .token.number { color: rgb(247, 140, 108) !important; }
        [data-theme="dark"] .token.string { color: rgb(165, 214, 255) !important; }
        [data-theme="dark"] .token.boolean { color: rgb(121, 192, 255) !important; }
        [data-theme="dark"] .token.punctuation { color: #dbdbdb !important; }

        @media (max-width: 640px) {
            .docs-nav a:not(.primary) { display: none; }
        }
    </style>
</head>
<body>
<header class="docs-header">
    <a href="/" class="docs-brand">
        <img src="/images/consulte-brasil-logo-closer.png" alt="Consulte Brasil">
    </a>
    <nav class="docs-nav">
        <a href="/dashboard">Painel</a>
        <a href="/client/api-keys">Minhas chaves</a>
        <a href="/docs/api.json" target="_blank" rel="noopener">OpenAPI JSON</a>
        <a href="/register" class="primary">Criar conta</a>
    </nav>
</header>

<elements-api
    id="docs"
    @foreach($config->renderer()->all(except: ['theme', 'view']) as $key => $value)
        @continue(! $value)
        {{ $key }}="{{ $value === true ? 'true' : ($value === false ? 'false' : $value) }}"
    @endforeach
/>

<script>
    (async () => {
        const docs = document.getElementById('docs');
        docs.apiDescriptionDocument = @json($spec);
    })();
</script>
</body>
</html>
