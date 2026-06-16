<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache de resultados de consulta
    |--------------------------------------------------------------------------
    |
    | TTL padrão (segundos) quando o tipo não define cache_ttl_seconds no banco.
    | Use 0 para desabilitar globalmente (tipos com TTL próprio > 0 ainda cacheiam).
    |
    | cache_ttl_by_query_type: fallback quando a coluna cache_ttl_seconds é null.
    |
    */
    'default_cache_ttl_seconds' => (int) env('CONSULTATION_CACHE_TTL_SECONDS', 86400),

    'cache_ttl_by_query_type' => [
        'cpf' => 604800,   // 7 dias — cadastro muda com pouca frequência
        'cnpj' => 604800,
    ],

];
