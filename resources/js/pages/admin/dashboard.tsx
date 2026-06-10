import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowRight, BarChart3, Building2, CreditCard, Layers, Repeat, Server, TrendingUp, Users } from 'lucide-react';
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
    MoneyChartTooltip,
    chartYDomainMax,
    formatChartDayLabel,
    truncateLabel,
} from '@/components/chart-utils';
import { ConsultationStatusBadge } from '@/components/consultation-status-badge';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL } from '@/lib/format';

interface Stats {
    accounts: number;
    users: number;
    consultations_today: number;
    consultations_total: number;
    credits_in_circulation: number;
    active_providers: number;
    plans: number;
    revenue_month: number;
    mrr: number;
    active_subscriptions: number;
    overdue_invoices: number;
}

interface DailyPoint {
    date: string;
    revenue: number;
    recharges: number;
    consumption: number;
}

interface TopClient {
    name: string;
    total: number;
}

interface Charts {
    daily: DailyPoint[];
    top_clients: TopClient[];
}

interface RecentConsultation {
    id: string;
    account_name: string | null;
    query_type: string;
    status: string;
    credit_cost: number;
    provider: string | null;
    created_at: string;
}

interface Props {
    stats: Stats;
    charts: Charts;
    recent: RecentConsultation[];
}

const brlTick = (v: number) => formatBRL(v);

const chartAxisProps = {
    tickLine: false,
    axisLine: false,
    fontSize: 11,
    className: 'fill-muted-foreground',
} as const;

