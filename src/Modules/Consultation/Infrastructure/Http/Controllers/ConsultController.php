<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Src\Modules\Consultation\Application\DTO\ExecuteConsultationInput;
use Src\Modules\Consultation\Application\UseCase\ExecuteConsultation;
use Src\Modules\Consultation\Infrastructure\Http\Requests\ExecuteConsultationRequest;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

/**
 * Endpoint principal da API pública: executa consultas de dados.
 */
#[Group('Consultas', description: 'Execução de consultas de dados oficiais.', weight: 2)]
final class ConsultController
{
    #[Endpoint(
        title: 'Executar consulta',
        description: <<<'DESC'
Executa uma consulta do tipo informado na URL (`queryType`). O corpo deve conter um objeto `params`
com os campos exigidos pelo tipo.

**Entrada (`params`):**
- Por documento (maioria dos tipos): `{ "document": "11144477735" }` — CPF (11 dígitos) ou CNPJ
  (14), com ou sem máscara. CNPJ alfanumérico é aceito.
- Busca de empresa por nome (`cnpj_razao`): `{ "razao_social": "GOOGLE BRASIL" }`.

**Resposta:** inclui o provedor utilizado, o valor debitado em reais (`amount_charged`, em centavos, e
`amount_charged_formatted`) e os dados. Para `cpf`/`cnpj` os campos vêm normalizados; nos demais tipos,
os campos do provedor são repassados como vieram. O payload bruto completo fica sempre em `data.raw`.
O cliente **só é cobrado em caso de sucesso** — falhas estornam o valor automaticamente.

### Tipos de consulta disponíveis

#### Pessoa Física (CPF)
| `queryType` | Retorno | Preço (R$) |
| --- | --- | --- |
| `cpf_nome` | Nome completo | 0,17 |
| `cpf_nascimento` | Nome + data de nascimento | 0,25 |
| `cpf` | Nome, nascimento, nome da mãe e gênero | 0,29 |
| `cpf_situacao` | Situação cadastral (RF) + comprovante PDF | 0,41 |
| `cpf_completo` | Dados completos da Receita Federal | 0,53 |
| `cpf_endereco` | Nome, nascimento, gênero e endereço completo | 1,35 |
| `cpf_ppe` | Pessoa Politicamente Exposta (PPE/PEP) e relacionados | 0,23 |
| `cpf_empresas` | CNPJs em que o titular é sócio | 0,23 |
| `cpf_endereco_situacao` | Endereço + situação cadastral | 1,57 |
| `cpf_contatos` | E-mails, telefones e WhatsApp | 0,27 |
| `cpf_family` | Vínculos familiares | 1,13 |
| `cpf_programas_sociais` | Programas sociais do titular | 0,17 |
| `cpf_antecedentes` | Mandados (BNMP) e lista INTERPOL | 1,46 |
| `cpf_situacao_simples` | Nome, nascimento e situação cadastral | 0,37 |
| `cpf_cac` | Antecedentes criminais (CAC/SINIC) em PDF | 0,27 |
| `cpf_cns` | Cartão Nacional de Saúde (CNS) | 0,24 |
| `cpf_risco` | Nível e score de risco | 0,50 |

#### Pessoa Jurídica (CNPJ)
| `queryType` | Retorno | Preço (R$) |
| --- | --- | --- |
| `cnpj_razao` | Razão social (por CNPJ ou busca por `razao_social`) | 0,15 |
| `cnpj_endereco` | Razão, fantasia e endereço completo | 0,27 |
| `cnpj_contato` | Endereço, telefones, e-mail e situação | 0,36 |
| `cnpj` | Dados completos da Receita Federal + cartão CNPJ (PDF) | 0,51 |
| `cnpj_simples` | Simples Nacional, SIMEI e Suframa | 0,34 |
| `cnpj_ie` | Inscrições estaduais | 0,17 |
| `cnpj_contatos` | Contatos dos sócios (e-mail/telefone/WhatsApp) | 0,30 |
| `cnpj_qsa` | Quadro societário com percentual de cada sócio | 2,25 |
| `cnpj_risco` | Nível e score de risco | 0,50 |

#### Outros
| `queryType` | Retorno | Preço (R$) |
| --- | --- | --- |
| `nfe` | Notas fiscais (NFe) de PF e PJ em tempo real | 0,04 |

---

### Catálogo APIBrasil (gateway oficial)

Rotas do gateway [doc.apibrasil.io](https://doc.apibrasil.io) (exceto WhatsApp, PIX e Notas
Fiscais). Todas usam o prefixo `ab_`. Quando o provedor `api_brasil` estiver em **sandbox**,
as consultas de CPF/CNPJ/Veículos retornam dados fictícios (`homolog`) sem consumir crédito.

#### APIBrasil — CPF (`document` = CPF)
| `queryType` | Retorno | Preço (R$) |
| --- | --- | --- |
| `ab_cpf_cadastrais` | Dados cadastrais e situação | 0,50 |
| `ab_cpf_receita_federal` | Situação cadastral na Receita Federal | 0,50 |
| `ab_cpf_spc_boavista` | Restrições/score SPC Boa Vista | 1,50 |
| `ab_cpf_spc_serasa` | Restrições/score Serasa (`document` → `documento`) | 1,80 |
| `ab_cpf_quod` | Restrições Quod (PF) | 1,50 |
| `ab_cpf_acerta` | Acerta Essencial | 1,20 |
| `ab_cpf_acerta_positivo` | Acerta Essencial + cadastro positivo | 1,50 |
| `ab_cpf_serasa_premium` | Serasa Premium (PF) | 2,50 |
| `ab_cpf_serasa_score` | Serasa Score (PF) | 1,80 |
| `ab_cpf_scr_bacen` | SCR Bacen + score | 2,50 |
| `ab_cpf_limite` | Crédito — define limite | 1,50 |
| `ab_cpf_limite_positivo` | Crédito — limite com positivo | 1,80 |
| `ab_cpf_cnh` | Dados da CNH por CPF | 1,50 |
| `ab_cpf_cnh_criminals` | CNH + antecedentes criminais | 2,00 |
| `ab_cpf_processos` | Ações e processos judiciais | 2,00 |

#### APIBrasil — CNPJ (`document` = CNPJ)
| `queryType` | Retorno | Preço (R$) |
| --- | --- | --- |
| `ab_cnpj` | Dados cadastrais (Receita Federal) | 0,51 |
| `ab_cnpj_socios` | Lista de sócios (QSA) | 0,40 |
| `ab_cnpj_capital_social` | Empresas por capital social | 0,40 |
| `ab_cnpj_cnae` | Empresas por CNAE/UF | 0,40 |
| `ab_cnpj_uf` | Empresas por UF | 0,40 |
| `ab_cnpj_cep` | Empresas por CEP | 0,40 |
| `ab_cnpj_lista_cnaes` | Tabela de CNAEs | 0,10 |
| `ab_cnpj_quod` | Restrições Quod (PJ) | 1,80 |
| `ab_cnpj_serasa_premium` | Serasa Premium (PJ) | 2,50 |

#### APIBrasil — Veículos (`document` = placa, salvo indicado)
| `queryType` | Retorno | Preço (R$) |
| --- | --- | --- |
| `ab_veiculos_dados` | Dados do veículo por placa | 1,00 |
| `ab_veiculos_fipe` | Valor FIPE por placa | 0,80 |
| `ab_veiculos_agregados` | Dados agregados (básica) | 1,00 |
| `ab_veiculos_agregados_propria` | Dados agregados (própria/extra) | 1,50 |
| `ab_veiculos_dados_v1` | Placa dados V1 | 1,00 |
| `ab_veiculos_crlv` | CRLV-e | 2,00 |
| `ab_veiculos_busca_documento` | Veículos por documento (`document` = CPF/CNPJ) | 2,00 |
| `ab_veiculos_checklist` | Check list veicular | 1,00 |
| `ab_veiculos_ficha_tecnica` | Ficha técnica | 1,00 |
| `ab_veiculos_vip_car` | Relatório Vip Car | 2,00 |
| `ab_veiculos_csv_completa` | Renainf+Renajud+Bin+proprietário | 3,00 |
| `ab_veiculos_proprietario` | Proprietário atual | 2,50 |
| `ab_veiculos_nacional` | Base nacional | 1,50 |
| `ab_veiculos_estadual` | Base estadual | 1,50 |
| `ab_veiculos_telefones` | Telefones + endereço por placa | 2,00 |
| `ab_veiculos_leilao_score` | Leilão + score | 2,50 |
| `ab_veiculos_leilao` | Histórico de leilão | 1,50 |
| `ab_veiculos_debitos_v3` | Multas e boletos (débitos V3) | 2,00 |
| `ab_veiculos_debitos` | Débitos veiculares | 1,50 |
| `ab_veiculos_multas` | Multas | 1,50 |
| `ab_veiculos_multas_prf` | Multas PRF | 1,50 |
| `ab_veiculos_recall` | Recalls pendentes | 0,80 |
| `ab_veiculos_roubo_furto` | Roubo/furto | 0,80 |
| `ab_veiculos_gravame` | Gravame/financiamento | 1,50 |
| `ab_veiculos_renajud` | Restrições judiciais (Renajud) | 2,00 |
| `ab_veiculos_renainf` | Multas Renainf | 2,00 |
| `ab_veiculos_fipe_placa` | FIPE via base veicular | 0,50 |

#### APIBrasil — Localização e utilidades
| `queryType` | Entrada | Retorno | Preço (R$) |
| --- | --- | --- | --- |
| `ab_cep` | `document` = CEP | Endereço + IBGE | 0,05 |
| `ab_cep_cidades_ddd` | `document` = DDD | Cidades do DDD | 0,05 |
| `ab_cep_bairros` | `document` = cidade | Bairros da cidade | 0,05 |
| `ab_cep_cidades` | `document` = UF | Cidades da UF | 0,05 |
| `ab_cep_estados` | — | Lista de estados | 0,05 |
| `ab_ddd` | `document` = DDD | Dados do DDD (Anatel) | 0,02 |
| `ab_ddd_lista` | — | Lista de DDDs | 0,02 |
| `ab_rastreio` | `document` = código | Rastreamento de encomenda | 0,10 |
| `ab_geocode` | `document` = endereço | Coordenadas do endereço | 0,05 |
| `ab_geocode_reverse` | `lat`, `lng` | Endereço por coordenadas | 0,05 |
| `ab_distancia` | `origin`, `destination` | Distância/rota entre CEPs | 0,05 |
| `ab_clima_cidade` | `document` = cidade | Previsão do tempo | 0,05 |
| `ab_clima_coordenadas` | `lat`, `lon` | Previsão por coordenadas | 0,05 |
| `ab_ip` | `document` = IP | Geolocalização de IP | 0,05 |

#### APIBrasil — Tabela FIPE
| `queryType` | Retorno | Preço (R$) |
| --- | --- | --- |
| `ab_fipe_tabela_referencia` | Tabelas de referência | 0,05 |
| `ab_fipe_marcas` | Marcas por tipo | 0,05 |
| `ab_fipe_modelos` | Modelos da marca | 0,05 |
| `ab_fipe_ano_modelo` | Anos do modelo | 0,05 |
| `ab_fipe_modelos_ano` | Modelos por ano | 0,05 |
| `ab_fipe_valor` | Valor com todos os parâmetros | 0,10 |

#### APIBrasil — Feriados
| `queryType` | Entrada | Retorno | Preço (R$) |
| --- | --- | --- | --- |
| `ab_feriados` | `ano`, `uf`, `tipo` | Feriados nacionais/estaduais/municipais | 0,02 |
| `ab_feriados_hoje` | — | Indica se hoje é feriado | 0,02 |

#### APIBrasil — Comunicação e IA
| `queryType` | Entrada | Retorno | Preço (R$) |
| --- | --- | --- | --- |
| `ab_sms` | `number`, `message` | Envio de SMS | 0,10 |
| `ab_chip_comprar` | `service`, `operator` | Compra de chip virtual | 0,50 |
| `ab_chip_sms` | `id` | SMS recebido no chip | 0,10 |
| `ab_chip_operadoras` | — | Operadoras disponíveis | 0,02 |
| `ab_chip_servicos` | — | Serviços disponíveis | 0,02 |
| `ab_ura_discador` | `number` | Dispara ligação (URA) | 0,30 |
| `ab_ura_status` | `id` | Status da ligação | 0,02 |
| `ab_ia_llama` | `messages` | Chat (Llama) | 0,05 |
| `ab_ia_ollama` | `messages` | Chat (Ollama/Deepseek) | 0,05 |
| `ab_ia_tts` | `text` | Texto para fala (TTS) | 0,05 |

> Os valores indicados são referência (custo do provedor); o preço de venda ao cliente é o
> custo acrescido da margem da plataforma e pode ser ajustado por provedor/tipo na administração.
> A disponibilidade de cada tipo depende do provedor estar habilitado para ele.
> Nos tipos `ab_*` sem mapeamento normalizado, os campos do provedor são repassados como
> vieram e o payload bruto completo fica em `data.raw`.
DESC,
    )]
    #[PathParameter(
        'queryType',
        description: 'Código do tipo de consulta. Ex.: cpf, cnpj, cpf_completo, cnpj_qsa, cnpj_razao, nfe. Veja a tabela completa na descrição do endpoint.',
        required: true,
        example: 'cpf',
    )]
    #[BodyParameter(
        'params',
        description: 'Parâmetros da consulta. Para CPF/CNPJ, informe `document` com o número sem formatação.',
        required: true,
        type: 'object',
        example: ['document' => '11144477735'],
    )]
    #[Response(
        status: 200,
        description: 'Consulta realizada com sucesso',
        examples: [[
            'data' => [
                'consultation_id' => '01932a1b-8c4d-7000-8000-000000000001',
                'provider' => 'api_brasil',
                'amount_charged' => 29,
                'amount_charged_formatted' => 'R$ 0,29',
                'credits_charged' => 29,
                'data' => [
                    'name' => 'JOAO DA SILVA',
                    'status' => 'REGULAR',
                    'raw' => [],
                ],
            ],
        ]],
    )]
    #[Response(status: 402, description: 'Saldo insuficiente na carteira')]
    #[Response(status: 422, description: 'Tipo de consulta inválido ou parâmetros incorretos')]
    #[Response(status: 503, description: 'Nenhum provedor disponível para o tipo solicitado')]
    public function __invoke(
        string $queryType,
        ExecuteConsultationRequest $request,
        ExecuteConsultation $execute,
    ): JsonResponse {
        /** @var AccountModel $account */
        $account = $request->user();
        $apiKeyId = $request->attributes->get('consulte.api_key_id');

        $output = $execute->handle(new ExecuteConsultationInput(
            accountId: $account->id,
            apiKeyId: is_string($apiKeyId) ? $apiKeyId : null,
            queryType: $queryType,
            params: (array) $request->input('params'),
        ));

        return response()->json([
            'data' => [
                'consultation_id' => $output->consultationId,
                'provider' => $output->providerIdentifier,
                // Valor cobrado em centavos de BRL (e versão formatada).
                'amount_charged' => $output->creditsCharged,
                'amount_charged_formatted' => 'R$ '.number_format($output->creditsCharged / 100, 2, ',', '.'),
                // Mantido por compatibilidade: mesmo valor em centavos.
                'credits_charged' => $output->creditsCharged,
                'from_cache' => $output->fromCache,
                'data' => $output->data,
            ],
        ]);
    }
}
