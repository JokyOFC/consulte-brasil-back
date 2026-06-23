<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Máscara de documentos no log de auditoria
    |--------------------------------------------------------------------------
    |
    | Por padrão (true), CPF/CNPJ enviados nas requisições são mascarados em
    | request_logs (LGPD). Defina AUDIT_MASK_DOCUMENTS=false TEMPORARIAMENTE
    | para inspecionar o valor real durante diagnóstico — e volte para true
    | depois. Credenciais e dados de cartão são SEMPRE mascarados.
    |
    */
    'mask_documents' => (bool) env('AUDIT_MASK_DOCUMENTS', true),

];
