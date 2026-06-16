import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { CHART_COLORS, MoneyChartTooltip, formatChartDayLabel } from '@/components/chart-utils';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatBRL } from '@/lib/format';

interface DailyPoint {
    date: string;
    full_date: string;
    consumption_cents: number;
    count: number;
}

interface ProviderConsumption {
    name: string;
    identifier: string;
    consumption_cents: number;
    count: number;
}

interface AdminShellProps {
    daily: DailyPoint[];
    today_cents: number;
    week_cents: number;
    month_cents: number;
    today_count: number;
    month_count: number;
    by_provider: ProviderConsumption[];
}

function MiniSparkline({ data }: { data: DailyPoint[] }) {
    const recent = data.slice(-7);
    const max = Math.max(...recent.map((d) => d.consumption_cents), 1);

    return (
        <svg viewBox="0 0 56 16" className="h-3.5 w-14 shrink-0" aria-hidden>
            {recent.map((point, i) => {
                const h = Math.max(2, (point.consumption_cents / max) * 14);
                return (
                    <rect
                        key={point.full_date}
                        x={i * 8 + 1}
                        y={16 - h}
                        width={5}
                        height={h}
                        rx={1}
                        fill={CHART_COLORS.consumption}
                        opacity={point.consumption_cents > 0 ? 0.85 : 0.25}
                    />
                );
            })}
        </svg>
    );
}

export function AdminConsumptionWidget() {
    const adminShell = usePage<{ adminShell?: AdminShellProps | null }>().props.adminShell;

    const chartData = useMemo(
        () => (adminShell?.daily ?? []).map((d) => ({
            date: d.date,
            label: formatChartDayLabel(d.date),
            consumption: d.consumption_cents,
            count: d.count,
        })),
        [adminShell?.daily],
    );

    const maxProviderCents = useMemo(
        () => Math.max(...(adminShell?.by_provider ?? []).map((p) => p.consumption_cents), 1),
        [adminShell?.by_provider],
    );

    if (!adminShell) {
        return null;
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="group ml-auto flex items-center gap-2 rounded-md px-2 py-1 text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground"
                    title="Ver consumo da plataforma"
                >
                    <MiniSparkline data={adminShell.daily} />
                    <span className="hidden text-xs tabular-nums sm:inline">
                        {formatBRL(adminShell.today_cents)}
                    </span>
                </button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Consumo</DialogTitle>
                    <DialogDescription>
                        Débitos das carteiras · últimos 14 dias
                    </DialogDescription>
                </DialogHeader>

                <div className="grid grid-cols-3 gap-2 text-center">
                    <div className="rounded-md border border-border/60 px-2 py-2">
                        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Hoje</p>
                        <p className="text-sm font-semibold tabular-nums">{formatBRL(adminShell.today_cents)}</p>
                        <p className="text-[10px] text-muted-foreground">{adminShell.today_count} consultas</p>
                    </div>
                    <div className="rounded-md border border-border/60 px-2 py-2">
                        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Semana</p>
                        <p className="text-sm font-semibold tabular-nums">{formatBRL(adminShell.week_cents)}</p>
                    </div>
                    <div className="rounded-md border border-border/60 px-2 py-2">
                        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Mês</p>
                        <p className="text-sm font-semibold tabular-nums">{formatBRL(adminShell.month_cents)}</p>
                        <p className="text-[10px] text-muted-foreground">{adminShell.month_count} consultas</p>
                    </div>
                </div>

                <div className="h-36 w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={chartData} margin={{ top: 4, right: 4, left: 0, bottom: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="currentColor" strokeOpacity={0.08} vertical={false} />
                            <XAxis
                                dataKey="date"
                                tickFormatter={formatChartDayLabel}
                                tick={{ fontSize: 10, fill: 'currentColor', opacity: 0.5 }}
                                axisLine={false}
                                tickLine={false}
                                interval="preserveStartEnd"
                            />
                            <YAxis
                                tick={{ fontSize: 10, fill: 'currentColor', opacity: 0.5 }}
                                tickFormatter={(v: number) => formatBRL(v)}
                                width={56}
                                axisLine={false}
                                tickLine={false}
                            />
                            <Tooltip
                                content={<MoneyChartTooltip />}
                                labelFormatter={(label) => formatChartDayLabel(String(label))}
                            />
                            <Area
                                type="monotone"
                                dataKey="consumption"
                                name="Consumo"
                                stroke={CHART_COLORS.consumption}
                                fill={CHART_COLORS.consumption}
                                fillOpacity={0.12}
                                strokeWidth={2}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>

                {adminShell.by_provider.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-xs font-medium text-muted-foreground">Por provedor (mês)</p>
                        <ul className="space-y-1.5">
                            {adminShell.by_provider.map((provider) => (
                                <li key={provider.identifier} className="flex items-center gap-2 text-xs">
                                    <span className="w-20 shrink-0 truncate text-muted-foreground" title={provider.name}>
                                        {provider.name}
                                    </span>
                                    <div className="relative h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="absolute inset-y-0 left-0 rounded-full"
                                            style={{
                                                width: `${Math.max(4, (provider.consumption_cents / maxProviderCents) * 100)}%`,
                                                backgroundColor: CHART_COLORS.consumption,
                                            }}
                                        />
                                    </div>
                                    <span className="w-16 shrink-0 text-right tabular-nums text-foreground">
                                        {formatBRL(provider.consumption_cents)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
