<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Documentation;

use Src\Modules\Consultation\Application\Service\ClientConsultationCatalog;

/**
 * Monta a documentação pública dos tipos de consulta disponíveis na API.
 * Lista apenas rotas ativas/habilitadas, sem expor nomes de provedores.
 */
final class ConsultQueryTypeDocumentation
{
    public function __construct(
        private ClientConsultationCatalog $catalog,
    ) {}

    public function endpointDescription(): string
    {
        return <<<'DESC'
Executa uma consulta do tipo informado na URL (`queryType`). O corpo deve conter um objeto `params`
com os campos exigidos pelo tipo.

**Entrada (`params`):**
- Por documento (maioria dos tipos): `{ "document": "11144477735" }` — CPF (11 dígitos) ou CNPJ
  (14), com ou sem máscara. CNPJ alfanumérico é aceito.
- Busca de empresa por nome (`cnpj_razao`): `{ "razao_social": "GOOGLE BRASIL" }`.
- Tipos com parâmetros extras (ex.: coordenadas, mensagens): consulte a descrição do tipo na tabela.

**Resposta:** inclui o valor debitado em reais (`amount_charged`, em centavos, e
`amount_charged_formatted`) e os dados normalizados. O payload bruto completo fica sempre em `data.raw`.
O cliente **só é cobrado em caso de sucesso** — falhas estornam o valor automaticamente.

### Tipos de consulta disponíveis

DESC
            .$this->catalogTables()
            .<<<'DESC'


> Os preços indicados são referência em reais (R$). O valor cobrado pode ser ajustado na administração
> da plataforma. Apenas tipos **ativos** e com rota habilitada aparecem nesta lista.
DESC;
    }

    private function catalogTables(): string
    {
        $grouped = $this->catalog->listGrouped();

        if ($grouped === []) {
            return "\n_Nenhum tipo de consulta disponível no momento._\n";
        }

        $markdown = '';

        foreach (ClientConsultationCatalog::GROUP_ORDER as $title) {
            $items = $grouped[$title] ?? [];

            if ($items === []) {
                continue;
            }

            $markdown .= "\n#### {$title}\n";
            $markdown .= "| `queryType` | Descrição | Preço (R$) |\n";
            $markdown .= "| --- | --- | --- |\n";

            foreach ($items as $type) {
                $price = number_format(((int) $type['price_cents']) / 100, 2, ',', '.');
                $markdown .= "| `{$type['code']}` | {$type['name']} | {$price} |\n";
            }
        }

        return $markdown;
    }
}
