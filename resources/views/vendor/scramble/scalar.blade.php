<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="light dark">
    <title>{{ $config->get('ui.title') ?? 'Consulte Brasil — Documentação da API' }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="description" content="Documentação da API Consulte Brasil — consultas de CPF, CNPJ e mais via REST.">
    <style>
        :root {
            --brand-green: #009c3b;
            --docs-header-height: 3.5rem;
            --scalar-custom-header-height: 3.5rem;
            --header-bg: rgba(255, 255, 255, 0.97);
            --header-border: #e5e7eb;
            --header-text: #111827;
            --header-link: #4b5563;
            --header-link-hover-bg: #f3f4f6;
        }

        /* Scalar aplica dark-mode/light-mode no <body> */
        body.dark-mode {
            --header-bg: rgba(32, 32, 32, 0.97);
            --header-border: rgba(255, 255, 255, 0.1);
            --header-text: rgba(255, 255, 255, 0.9);
            --header-link: rgba(255, 255, 255, 0.55);
            --header-link-hover-bg: rgba(255, 255, 255, 0.08);
            color-scheme: dark;
        }

        body.light-mode {
            color-scheme: light;
        }

        /* Fallback: tema no root Vue dentro de #app */
        html.docs-dark {
            --header-bg: rgba(32, 32, 32, 0.97);
            --header-border: rgba(255, 255, 255, 0.1);
            --header-text: rgba(255, 255, 255, 0.9);
            --header-link: rgba(255, 255, 255, 0.55);
            --header-link-hover-bg: rgba(255, 255, 255, 0.08);
            color-scheme: dark;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        }

        .docs-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            display: flex;
            height: var(--docs-header-height);
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
            backdrop-filter: blur(8px);
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
            background-color: var(--header-bg);
            color: var(--header-text);
            border-bottom: 1px solid var(--header-border);
            box-shadow: inset 0 -1px 0 var(--header-border);
        }

        .docs-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: inherit;
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
            color: var(--header-link);
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .docs-nav a:hover {
            background: var(--header-link-hover-bg);
            color: var(--header-text);
        }

        .docs-nav a.primary {
            background: var(--brand-green);
            color: #fff;
        }

        .docs-nav a.primary:hover {
            background: #008633;
            color: #fff;
        }

        @media (max-width: 640px) {
            .docs-nav a:not(.primary) {
                display: none;
            }
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

<div id="app"></div>

<script src="{{ $config->renderer()->get('cdn', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference') }}"></script>
<script>
    const CSRF_TOKEN_COOKIE_KEY = 'XSRF-TOKEN';
    const CSRF_TOKEN_HEADER_KEY = 'X-XSRF-TOKEN';
    const DOCS_HEADER_HEIGHT = '3.5rem';

    const getCookieValue = (key) => {
        const cookie = document.cookie.split(';').find((c) => c.trim().startsWith(key));
        return cookie?.split('=')[1];
    };

    document.documentElement.style.setProperty('--scalar-custom-header-height', DOCS_HEADER_HEIGHT);
    document.documentElement.style.setProperty('--scalar-y-offset', DOCS_HEADER_HEIGHT);

    Scalar.createApiReference('#app', {
        content: @json($spec),
        ...@json($config->renderer()->all(except: ['cdn', 'credentials', 'view'])),
        metaData: {
            title: @json($config->get('ui.title')),
        },
        authentication: {
            preferredSecurityScheme: 'http',
        },
        onBeforeRequest: ({ requestBuilder }) => {
            const token = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (token) {
                requestBuilder.headers.set(CSRF_TOKEN_HEADER_KEY, decodeURIComponent(token));
            }
        },
        customFetch: (input, init) => window.fetch(input, {
            ...init,
            credentials: @json($config->renderer()->get('credentials', 'include')),
        }),
        customCss: `
            :root {
                --scalar-y-offset: ${DOCS_HEADER_HEIGHT};
                --scalar-custom-header-height: ${DOCS_HEADER_HEIGHT};
            }

            .scalar-app .sidebar,
            .scalar-app .t-doc__sidebar {
                top: ${DOCS_HEADER_HEIGHT} !important;
                height: calc(100dvh - ${DOCS_HEADER_HEIGHT}) !important;
                max-height: calc(100dvh - ${DOCS_HEADER_HEIGHT}) !important;
            }
        `,
    });

    const watchedThemeNodes = new Set();

    const findThemeNodes = () => {
        const nodes = [document.body];
        const vueRoot = document.getElementById('app')?.firstElementChild;

        if (!vueRoot) {
            return nodes;
        }

        nodes.push(vueRoot);

        if (vueRoot.classList.contains('scalar-app')) {
            return nodes;
        }

        const scalarApp = vueRoot.querySelector('.references-layout, .scalar-app.references-layout, .scalar-app');

        if (scalarApp) {
            nodes.push(scalarApp);
        }

        return nodes;
    };

    const isGlobalDarkTheme = () => {
        for (const node of findThemeNodes()) {
            if (node.classList.contains('dark-mode')) {
                return true;
            }

            if (node.classList.contains('light-mode')) {
                return false;
            }
        }

        return false;
    };

    const syncHeaderTheme = () => {
        const isDark = isGlobalDarkTheme();

        document.documentElement.classList.toggle('docs-dark', isDark);
        document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
    };

    const watchThemeNode = (node) => {
        if (!node || watchedThemeNodes.has(node)) {
            return;
        }

        watchedThemeNodes.add(node);

        const observer = new MutationObserver(syncHeaderTheme);
        observer.observe(node, {
            attributes: true,
            attributeFilter: ['class'],
        });
    };

    const setupThemeSync = () => {
        findThemeNodes().forEach(watchThemeNode);
        syncHeaderTheme();
    };

    setupThemeSync();

    const mountObserver = new MutationObserver(setupThemeSync);
    mountObserver.observe(document.getElementById('app'), {
        childList: true,
        subtree: false,
    });

    setTimeout(setupThemeSync, 500);
</script>
</body>
</html>
