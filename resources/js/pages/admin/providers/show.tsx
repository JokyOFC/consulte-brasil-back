import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowLeft, BarChart3, Clock, Coins, Percent, Server, TrendingUp, Wallet } from 'lucide-react';
import { useMemo } from 'react';
import {
    Area,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ComposedChart,
    LabelList,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    CHART_COLORS,
    ChartEmptyState,
    CountChartTooltip,
    MoneyChartTooltip,
    TypeBarChartTooltip,
    chartMoneyDomain,
    chartYDomainMax,
    formatChartDayLabel,
    formatQueryTypeLabel,
    truncateLabel,
} from '@/components/chart-utils';
import { ConsultationStatusBadge } from '@/components/consultation-status-badge';
import { PageHeader } from '@/components/page-header';
import { ProviderBalanceDisplay  } from '@/components/provider-balance-display';
import type {ProviderBalance} from '@/components/provider-balance-display';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL } from '@/lib/format';
import { queryTypeDisplayName } from '@/lib/query-type-display';

interface Provider {
    id: string;
    identifier: string;
    name: string;
    status: string;
    environment: string;
    base_url: string | null;
    capabilities_count: number;
    enabled_capabilities: number;
}

interface Stats {
    total: number;
    success: number;
    failed: number;
    refunded: number;
    today: number;
    revenue_cents: number;
    cost_cents: number;
    margin_cents: number;
    avg_latency_ms: number;
    success_rate: number;
}

interface DailyPoint {
    date: string;
    count: number;
    revenue: number;
    cost: number;
    margin: number;
}

interface TypeRow {
    query_type: string;
    total: number;
    revenue: number;
    cost: number;
}

interface LogRow {
    id: string;
    query_type: string;
    status: string;
    credit_cost: number;
    latency_ms: number | null;
    http_status: number | null;
    created_at: string;
    account_name: string | null;
}

interface Props {
    provider: Provider;
    stats: Stats;
    daily: DailyPoint[];
    by_type: TypeRow[];
    recent: LogRow[];
    balance: ProviderBalance;
}

const brlTick = (v: number) => formatBRL(v);

const chartAxisProps = {
    tickLine: false,
    axisLine: false,
    fontSize: 11,
    className: 'fill-muted-foreground',
} as const;

