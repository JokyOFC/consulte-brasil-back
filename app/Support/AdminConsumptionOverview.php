<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;

final class AdminConsumptionOverview
{
    /** @return array<string, mixed> */
    public function headerPayload(): array
    {
        $start = now()->subDays(13)->startOfDay();

        $byDay = collect(ConsultationModel::query()
            ->selectRaw('DATE(created_at) as d, SUM(credit_cost) as total, COUNT(*) as count')
            ->where('created_at', '>=', $start)
            ->groupBy('d')
            ->get())
            ->mapWithKeys(fn ($r) => [(string) $r->d => [
                'consumption_cents' => (int) $r->total,
                'count' => (int) $r->count,
            ]])
            ->all();

        $daily = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $row = $byDay[$date] ?? ['consumption_cents' => 0, 'count' => 0];
            $daily[] = [
                'date' => substr($date, 5),
                'full_date' => $date,
                'consumption_cents' => $row['consumption_cents'],
                'count' => $row['count'],
            ];
        }

        $todayStart = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $aggregate = static fn ($query) => (int) $query->sum('credit_cost');
        $countAggregate = static fn ($query) => (int) $query->count();

        $base = ConsultationModel::query();

        $byProvider = DB::table('consultations')
            ->leftJoin('providers', 'consultations.provider_id', '=', 'providers.id')
            ->where('consultations.created_at', '>=', $monthStart)
            ->groupBy('providers.identifier', 'providers.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get([
                DB::raw('COALESCE(providers.name, \'—\') as name'),
                DB::raw('COALESCE(providers.identifier, \'unknown\') as identifier'),
                DB::raw('SUM(consultations.credit_cost) as total'),
                DB::raw('COUNT(*) as count'),
            ])
            ->map(fn ($r) => [
                'name' => $r->name,
                'identifier' => $r->identifier,
                'consumption_cents' => (int) $r->total,
                'count' => (int) $r->count,
            ])
            ->all();

        return [
            'daily' => $daily,
            'today_cents' => $aggregate((clone $base)->where('created_at', '>=', $todayStart)),
            'week_cents' => $aggregate((clone $base)->where('created_at', '>=', $weekStart)),
            'month_cents' => $aggregate((clone $base)->where('created_at', '>=', $monthStart)),
            'today_count' => $countAggregate((clone $base)->where('created_at', '>=', $todayStart)),
            'month_count' => $countAggregate((clone $base)->where('created_at', '>=', $monthStart)),
            'by_provider' => $byProvider,
        ];
    }
}
