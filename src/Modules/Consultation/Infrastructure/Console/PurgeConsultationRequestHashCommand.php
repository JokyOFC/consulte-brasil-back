<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;

/**
 * Política de retenção LGPD: a tabela `consultations` mantém apenas
 * metadados (provider, latência, custo) e um HASH do request — não o
 * payload em claro. Mesmo assim, o hash é um vetor de re-identificação
 * fraca, então o anonimizamos após o período de retenção configurado.
 *
 * A linha NÃO é deletada (precisamos do registro contábil) — só o hash é
 * zerado.
 */
final class PurgeConsultationRequestHashCommand extends Command
{
    protected $signature = 'consultation:purge-request-hash {--days=180 : Idade mínima em dias para anonimização}';

    protected $description = 'Anonimiza o request_hash de consultas antigas (retenção LGPD).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $count = ConsultationModel::query()
            ->where('created_at', '<', $cutoff)
            ->where('request_hash', '!=', str_repeat('0', 64))
            ->update(['request_hash' => str_repeat('0', 64)]);

        $this->info("Anonimizado(s) {$count} registro(s) anteriores a {$cutoff->toIso8601String()}.");

        return self::SUCCESS;
    }
}
