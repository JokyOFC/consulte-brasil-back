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

    /*
    |--------------------------------------------------------------------------
    | OCR de fallback para PDFs sem camada de texto
    |--------------------------------------------------------------------------
    |
    | Algumas certidões (ex.: antecedentes criminais da PF) chegam como PDF
    | escaneado/imagem, sem texto extraível. Quando o Smalot não acha texto,
    | o sistema tenta OCR LOCAL (Ghostscript rasteriza → Tesseract reconhece),
    | mantendo dados sensíveis no servidor. Se os binários não existirem, o
    | OCR vira no-op e a consulta segue normalmente.
    |
    | Requisitos no servidor: ghostscript (gs/gswin64c) e tesseract-ocr com o
    | idioma português (traineddata `por`).
    |
    */
    'ocr' => [
        'enabled' => (bool) env('CONSULTATION_OCR_ENABLED', true),
        'language' => env('CONSULTATION_OCR_LANG', 'por'),
        'dpi' => (int) env('CONSULTATION_OCR_DPI', 300),
        'max_pages' => (int) env('CONSULTATION_OCR_MAX_PAGES', 3),
        'timeout_seconds' => (int) env('CONSULTATION_OCR_TIMEOUT', 60),
        // Caminhos explícitos (opcional). Se null, são descobertos no PATH.
        'ghostscript_bin' => env('CONSULTATION_OCR_GHOSTSCRIPT_BIN'),
        'tesseract_bin' => env('CONSULTATION_OCR_TESSERACT_BIN'),
    ],

];
