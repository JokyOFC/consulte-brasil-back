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
 * Catálogo completo da API CPF.CNPJ (https://www.cpfcnpj.com.br/dev).
 *
 * Cada "pacote" do provedor vira um query_type da nossa API + uma capability
 * do provedor "cpfcnpj" (com o ID do pacote no config). Assim, qualquer
 * consulta que a CPF.CNPJ oferece fica disponível via /api/v1/consult/{tipo}.
 *
 * Idempotente: não duplica query_type nem capability já existentes.
 */
final class CpfCnpjCatalogSeeder extends Seeder
{
    /**
     * [code, nome, descrição, pacote (ID na CPF.CNPJ), preço BRL].
     *
     * @var list<array{0:string,1:string,2:string,3:string,4:float}>
     */
    private const CATALOG = [
        // ===== CPF =====
        ['cpf_nome', 'CPF — Nome', 'Nome completo do titular.', '1', 0.17],
        ['cpf_nascimento', 'CPF — Nome e nascimento', 'Nome completo e data de nascimento.', '7', 0.25],
        ['cpf', 'CPF — Dados básicos', 'Nome, nascimento, nome da mãe e gênero.', '2', 0.29],
        ['cpf_situacao', 'CPF — Situação cadastral (RF)', 'Situação na Receita Federal + comprovante (PDF).', '8', 0.41],
        ['cpf_completo', 'CPF — Completo (RF)', 'Dados completos com situação cadastral em tempo real.', '9', 0.53],
        ['cpf_endereco', 'CPF — Com endereço', 'Nome, nascimento, gênero e endereço completo.', '3', 1.35],
        ['cpf_ppe', 'CPF — Pessoa Politicamente Exposta', 'Indicador PPE/PEP e relacionados.', '14', 0.23],
        ['cpf_empresas', 'CPF — Empresas', 'CNPJs em que o titular é sócio.', '15', 0.23],
        ['cpf_endereco_situacao', 'CPF — Endereço e situação', 'Nome, nascimento, endereço e situação cadastral.', '18', 1.57],
        ['cpf_contatos', 'CPF — Contatos', 'E-mails, telefones e WhatsApp.', '21', 0.27],
        ['cpf_family', 'CPF — Família', 'Vínculos familiares do titular.', '20', 1.13],
        ['cpf_programas_sociais', 'CPF — Programas sociais', 'Programas sociais em que o titular participa.', '22', 0.17],
        ['cpf_antecedentes', 'CPF — Mandados/Antecedentes', 'Mandados de busca e apreensão (BNMP) e lista INTERPOL.', '23', 1.46],
        ['cpf_situacao_simples', 'CPF — Situação simplificada', 'Nome, nascimento e situação cadastral.', '26', 0.37],
        ['cpf_cac', 'CPF — Antecedentes criminais (CAC/SINIC)', 'Certidão de antecedentes criminais (PF) em PDF.', '27', 0.27],
        ['cpf_cns', 'CPF — Cartão Nacional de Saúde', 'CNS de 15 dígitos vinculado ao CPF.', '24', 0.25],
        ['cpf_risco', 'CPF — Score de risco', 'Nível e score de risco do CPF.', '13', 0.50],

        // ===== CNPJ =====
        ['cnpj_razao', 'CNPJ — Razão social', 'Razão social (consulta por CNPJ ou busca por nome).', '4', 0.15],
        ['cnpj_endereco', 'CNPJ — Endereço', 'Razão social, nome fantasia e endereço completo.', '5', 0.27],
        ['cnpj_contato', 'CNPJ — Contatos e situação', 'Endereço, telefones, e-mail e situação cadastral.', '10', 0.36],
        ['cnpj', 'CNPJ — Completo (RF)', 'Dados completos da Receita Federal + cartão CNPJ (PDF).', '6', 0.51],
        ['cnpj_simples', 'CNPJ — Simples/SIMEI/Suframa', 'Informações de Simples Nacional, SIMEI e Suframa.', '11', 0.34],
        ['cnpj_ie', 'CNPJ — Inscrições estaduais', 'Inscrições estaduais e razão social.', '16', 0.17],
        ['cnpj_contatos', 'CNPJ — Contatos dos sócios', 'E-mails, telefones e WhatsApp dos sócios.', '19', 0.30],
        ['cnpj_qsa', 'CNPJ — QSA detalhado', 'Percentual societário de cada sócio.', '25', 2.25],
        ['cnpj_risco', 'CNPJ — Score de risco', 'Nível e score de risco do CNPJ.', '12', 0.50],

        // ===== Outros =====
        ['nfe', 'NFe — Unificada PF/PJ', 'Consulta de notas fiscais (NFe) de PF e PJ em tempo real.', '100', 0.04],
    ];

    /**
     * Custo do provedor (centavos) por query_type, derivado do catálogo.
     *
     * @return array<string, int>
     */
    public static function costMap(): array
    {
        $out = [];
        foreach (self::CATALOG as [$code, , , , $brl]) {
            $out[$code] = max(1, (int) round($brl * 100));
        }

        return $out;
    }

    public function run(): void
    {
        $ids = app(IdGenerator::class);

        $providerId = ProviderModel::query()->where('identifier', 'cpfcnpj')->value('id');

        foreach (self::CATALOG as [$code, $name, $description, $package, $brl]) {
            // O valor da doc é o CUSTO do provedor; venda = custo + margem.
            $costCents = max(1, (int) round($brl * 100));
            $priceCents = Pricing::sellPriceCents($costCents);

            $this->ensureQueryType($ids, $code, $name, $description, $priceCents);

            if ($providerId !== null) {
                $this->ensureCapability($ids, $providerId, $code, $package, $costCents, $priceCents);
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

    private function ensureCapability(IdGenerator $ids, string $providerId, string $code, string $package, int $costCents, int $priceCents): void
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
            'config' => json_encode(['endpoint' => $package]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