export default function AdminProviderShow({ provider, stats, daily, by_type, recent, balance }: Props) {
    usePageFlash();

    const dailyChart = useMemo(
        () => daily.map((point) => ({
            ...point,
            label: formatChartDayLabel(point.date),
        })),
        [daily],
    );

    const dailyTotals = useMemo(() => ({
        revenue: daily.reduce((sum, point) => sum + point.revenue, 0),
        cost: daily.reduce((sum, point) => sum + point.cost, 0),
        margin: daily.reduce((sum, point) => sum + point.margin, 0),
        count: daily.reduce((sum, point) => sum + point.count, 0),
    }), [daily]);

    const moneyMin = useMemo(
        () => Math.min(...daily.flatMap((point) => [point.revenue, point.cost, point.margin]), 0),
        [daily],
    );

    const moneyMax = useMemo(
        () => Math.max(...daily.flatMap((point) => [point.revenue, point.cost, point.margin]), 0),
        [daily],
    );

    const hasMoneyActivity = moneyMax > 0 || moneyMin < 0;

    const volumeMax = useMemo(
        () => Math.max(...daily.map((point) => point.count), 0),
        [daily],
    );

    const hasVolumeActivity = volumeMax > 0;

    const byTypeChart = useMemo(
        () => by_type.map((row, index) => ({
            ...row,
            label: formatQueryTypeLabel(row.query_type),
            shortLabel: truncateLabel(formatQueryTypeLabel(row.query_type), 20),
            rank: index + 1,
        })),
        [by_type],
    );

    const byTypeMax = useMemo(
        () => Math.max(...by_type.map((row) => row.total), 0),
        [by_type],
    );

    const hasByType = byTypeMax > 0;

    return (
        <>
            <Head title={`Provedor — ${provider.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={provider.name}
                    description={`${provider.identifier}${provider.base_url ? ` · ${provider.base_url}` : ''}`}
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/providers">
                                <ArrowLeft /> Voltar
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        className={provider.status === 'enabled'
                            ? 'border-transparent bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                            : 'border-transparent bg-muted text-muted-foreground'}
                    >
                        {provider.status === 'enabled' ? 'Habilitado' : 'Desabilitado'}
                    </Badge>
                    <Badge
                        variant="outline"
                        className={provider.environment === 'production'
                            ? 'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300'
                            : 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'}
                    >
                        {provider.environment === 'production' ? 'Produção' : 'Sandbox'}
                    </Badge>
                    <Badge variant="secondary">
                        {provider.enabled_capabilities}/{provider.capabilities_count} tipos ativos
                    </Badge>
                </div>

                {provider.identifier !== 'mercado_pago' && (
                    <ProviderBalanceDisplay
                        balance={balance}
                        providerId={provider.id}
                    />
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Receita (minha plataforma)" value={formatBRL(stats.revenue_cents)} icon={Wallet} highlight />
                    <StatCard label="Custo (provedor)" value={formatBRL(stats.cost_cents)} icon={Coins} />
                    <StatCard label="Margem" value={formatBRL(stats.margin_cents)} icon={TrendingUp} />
                    <StatCard
                        label="Taxa de sucesso"
                        value={`${stats.success_rate}%`}
                        icon={Percent}
                        hint={`${stats.success} ok · ${stats.failed} falhas`}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Consultas (total)" value={stats.total} icon={Activity} hint={`${stats.today} hoje`} />
                    <StatCard label="Sucesso" value={stats.success} icon={Server} />
                    <StatCard label="Estornadas" value={stats.refunded} icon={Coins} />
                    <StatCard label="Latência média" value={`${stats.avg_latency_ms} ms`} icon={Clock} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-0 py-0 lg:col-span-2">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Receita, custo e margem (30 dias)</CardTitle>
                            <CardDescription>
                                {hasMoneyActivity
                                    ? `Receita ${formatBRL(dailyTotals.revenue)} · custo ${formatBRL(dailyTotals.cost)} · margem ${formatBRL(dailyTotals.margin)}`
                                    : 'Evolução diária da operação deste provedor'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-4">
                            {hasMoneyActivity ? (
                                <ResponsiveContainer width="100%" height={300}>
                                    <ComposedChart data={dailyChart} margin={{ top: 12, right: 12, left: 4, bottom: 4 }}>
                                        <defs>
                                            <linearGradient id="providerRevenueFill" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor={CHART_COLORS.revenue} stopOpacity={0.3} />
                                                <stop offset="100%" stopColor={CHART_COLORS.revenue} stopOpacity={0.02} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} className="stroke-border/60" />
                                        <XAxis dataKey="label" {...chartAxisProps} interval="preserveStartEnd" minTickGap={24} />
                                        <YAxis
                                            {...chartAxisProps}
                                            tickFormatter={brlTick}
                                            width={76}
                                            domain={chartMoneyDomain(moneyMin, moneyMax)}
                                        />
                                        <Tooltip content={<MoneyChartTooltip />} />
                                        <Legend
                                            verticalAlign="top"
                                            align="right"
                                            iconType="circle"
                                            iconSize={8}
                                            wrapperStyle={{ fontSize: 12, paddingBottom: 8 }}
                                        />
                                        <Area
                                            type="monotone"
                                            dataKey="revenue"
                                            name="Receita"
                                            fill="url(#providerRevenueFill)"
                                            stroke={CHART_COLORS.revenue}
                                            strokeWidth={2}
                                            dot={false}
                                            activeDot={{ r: 4, strokeWidth: 0 }}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="cost"
                                            name="Custo"
                                            stroke={CHART_COLORS.cost}
                                            strokeWidth={2}
                                            strokeDasharray="6 4"
                                            dot={{ r: 2, fill: CHART_COLORS.cost, strokeWidth: 0 }}
                                            activeDot={{ r: 5, strokeWidth: 0 }}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="margin"
                                            name="Margem"
                                            stroke={CHART_COLORS.margin}
                                            strokeWidth={2.5}
                                            dot={{ r: 2, fill: CHART_COLORS.margin, strokeWidth: 0 }}
                                            activeDot={{ r: 5, strokeWidth: 0 }}
                                        />
                                    </ComposedChart>
                                </ResponsiveContainer>
                            ) : (
                                <ChartEmptyState
                                    icon={TrendingUp}
                                    title="Sem movimentação financeira"
                                    description="Quando houver consultas cobradas neste provedor, receita, custo e margem aparecerão aqui."
                                />
                            )}
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Volume de consultas (30 dias)</CardTitle>
                            <CardDescription>
                                {hasVolumeActivity
                                    ? `${dailyTotals.count.toLocaleString('pt-BR')} consultas no período`
                                    : 'Quantidade diária de requisições'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-4">
                            {hasVolumeActivity ? (
                                <ResponsiveContainer width="100%" height={300}>
                                    <ComposedChart data={dailyChart} margin={{ top: 12, right: 12, left: 4, bottom: 4 }}>
                                        <defs>
                                            <linearGradient id="providerVolumeFill" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor={CHART_COLORS.volume} stopOpacity={0.4} />
                                                <stop offset="100%" stopColor={CHART_COLORS.volume} stopOpacity={0.03} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} className="stroke-border/60" />
                                        <XAxis dataKey="label" {...chartAxisProps} interval="preserveStartEnd" minTickGap={20} />
                                        <YAxis
                                            {...chartAxisProps}
                                            width={36}
                                            allowDecimals={false}
                                            domain={[0, Math.max(chartYDomainMax(volumeMax), 4)]}
                                        />
                                        <Tooltip content={<CountChartTooltip />} />
                                        <Area
                                            type="monotone"
                                            dataKey="count"
                                            name="Consultas"
                                            fill="url(#providerVolumeFill)"
                                            stroke={CHART_COLORS.volume}
                                            strokeWidth={2}
                                            dot={{ r: 2, fill: CHART_COLORS.volume, strokeWidth: 0 }}
                                            activeDot={{ r: 5, strokeWidth: 0 }}
                                        />
                                    </ComposedChart>
                                </ResponsiveContainer>
                            ) : (
                                <ChartEmptyState
                                    icon={Activity}
                                    title="Nenhuma consulta no período"
                                    description="O volume diário será exibido assim que este provedor processar requisições."
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b border-border py-4">
                        <CardTitle className="text-base">Consumo por tipo de consulta</CardTitle>
                        <CardDescription>
                            {hasByType
                                ? `${stats.total.toLocaleString('pt-BR')} consultas · ${by_type.length} tipo(s) distintos`
                                : 'Distribuição por tipo de consulta'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-4">
                        {hasByType ? (
                            <ResponsiveContainer width="100%" height={Math.max(200, byTypeChart.length * 44)}>
                                <BarChart
                                    data={byTypeChart}
                                    layout="vertical"
                                    margin={{ top: 4, right: 40, left: 4, bottom: 4 }}
                                    barCategoryGap="24%"
                                >
                                    <CartesianGrid strokeDasharray="3 3" horizontal={false} className="stroke-border/60" />
                                    <XAxis
                                        type="number"
                                        {...chartAxisProps}
                                        allowDecimals={false}
                                        domain={[0, chartYDomainMax(byTypeMax)]}
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey="shortLabel"
                                        {...chartAxisProps}
                                        width={120}
                                    />
                                    <Tooltip content={<TypeBarChartTooltip />} cursor={{ fill: 'hsl(var(--muted) / 0.35)' }} />
                                    <Bar dataKey="total" name="Consultas" radius={[0, 6, 6, 0]} maxBarSize={32}>
                                        {byTypeChart.map((entry) => (
                                            <Cell
                                                key={entry.query_type}
                                                fill={CHART_COLORS.revenue}
                                                fillOpacity={1 - (entry.rank - 1) * 0.1}
                                            />
                                        ))}
                                        <LabelList
                                            dataKey="total"
                                            position="right"
                                            className="fill-muted-foreground"
                                            fontSize={11}
                                        />
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmptyState
                                icon={BarChart3}
                                title="Sem consultas registradas"
                                description="A distribuição por tipo aparecerá quando houver volume neste provedor."
                            />
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Valores por tipo</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/30 text-left text-xs text-muted-foreground">
                                        <tr className="border-b border-border">
                                            <th className="px-6 py-2.5 font-medium">Consulta</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Qtd</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Receita</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Custo</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Margem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {by_type.map((t, index) => (
                                            <tr
                                                key={t.query_type}
                                                className={`border-b border-border last:border-0 hover:bg-muted/40 ${
                                                    index % 2 === 1 ? 'bg-muted/15' : ''
                                                }`}
                                            >
                                                <td className="px-6 py-2.5">
                                                    <p className="font-medium">
                                                        {queryTypeDisplayName(null, t.query_type)}
                                                    </p>
                                                    <code className="mt-1 inline-block rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
                                                        {t.query_type}
                                                    </code>
                                                </td>
                                                <td className="px-6 py-2.5 text-right tabular-nums">{t.total}</td>
                                                <td className="px-6 py-2.5 text-right tabular-nums">{formatBRL(t.revenue)}</td>
                                                <td className="px-6 py-2.5 text-right tabular-nums text-muted-foreground">{formatBRL(t.cost)}</td>
                                                <td className="px-6 py-2.5 text-right tabular-nums font-medium">{formatBRL(t.revenue - t.cost)}</td>
                                            </tr>
                                        ))}
                                        {by_type.length === 0 && (
                                            <tr>
                                                <td colSpan={5} className="px-6 py-10 text-center text-muted-foreground">
                                                    Sem dados.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Logs recentes</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/30 text-left text-xs text-muted-foreground">
                                        <tr className="border-b border-border">
                                            <th className="px-6 py-2.5 font-medium">Cliente</th>
                                            <th className="px-6 py-2.5 font-medium">Consulta</th>
                                            <th className="px-6 py-2.5 font-medium">Status</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Latência</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recent.map((c, index) => (
                                            <tr
                                                key={c.id}
                                                className={`border-b border-border last:border-0 hover:bg-muted/40 ${
                                                    index % 2 === 1 ? 'bg-muted/15' : ''
                                                }`}
                                            >
                                                <td className="px-6 py-2.5">{c.account_name ?? '—'}</td>
                                                <td className="px-6 py-2.5">
                                                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
                                                        {c.query_type}
                                                    </code>
                                                </td>
                                                <td className="px-6 py-2.5"><ConsultationStatusBadge status={c.status} /></td>
                                                <td className="px-6 py-2.5 text-right tabular-nums text-muted-foreground">
                                                    {c.latency_ms !== null ? `${c.latency_ms} ms` : '—'}
                                                </td>
                                                <td className="px-6 py-2.5 text-right tabular-nums">{formatBRL(c.credit_cost)}</td>
                                            </tr>
                                        ))}
                                        {recent.length === 0 && (
                                            <tr>
                                                <td colSpan={5} className="px-6 py-10 text-center text-muted-foreground">
                                                    Nenhuma consulta registrada.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminProviderShow.layout = {
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Provedores', href: '/admin/providers' },
        { title: 'Detalhes', href: '#' },
    ],
};
