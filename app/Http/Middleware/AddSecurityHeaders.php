<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adiciona headers de defesa em profundidade (CSP, HSTS, anti-clickjacking, etc.).
 */
final class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if (app()->isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        if ($request->is('docs/*') || app()->environment('local')) {
            return $response;
        }

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        if (app()->environment('testing')) {
            return $this->localDevelopmentPolicy();
        }

        return $this->productionPolicy();
    }

    private function localDevelopmentPolicy(): string
    {
        $vite = 'http://localhost:* http://127.0.0.1:* http://[::1]:*';
        $viteWs = 'ws://localhost:* ws://127.0.0.1:* ws://[::1]:*';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$vite} https://sdk.mercadopago.com https://http2.mlstatic.com",
            "style-src 'self' 'unsafe-inline' {$vite}",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https: {$vite}",
            "connect-src 'self' {$viteWs} {$vite} https://api.mercadopago.com https://*.mercadopago.com",
            "frame-src https://*.mercadopago.com https://www.mercadopago.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    private function productionPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://sdk.mercadopago.com https://http2.mlstatic.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https:",
            "connect-src 'self' https://api.mercadopago.com https://*.mercadopago.com",
            "frame-src https://*.mercadopago.com https://www.mercadopago.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