export default function AdminDashboard({ stats, charts, recent }: Props) {
    usePageFlash();

    const dailyChart = useMemo(
        () => charts.daily.map((point) => ({
            ...point,
            label: formatChartDayLabel(point.date),
        })),
        [charts.daily],
    );

    const dailyTotals = useMemo(() => ({
        revenue: charts.daily.reduce((sum, point) => sum + point.revenue, 0),
        recharges: charts.daily.reduce((sum, point) => sum + point.recharges, 0),
        consumption: charts.daily.reduce((sum, point) => sum + point.consumption, 0),
    }), [charts.daily]);

    const dailyMax = useMemo(
        () => Math.max(
            ...charts.daily.flatMap((point) => [point.revenue, point.recharges, point.consumption]),
            0,
        ),
        [charts.daily],
    );

    const hasDailyActivity = dailyMax > 0;
    const hasTopClients = charts.top_clients.some((client) => client.total > 0);

    const topClientsChart = useMemo(
        () => charts.top_clients.map((client, index) => ({
            ...client,
            shortName: truncateLabel(client.name, 18),
            rank: index + 1,
        })),
        [charts.top_clients],
    );

    const topClientsMax = useMemo(
        () => Math.max(...charts.top_clients.map((client) => client.total), 0),
        [charts.top_clients],
    );

    return (
        <>
            <Head title="Admin — Visão geral" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Visão geral" description="Métricas do SaaS em tempo real." />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Receita no mês" value={formatBRL(stats.revenue_month)} icon={TrendingUp} highlight />
                    <StatCard label="MRR" value={formatBRL(stats.mrr)} icon={Repeat} hint={`${stats.active_subscriptions} assinatura(s)`} />
                    <StatCard label="Saldo em circulação" value={formatBRL(stats.credits_in_circulation)} icon={CreditCard} />
                    <StatCard label="Faturas vencidas" value={formatBRL(stats.overdue_invoices)} icon={Activity} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Clientes" value={stats.accounts} icon={Building2} />
                    <StatCard label="Consultas hoje" value={stats.consultations_today} icon={Activity} hint={`${stats.consultations_total} no total`} />
                    <StatCard label="Provedores ativos" value={stats.active_providers} icon={Server} hint={`${stats.plans} plano(s)`} />
                    <StatCard label="Usuários" value={stats.users} icon={Users} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-0 py-0 lg:col-span-2">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Receita, recargas e consumo</CardTitle>
                            <CardDescription>
                                Últimos 14 dias · receita {formatBRL(dailyTotals.revenue)} · recargas {formatBRL(dailyTotals.recharges)} · consumo {formatBRL(dailyTotals.consumption)}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-4">
                            {hasDailyActivity ? (
                                <ResponsiveContainer width="100%" height={300}>
                                    <ComposedChart data={dailyChart} margin={{ top: 12, right: 12, left: 4, bottom: 4 }}>
                                        <defs>
                                            <linearGradient id="consumptionFill" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor={CHART_COLORS.consumption} stopOpacity={0.35} />
                                                <stop offset="100%" stopColor={CHART_COLORS.consumption} stopOpacity={0.02} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} className="stroke-border/60" />
                                        <XAxis dataKey="label" {...chartAxisProps} interval="preserveStartEnd" minTickGap={24} />
                                        <YAxis
                                            {...chartAxisProps}
                                            tickFormatter={brlTick}
                                            width={76}
                                            domain={[0, chartYDomainMax(dailyMax)]}
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
                                            dataKey="consumption"
                                            name="Consumo"
                                            fill="url(#consumptionFill)"
                                            stroke={CHART_COLORS.consumption}
                                            strokeWidth={2}
                                            dot={false}
                                            activeDot={{ r: 4, strokeWidth: 0 }}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="revenue"
                                            name="Receita"
                                            stroke={CHART_COLORS.revenue}
                                            strokeWidth={2.5}
                                            dot={{ r: 2, fill: CHART_COLORS.revenue, strokeWidth: 0 }}
                                            activeDot={{ r: 5, strokeWidth: 0 }}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="recharges"
                                            name="Recargas"
                                            stroke={CHART_COLORS.recharges}
                                            strokeWidth={2}
                                            strokeDasharray="6 4"
                                            dot={{ r: 2, fill: CHART_COLORS.recharges, strokeWidth: 0 }}
                                            activeDot={{ r: 5, strokeWidth: 0 }}
                                        />
                                    </ComposedChart>
                                </ResponsiveContainer>
                            ) : (
                                <ChartEmptyState
                                    icon={TrendingUp}
                                    title="Sem movimentação no período"
                                    description="Quando houver pagamentos ou consultas nos últimos 14 dias, o gráfico será preenchido automaticamente."
                                />
                            )}
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Top clientes por receita</CardTitle>
                            <CardDescription>
                                {hasTopClients
                                    ? `Maiores pagadores · líder com ${formatBRL(topClientsChart[0]?.total ?? 0)}`
                                    : 'Ranking acumulado de pagamentos aprovados'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-4">
                            {hasTopClients ? (
                                <ResponsiveContainer width="100%" height={300}>
                                    <BarChart
                                        data={topClientsChart}
                                        layout="vertical"
                                        margin={{ top: 4, right: 48, left: 4, bottom: 4 }}
                                        barCategoryGap="28%"
                                    >
                                        <CartesianGrid strokeDasharray="3 3" horizontal={false} className="stroke-border/60" />
                                        <XAxis
                                            type="number"
                                            {...chartAxisProps}
                                            tickFormatter={brlTick}
                                            domain={[0, chartYDomainMax(topClientsMax)]}
                                        />
                                        <YAxis
                                            type="category"
                                            dataKey="shortName"
                                            {...chartAxisProps}
                                            width={108}
                                        />
                                        <Tooltip content={<MoneyChartTooltip />} cursor={{ fill: 'hsl(var(--muted) / 0.35)' }} />
                                        <Bar dataKey="total" name="Receita" radius={[0, 6, 6, 0]} maxBarSize={28}>
                                            {topClientsChart.map((entry) => (
                                                <Cell
                                                    key={entry.name}
                                                    fill={CHART_COLORS.revenue}
                                                    fillOpacity={1 - (entry.rank - 1) * 0.12}
                                                />
                                            ))}
                                            <LabelList
                                                dataKey="total"
                                                position="right"
                                                formatter={(value) => formatBRL(Number(value))}
                                                className="fill-muted-foreground"
                                                fontSize={11}
                                            />
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            ) : (
                                <ChartEmptyState
                                    icon={BarChart3}
                                    title="Nenhum pagamento ainda"
                                    description="Assim que clientes pagarem faturas ou recargas, os maiores contribuintes aparecerão aqui."
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <QuickLink href="/admin/accounts" title="Clientes" desc="Contas, saldos e ajustes." icon={Users} />
                    <QuickLink href="/admin/plans" title="Planos" desc="Catálogo e preços." icon={Layers} />
                    <QuickLink href="/admin/providers" title="Provedores" desc="Ativação e prioridade." icon={Server} />
                </div>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b border-border py-4">
                        <CardTitle className="text-base">Consultas recentes — todos os clientes</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-6 py-3 font-medium">Cliente</th>
                                    <th className="px-6 py-3 font-medium">Tipo</th>
                                    <th className="px-6 py-3 font-medium">Provedor</th>
                                    <th className="px-6 py-3 font-medium">Status</th>
                                    <th className="px-6 py-3 text-right font-medium">Custo</th>
                                    <th className="px-6 py-3 font-medium">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent.map((c) => (
                                    <tr key={c.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                        <td className="px-6 py-3 font-medium">{c.account_name ?? '—'}</td>
                                        <td className="px-6 py-3"><Badge variant="secondary" className="uppercase">{c.query_type}</Badge></td>
                                        <td className="px-6 py-3 text-muted-foreground">{c.provider ?? '—'}</td>
                                        <td className="px-6 py-3"><ConsultationStatusBadge status={c.status} /></td>
                                        <td className="px-6 py-3 text-right font-medium">{formatBRL(c.credit_cost)}</td>
                                        <td className="px-6 py-3 text-muted-foreground">{formatDate(c.created_at)}</td>
                                    </tr>
                                ))}
                                {recent.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center text-muted-foreground">
                                            Nenhuma consulta registrada ainda.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function QuickLink({
    href,
    title,
    desc,
    icon: Icon,
}: {
    href: string;
    title: string;
    desc: string;
    icon: React.ComponentType<{ className?: string }>;
}) {
    return (
        <Link href={href} className="group">
            <Card className="gap-0 py-0 transition hover:border-brand-green/60 hover:shadow-md">
                <CardContent className="flex items-center gap-4 p-5">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-brand-green/10 text-brand-green">
                        <Icon className="size-5" />
                    </div>
                    <div className="flex-1">
                        <h3 className="font-medium">{title}</h3>
                        <p className="text-sm text-muted-foreground">{desc}</p>
                    </div>
                    <ArrowRight className="size-4 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-brand-green" />
                </CardContent>
            </Card>
        </Link>
    );
}

function formatDate(value: string): string {
    const d = new Date(value.replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('pt-BR');
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Administração', href: '/admin' }],
};
