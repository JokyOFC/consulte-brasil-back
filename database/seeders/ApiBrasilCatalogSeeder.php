<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Pricing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Catálogo da APIBrasil Gateway V2 (https://doc.apibrasil.io).
 *
 * Cada rota vira um query_type da nossa API + uma capability do provedor
 * "api_brasil". Os tipos usam o prefixo "ab_" para não colidir com os tipos
 * legados (cpf/cnpj) nem com o provedor cpfcnpj.
 *
 * O config de cada capability carrega:
 *  - endpoint:     path no gateway (relativo à base_url)
 *  - method:       GET ou POST (default POST)
 *  - body_key:     chave para a qual "document" é traduzido (opcional)
 *  - body:         body estático mesclado à requisição (ex.: {"tipo": "..."})
 *  - device_group: agrupa serviços que compartilham o mesmo DeviceToken
 *  - homolog:      true → em sandbox injeta homolog=true (dados fictícios)
 *
 * Excluídos por decisão de escopo: WhatsApp; PIX (coberto pelo Mercado Pago);
 * Notas Fiscais (endpoints placeholder/inválidos na coleção oficial).
 *
 * Idempotente: não duplica query_type nem capability já existentes.
 */
final class ApiBrasilCatalogSeeder extends Seeder
{
    /**
     * [code, nome, descrição, preço BRL, config].
     *
     * @var list<array{0:string,1:string,2:string,3:float,4:array<string,mixed>}>
     */
    private const CATALOG = [
        // ===================== CONSULTAS CPF =====================
        ['ab_cpf_cadastrais', 'CPF — Dados cadastrais', 'Nome, nascimento e situação cadastral do CPF.', 0.34,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'dados-cadastrais', 'homolog' => true]],
        ['ab_cpf_receita_federal', 'CPF — Receita Federal', 'Situação cadastral do CPF na Receita Federal.', 0.34,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'receita-federal', 'homolog' => true]],
        ['ab_cpf_spc_boavista', 'CPF — SPC Boa Vista', 'Restrições e score de crédito (SPC Boa Vista).', 5.0,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'spc-boa-vista', 'homolog' => true]],
        // Produto Serasa deslistado do gateway → remapeado para o SCPC Net (code preservado).
        ['ab_cpf_spc_serasa', 'CPF — SCPC Net (restrições)', 'Restrições de crédito do CPF (SCPC Net).', 1.4,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'scpc-net-pf', 'homolog' => true]],
        ['ab_cpf_quod', 'CPF — Quod (restrições)', 'Restrições de crédito pelo bureau Quod (PF).', 6.5,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'quod-restricao-pf', 'homolog' => true]],
        ['ab_cpf_acerta', 'CPF — Acerta Essencial', 'Análise de crédito Acerta Essencial (PF).', 3.5,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'acerta-essencial', 'homolog' => true]],
        ['cpf_analise_credito_basic', 'CPF — Análise de crédito básica', 'Análise de crédito básica para pessoa física.', 10.06,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'analise-credito-basic-pf', 'homolog' => true]],
        ['ab_cpf_acerta_positivo', 'CPF — Acerta Essencial+', 'Acerta Essencial com cadastro positivo (PF).', 4.49,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'acerta-essencial-positivo', 'homolog' => true]],
        // Produto Serasa deslistado do gateway → remapeado para o Relatório Positivo (code preservado).
        ['ab_cpf_serasa_premium', 'CPF — Relatório Positivo', 'Relatório positivo completo de crédito (PF).', 36.3,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'relatorio-positivo', 'homolog' => true]],
        // Produto Serasa deslistado do gateway → remapeado para o Quod Score (code preservado).
        ['ab_cpf_serasa_score', 'CPF — Quod Score', 'Score de crédito Quod (PF).', 7.5,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'score-credito-quod', 'homolog' => true]],
        ['ab_cpf_scr_bacen', 'CPF — SCR Bacen + Score', 'Sistema de Crédito do Banco Central + score.', 6.19,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'scr-bacen-score', 'homolog' => true]],
        // limite-pj/limite-positivo-pj são produtos de CNPJ no gateway — a consulta é por CNPJ (codes preservados).
        ['ab_cpf_limite', 'CNPJ — Define Limite', 'Definição de limite de crédito da empresa (consulta por CNPJ).', 10.79,
            ['endpoint' => 'consulta/cnpj/credits', 'group' => 'cpf', 'key' => 'cnpj', 'tipo' => 'limite-pj', 'homolog' => true]],
        ['ab_cpf_limite_positivo', 'CNPJ — Define Limite Positivo', 'Definição de limite com cadastro positivo da empresa (consulta por CNPJ).', 12.39,
            ['endpoint' => 'consulta/cnpj/credits', 'group' => 'cpf', 'key' => 'cnpj', 'tipo' => 'limite-positivo-pj', 'homolog' => true]],
        ['ab_cpf_cnh', 'CPF — CNH por CPF', 'Dados da CNH a partir do CPF.', 5.2,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'cnh-por-cpf', 'homolog' => true]],
        ['ab_cpf_cnh_criminals', 'CPF — CNH + Criminais', 'CNH e antecedentes criminais.', 4.7,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'cnh-criminals', 'homolog' => true]],
        ['ab_cpf_processos', 'CPF — Processos judiciais', 'Ações e processos judiciais do CPF.', 6.19,
            ['endpoint' => 'consulta/cpf/credits', 'group' => 'cpf', 'key' => 'cpf', 'tipo' => 'acoes-processos-judiciais', 'homolog' => true]],

        // ===================== CONSULTAS CNPJ =====================
        ['ab_cnpj', 'CNPJ — Dados cadastrais', 'Dados cadastrais da empresa (Receita Federal).', 0.55,
            ['endpoint' => 'consulta/cnpj/credits', 'group' => 'cnpj', 'key' => 'cnpj', 'tipo' => 'cnpj-cadastral', 'homolog' => true]],
        ['ab_cnpj_socios', 'CNPJ — Lista de sócios', 'Quadro societário (sócios) da empresa.', 3.49,
            ['endpoint' => 'lista-socios', 'group' => 'cnpj', 'key' => 'cnpj', 'tipo' => 'lista-socios', 'homolog' => true]],
        ['ab_cnpj_capital_social', 'CNPJ — Por capital social', 'Empresas por faixa de capital social/CNAE/UF.', 3.6,
            ['endpoint' => 'cnpj/credits', 'group' => 'cnpj', 'tipo' => 'capital-social', 'homolog' => true]],
        ['ab_cnpj_cnae', 'CNPJ — Por CNAE', 'Empresas por CNAE e UF.', 1.6,
            ['endpoint' => 'cnae/credits', 'group' => 'cnpj', 'tipo' => 'cnae', 'homolog' => true]],
        ['ab_cnpj_uf', 'CNPJ — Por UF', 'Empresas por UF.', 1.6,
            ['endpoint' => 'cnpj/credits', 'group' => 'cnpj', 'tipo' => 'uf', 'homolog' => true]],
        ['ab_cnpj_cep', 'CNPJ — Por CEP', 'Empresas por CEP.', 1.6,
            ['endpoint' => 'cnpj/credits', 'group' => 'cnpj', 'tipo' => 'cep', 'homolog' => true]],
        ['ab_cnpj_lista_cnaes', 'CNPJ — Lista de CNAEs', 'Tabela de CNAEs disponíveis.', 0.04,
            ['endpoint' => 'cnpj/credits', 'group' => 'cnpj', 'tipo' => 'lista-cnaes', 'homolog' => true]],
        ['ab_cnpj_quod', 'CNPJ — Quod (restrições)', 'Restrições de crédito Quod (PJ).', 5.5,
            ['endpoint' => 'consulta/cnpj/credits', 'group' => 'cnpj', 'key' => 'cnpj', 'tipo' => 'quod-pj', 'homolog' => true]],
        ['cnpj_creditos_simples', 'CNPJ — Créditos simples', 'Consulta de créditos simples para pessoa jurídica.', 14.96,
            ['endpoint' => 'consulta/cnpj/credits', 'group' => 'cnpj', 'key' => 'cnpj', 'tipo' => 'creditos-simples-pj', 'homolog' => true]],
        // Produto Serasa deslistado do gateway → remapeado para o Relatório Positivo PJ (code preservado).
        ['ab_cnpj_serasa_premium', 'CNPJ — Relatório Positivo', 'Relatório positivo completo de crédito (PJ).', 36.3,
            ['endpoint' => 'consulta/cnpj/credits', 'group' => 'cnpj', 'key' => 'cnpj', 'tipo' => 'relatorio-positivo-pj', 'homolog' => true]],

        // ===================== CONSULTAS VEICULARES =====================
        ['ab_veiculos_dados', 'Veículos — Placa Dados', 'Dados do veículo por placa (base nacional).', 2.5,
            ['endpoint' => 'vehicles/dados', 'group' => 'vehicles', 'key' => 'placa', 'homolog' => true]],
        ['ab_veiculos_fipe', 'Veículos — Placa FIPE', 'Valor FIPE do veículo por placa.', 0.1,
            ['endpoint' => 'vehicles/fipe', 'group' => 'vehicles', 'key' => 'placa', 'homolog' => true]],
        ['ab_veiculos_agregados', 'Veículos — Agregados Básica', 'Dados agregados básicos do veículo.', 0.14,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'agregados-basica', 'homolog' => true]],
        ['ab_veiculos_agregados_propria', 'Veículos — Agregados (própria)', 'Dados agregados (consulta própria/extra).', 0.08,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'agregados-propria', 'homolog' => true]],
        ['ab_veiculos_dados_v1', 'Veículos — Placa Dados V1', 'Dados do veículo por placa (V1).', 2.5,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'veiculos-dados-v1', 'homolog' => true]],
        ['ab_veiculos_crlv', 'Veículos — CRLV-e', 'CRLV-e (documento do veículo).', 24.0,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'crlv', 'homolog' => true]],
        ['ab_veiculos_busca_documento', 'Veículos — Busca por documento', 'Veículos de uma PF/PJ por documento.', 9.5,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'documento', 'tipo' => 'veiculos-documento-pf', 'homolog' => true]],
        ['ab_veiculos_checklist', 'Veículos — Check List', 'Check list veicular.', 2.4,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'check-list', 'homolog' => true]],
        ['ab_veiculos_ficha_tecnica', 'Veículos — Ficha técnica', 'Ficha técnica do veículo.', 2.98,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'ficha-tecnica', 'homolog' => true]],
        ['ab_veiculos_vip_car', 'Veículos — Vip Car', 'Relatório Vip Car.', 29.0,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'vip-car', 'homolog' => true]],
        ['ab_veiculos_csv_completa', 'Veículos — CSV Completa', 'Renainf, Renajud, Bin e proprietário.', 4.0,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'csv-renainf-renajud-bin-proprietario', 'homolog' => true]],
        ['ab_veiculos_proprietario', 'Veículos — Proprietário atual', 'Proprietário atual do veículo.', 0.7,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'proprietario-atual', 'homolog' => true]],
        ['ab_veiculos_nacional', 'Veículos — Base nacional', 'Consulta na base nacional.', 1.8,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'nacional', 'homolog' => true]],
        ['ab_veiculos_estadual', 'Veículos — Base estadual', 'Consulta na base estadual.', 2.5,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'estadual', 'homolog' => true]],
        ['ab_veiculos_telefones', 'Veículos — Telefones + Endereço', 'Telefones e endereço a partir da placa.', 3.89,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'endereco-telefone-por-placa', 'homolog' => true]],
        ['ab_veiculos_leilao_score', 'Veículos — Leilão + Score', 'Histórico de leilão com score.', 13,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'leilao-completo-score', 'homolog' => true]],
        ['ab_veiculos_leilao', 'Veículos — Leilão', 'Histórico de leilão do veículo.', 11.5,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'leilao', 'homolog' => true]],
        ['ab_veiculos_debitos_v3', 'Veículos — Débitos V3', 'Multas e boletos (débitos V3).', 8,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'debitos-v3', 'homolog' => true]],
        ['ab_veiculos_debitos', 'Veículos — Débitos veiculares', 'Débitos veiculares (IPVA/licenciamento).', 4.79,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'veicular-debitos', 'homolog' => true]],
        ['ab_veiculos_multas', 'Veículos — Multas', 'Multas do veículo.', 3.45,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'multas', 'homolog' => true]],
        ['ab_veiculos_multas_prf', 'Veículos — Multas PRF', 'Multas da Polícia Rodoviária Federal.', 3.45,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'multas-prf', 'homolog' => true]],
        ['ab_veiculos_recall', 'Veículos — Recall', 'Recalls pendentes do veículo.', 0.62,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'recall', 'homolog' => true]],
        ['ab_veiculos_roubo_furto', 'Veículos — Roubo/Furto', 'Registro de roubo/furto.', 3.86,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'roubo-furto', 'homolog' => true]],
        ['ab_veiculos_gravame', 'Veículos — Gravame', 'Gravame/financiamento do veículo.', 2.71,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'gravame', 'homolog' => true]],
        ['ab_veiculos_renajud', 'Veículos — Renajud', 'Restrições judiciais (Renajud).', 3.4,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'renajud', 'homolog' => true]],
        ['ab_veiculos_renainf', 'Veículos — Renainf', 'Multas Renainf.', 3.45,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'renainf', 'homolog' => true]],
        ['ab_veiculos_fipe_placa', 'Veículos — FIPE por placa', 'Valor FIPE via base veicular.', 0.06,
            ['endpoint' => 'vehicles/base/001/consulta', 'group' => 'vehicles', 'key' => 'placa', 'tipo' => 'fipe', 'homolog' => true]],

        // ===================== CEP + IBGE =====================
        ['ab_cep', 'CEP — Busca por CEP', 'Endereço e dados do IBGE por CEP.', 0.04,
            ['endpoint' => 'cep/cep', 'group' => 'cep', 'key' => 'cep']],
        ['ab_cep_cidades_ddd', 'CEP — Cidades por DDD', 'Cidades atendidas por um DDD.', 0.05,
            ['endpoint' => 'cep/cidadesPorDDD', 'group' => 'cep', 'key' => 'ddd']],
        ['ab_cep_bairros', 'CEP — Bairros por cidade', 'Bairros de uma cidade.', 0.05,
            ['endpoint' => 'cep/bairros', 'group' => 'cep', 'key' => 'cidade']],
        ['ab_cep_cidades', 'CEP — Cidades por UF', 'Cidades de uma UF.', 0.05,
            ['endpoint' => 'cep/cidades', 'group' => 'cep', 'key' => 'uf']],
        ['ab_cep_estados', 'CEP — Estados', 'Lista de estados brasileiros.', 0.05,
            ['endpoint' => 'cep/estados', 'group' => 'cep', 'method' => 'GET']],

        // ===================== TABELA FIPE =====================
        ['ab_fipe_tabela_referencia', 'FIPE — Tabela de referência', 'Tabelas de referência disponíveis.', 0.06,
            ['endpoint' => 'fipe/ConsultarTabelaDeReferencia', 'group' => 'fipe']],
        ['ab_fipe_marcas', 'FIPE — Marcas', 'Marcas por tipo de veículo.', 0.06,
            ['endpoint' => 'fipe/ConsultarMarcas', 'group' => 'fipe']],
        ['ab_fipe_modelos', 'FIPE — Modelos', 'Modelos de uma marca.', 0.06,
            ['endpoint' => 'fipe/ConsultarModelos', 'group' => 'fipe']],
        ['ab_fipe_ano_modelo', 'FIPE — Ano/Modelo', 'Anos disponíveis de um modelo.', 0.06,
            ['endpoint' => 'fipe/ConsultarAnoModelo', 'group' => 'fipe']],
        ['ab_fipe_modelos_ano', 'FIPE — Modelos por ano', 'Modelos disponíveis em um ano.', 0.06,
            ['endpoint' => 'fipe/ConsultarModelosAtravesDoAno', 'group' => 'fipe']],
        ['ab_fipe_valor', 'FIPE — Valor (todos parâmetros)', 'Valor FIPE com todos os parâmetros.', 0.06,
            ['endpoint' => 'fipe/ConsultarValorComTodosParametros', 'group' => 'fipe']],

        // ===================== FERIADOS =====================
        ['ab_feriados', 'Feriados — Nacionais/Estaduais/Municipais', 'Feriados por ano/UF/tipo.', 0.02,
            ['endpoint' => 'holidays/feriados', 'group' => 'holidays']],
        ['ab_feriados_hoje', 'Feriados — Hoje', 'Indica se hoje é feriado.', 0.02,
            ['endpoint' => 'holidays/holidays', 'group' => 'holidays', 'method' => 'GET']],

        // ===================== DDD ANATEL =====================
        ['ab_ddd', 'DDD — Dados por DDD', 'Cidades/UF de um DDD (Anatel).', 0.03,
            ['endpoint' => 'ddd/ddd', 'group' => 'ddd', 'key' => 'ddd']],
        ['ab_ddd_lista', 'DDD — Lista de DDDs', 'Lista completa de DDDs.', 0.02,
            ['endpoint' => 'ddd/ddd', 'group' => 'ddd', 'method' => 'GET']],

        // ===================== ENCOMENDAS (CORREIOS) =====================
        ['ab_rastreio', 'Correios — Rastreio', 'Rastreamento de encomenda por código.', 0.04,
            ['endpoint' => 'correios/rastreio', 'group' => 'correios', 'key' => 'code']],

        // ===================== GEOLOCATION =====================
        ['ab_geocode', 'Geolocalização — Por endereço', 'Coordenadas a partir de um endereço.', 0.05,
            ['endpoint' => 'geolocation/forward-geocoding', 'group' => 'geolocation', 'key' => 'query']],
        ['ab_geocode_reverse', 'Geolocalização — Por coordenadas', 'Endereço a partir de lat/lng.', 0.05,
            ['endpoint' => 'geolocation/geocode', 'group' => 'geolocation']],

        // ===================== DISTÂNCIA ENTRE CEPS =====================
        ['ab_distancia', 'Distância entre CEPs', 'Distância/rota entre origem e destino.', 0.03,
            ['endpoint' => 'geomatrix/distance', 'group' => 'geomatrix']],

        // ===================== CLIMA =====================
        ['ab_clima_cidade', 'Clima — Por cidade', 'Previsão do tempo por cidade.', 0.05,
            ['endpoint' => 'weather/city', 'group' => 'weather', 'key' => 'city']],
        ['ab_clima_coordenadas', 'Clima — Por coordenadas', 'Previsão do tempo por lat/lon.', 0.05,
            ['endpoint' => 'weather/coordenates', 'group' => 'weather']],

        // ===================== IP DATABASE =====================
        ['ab_ip', 'IP — Geolocalização', 'Geolocalização e dados de um IP.', 0.05,
            ['endpoint' => 'database/ip', 'group' => 'database', 'key' => 'ip']],

        // ===================== IA [beta] =====================
        ['ab_ia_llama', 'IA — Chat (Llama)', 'Completions de chat via Llama.', 0.05,
            ['endpoint' => 'llama/completions/chat', 'group' => 'ia']],
        ['ab_ia_ollama', 'IA — Chat (Ollama)', 'Completions de chat via Ollama/Deepseek.', 0.05,
            ['endpoint' => 'ollama/completions/chat', 'group' => 'ia']],
        ['ab_ia_tts', 'IA — Texto para fala (TTS)', 'Síntese de voz a partir de texto.', 0.05,
            ['endpoint' => 'llama/tts', 'group' => 'ia']],

        // ===================== SMS SHORT =====================
        ['ab_sms', 'SMS — Envio', 'Envio de SMS por crédito.', 0.1,
            ['endpoint' => 'sms/send/credits', 'group' => 'sms']],

        // ===================== CHIP VIRTUAL =====================
        ['ab_chip_comprar', 'Chip Virtual — Comprar', 'Compra de chip virtual para receber SMS.', 0.5,
            ['endpoint' => 'chip/virtual/buy', 'group' => 'chip']],
        ['ab_chip_sms', 'Chip Virtual — Receber SMS', 'Consulta SMS recebido no chip.', 0.1,
            ['endpoint' => 'chip/virtual/activation', 'group' => 'chip']],
        ['ab_chip_operadoras', 'Chip Virtual — Operadoras', 'Lista de operadoras disponíveis.', 0.02,
            ['endpoint' => 'chip/virtual/operators', 'group' => 'chip', 'method' => 'GET']],
        ['ab_chip_servicos', 'Chip Virtual — Serviços', 'Lista de serviços disponíveis.', 0.02,
            ['endpoint' => 'chip/virtual/services', 'group' => 'chip', 'method' => 'GET']],

        // ===================== LIGAÇÕES (URA) =====================
        ['ab_ura_discador', 'URA — Discador', 'Dispara ligação automatizada (discador).', 0.3,
            ['endpoint' => 'ura/call', 'group' => 'ura']],
        ['ab_ura_status', 'URA — Status da ligação', 'Status de uma ligação disparada.', 0.02,
            ['endpoint' => 'ura/call/status', 'group' => 'ura']],
    ];

    /**
     * Custo do provedor (centavos) por query_type, derivado do catálogo.
     *
     * @return array<string, int>
     */
    public static function costMap(): array
    {
        $out = [];
        foreach (self::CATALOG as [$code, , , $brl]) {
            $out[$code] = max(1, (int) round($brl * 100));
        }

        return $out;
    }

    public function run(): void
    {
        $ids = app(IdGenerator::class);

        $providerId = ProviderModel::query()->where('identifier', 'api_brasil')->value('id');

        foreach (self::CATALOG as [$code, $name, $description, $brl, $config]) {
            // Custo do provedor (centavos) e preço de venda = custo + margem.
            $costCents = max(1, (int) round($brl * 100));
            $priceCents = Pricing::sellPriceCents($costCents);

            $this->ensureQueryType($ids, $code, $name, $description, $priceCents);

            if ($providerId !== null) {
                $this->ensureCapability($ids, $providerId, $code, $costCents, $priceCents, $config);
            }
        }
    }

    private function ensureQueryType(IdGenerator $ids, string $code, string $name, string $description, int $priceCents): void
    {
        if (DB::table('query_types')->where('code', $code)->exists()) {
            return;
        }

        DB::table('query_types')->insert([
            'id' => $ids->generate(),
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'default_credit_cost' => $priceCents,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function ensureCapability(IdGenerator $ids, string $providerId, string $code, int $costCents, int $priceCents, array $config): void
    {
        $exists = ProviderCapabilityModel::query()
            ->where('provider_id', $providerId)
            ->where('query_type', $code)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('provider_capabilities')->insert([
            'id' => $ids->generate(),
            'provider_id' => $providerId,
            'query_type' => $code,
            'priority' => 10,
            'price_cents' => $priceCents,
            'cost_cents' => $costCents,
            'enabled' => true,
            'config' => json_encode($this->buildConfig($config)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Traduz a definição compacta do catálogo no JSON consumido pelo adapter.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildConfig(array $config): array
    {
        $out = ['endpoint' => $config['endpoint']];

        if (isset($config['method']) && strtoupper((string) $config['method']) !== 'POST') {
            $out['method'] = strtoupper((string) $config['method']);
        }

        if (isset($config['key'])) {
            $out['body_key'] = $config['key'];
        }

        if (isset($config['group'])) {
            $out['device_group'] = $config['group'];
        }

        if (isset($config['tipo'])) {
            $out['body'] = ['tipo' => $config['tipo']];
        }

        if (isset($config['body']) && is_array($config['body'])) {
            $out['body'] = array_merge($out['body'] ?? [], $config['body']);
        }

        if (($config['homolog'] ?? false) === true) {
            $out['homolog'] = true;
        }

        return $out;
    }
}
