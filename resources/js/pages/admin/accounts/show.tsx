import { Head, Link, useForm } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    CreditCard,
    KeyRound,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import {
    Area,
    CartesianGrid,
    ComposedChart,
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
} from '@/components/chart-utils';
import { ConsultationStatusBadge } from '@/components/consultation-status-badge';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL } from '@/lib/format';

interface Account {
    id: string;
    name: string;
    document: string;
    document_type: string;
    status: string;
    created_at: string | null;
}

interface WalletInfo {
    balance: number;
    reserved: number;
    available: number;
}

interface Stats {
    consultations_total: number;
    consultations_today: number;
    consultations_success: number;
    consumption_total: number;
    revenue_total: number;
    revenue_month: number;
    success_rate: number;
}

interface Subscription {
    id: string;
    status: string;
    payment_method: string | null;
    plan_name: string;
    price_cents: number;
    included_credits: number;
    next_billing_at: string | null;
    created_at: string | null;
}

interface DailyPoint {
    date: string;
    consumption: number;
    payments: number;
}

interface ApiKeyRow {
    id: string;
    name: string;
    prefix: string;
    last_four: string;
    status: string;
    last_used_at: string | null;
    expires_at: string | null;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string | null;
}

interface ConsultationRow {
    id: string;
    query_type: string;
    status: string;
    credit_cost: number;
    provider: string | null;
    created_at: string;
}

interface PaymentRow {
    id: string;
    type: string;
    method: string | null;
    status: string;
    amount_cents: number;
    created_at: string | null;
    paid_at: string | null;
}

interface CreditTxRow {
    id: string;
    type: string;
    direction: string;
    amount: number;
    balance_after: number;
    created_at: string | null;
}

interface PlanOption {
    id: string;
    name: string;
    included_credits: number;
}

interface Props {
    account: Account;
    wallet: WalletInfo;
    stats: Stats;
    subscription: Subscription | null;
    daily: DailyPoint[];
    api_keys: ApiKeyRow[];
    users: UserRow[];
    recent_consultations: ConsultationRow[];
    recent_payments: PaymentRow[];
    credit_transactions: CreditTxRow[];
    plans: PlanOption[];
}

const brlTick = (v: number) => formatBRL(v);

const chartAxisProps = {
    tickLine: false,
    axisLine: false,
    fontSize: 11,
    className: 'fill-muted-foreground',
} as const;

export default function AdminAccountShow({
    account,
    wallet,
    stats,
    subscription,
    daily,
    api_keys,
    users,
    recent_consultations,
    recent_payments,
    credit_transactions,
    plans,
}: Props) {
    usePageFlash();

    const dailyChart = useMemo(
        () => daily.map((point) => ({ ...point, label: formatChartDayLabel(point.date) })),
        [daily],
    );

    const dailyMax = useMemo(
        () => Math.max(...daily.flatMap((point) => [point.consumption, point.payments]), 0),
        [daily],
    );

    const hasDailyActivity = dailyMax > 0;

    return (
        <>
            <Head title={`Cliente — ${account.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={account.name}
                    description={`${account.document_type.toUpperCase()} ${account.document}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={`/admin/finance?account_id=${account.id}`}>
                                    <Wallet /> Financeiro
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/accounts">
                                    <ArrowLeft /> Voltar
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        className={account.status === 'active'
                            ? 'border-transparent bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                            : 'border-transparent bg-muted text-muted-foreground'}
                    >
                        {account.status === 'active' ? 'Ativa' : 'Suspensa'}
                    </Badge>
                    <Badge variant="outline" className="uppercase">{account.document_type}</Badge>
                    {account.created_at && (
                        <span className="text-sm text-muted-foreground">
                            Cliente desde {formatDate(account.created_at)}
                        </span>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Disponível" value={formatBRL(wallet.available)} icon={CreditCard} highlight />
                    <StatCard label="Saldo" value={formatBRL(wallet.balance)} icon={Wallet} hint={`Reservado: ${formatBRL(wallet.reserved)}`} />
                    <StatCard label="Receita total" value={formatBRL(stats.revenue_total)} icon={TrendingUp} hint={`${formatBRL(stats.revenue_month)} no mês`} />
                    <StatCard
                        label="Consultas"
                        value={stats.consultations_total}
                        icon={Activity}
                        hint={`${stats.consultations_today} hoje · ${stats.success_rate}% sucesso`}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-0 py-0 lg:col-span-2">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Consumo e pagamentos</CardTitle>
                            <CardDescription>
                                Últimos 14 dias · consumo {formatBRL(stats.consumption_total)} no total
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-4">
                            {hasDailyActivity ? (
                                <ResponsiveContainer width="100%" height={280}>
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
                                        <Legend verticalAlign="top" align="right" iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 12, paddingBottom: 8 }} />
                                        <Area
                                            type="monotone"
                                            dataKey="consumption"
                                            name="Consumo"
                                            fill="url(#consumptionFill)"
                                            stroke={CHART_COLORS.consumption}
                                            strokeWidth={2}
                                            dot={false}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="payments"
                                            name="Pagamentos"
                                            stroke={CHART_COLORS.revenue}
                                            strokeWidth={2.5}
                                            dot={{ r: 2, fill: CHART_COLORS.revenue, strokeWidth: 0 }}
                                        />
                                    </ComposedChart>
                                </ResponsiveContainer>
                            ) : (
                                <ChartEmptyState
                                    icon={Activity}
                                    title="Sem atividade recente"
                                    description="Consultas e pagamentos deste cliente aparecerão aqui nos últimos 14 dias."
                                />
                            )}
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <AccountOpsCard accountId={account.id} wallet={wallet} plans={plans} />
                        <SubscriptionCard subscription={subscription} />
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <ApiKeysCard apiKeys={api_keys} />
                    <UsersCard users={users} />
                </div>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b border-border py-4">
                        <CardTitle className="text-base">Consultas recentes</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <DataTable
                            empty="Nenhuma consulta registrada para este cliente."
                            headers={['Tipo', 'Provedor', 'Status', 'Custo', 'Data']}
                            rows={recent_consultations.map((c) => [
                                <Badge key="type" variant="secondary" className="uppercase">{c.query_type}</Badge>,
                                c.provider ?? '—',
                                <ConsultationStatusBadge key="status" status={c.status} />,
                                <span key="cost" className="font-medium tabular-nums">{formatBRL(c.credit_cost)}</span>,
                                formatDate(c.created_at),
                            ])}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Pagamentos recentes</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <DataTable
                                empty="Nenhum pagamento registrado."
                                headers={['Tipo', 'Status', 'Valor', 'Data']}
                                rows={recent_payments.map((p) => [
                                    paymentTypeLabel(p.type),
                                    <PaymentStatusBadge key="status" status={p.status} />,
                                    <span key="amount" className="font-medium tabular-nums">{formatBRL(p.amount_cents)}</span>,
                                    formatDate(p.paid_at ?? p.created_at),
                                ])}
                            />
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Movimentações de crédito</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <DataTable
                                empty="Nenhuma movimentação na carteira."
                                headers={['Tipo', 'Valor', 'Saldo após', 'Data']}
                                rows={credit_transactions.map((tx) => [
                                    creditTypeLabel(tx.type),
                                    <span
                                        key="amount"
                                        className={`font-medium tabular-nums ${tx.direction === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}
                                    >
                                        {tx.direction === 'credit' ? '+' : '-'}{formatBRL(tx.amount)}
                                    </span>,
                                    formatBRL(tx.balance_after),
                                    formatDate(tx.created_at),
                                ])}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function AccountOpsCard({
    accountId,
    wallet,
    plans,
}: {
    accountId: string;
    wallet: WalletInfo;
    plans: PlanOption[];
}) {
    const adjust = useForm<{ delta: string; reason: string }>({ delta: '', reason: '' });
    const assign = useForm<{ plan_id: string }>({ plan_id: plans[0]?.id ?? '' });

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b border-border py-4">
                <CardTitle className="text-base">Operações</CardTitle>
                <CardDescription>
                    Disponível {formatBRL(wallet.available)} · Saldo {formatBRL(wallet.balance)}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 p-4">
                <div className="space-y-3">
                    <h4 className="text-sm font-medium">Ajustar saldo</h4>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            adjust.transform((d) => ({ ...d, delta: Math.round(Number(d.delta) * 100) }));
                            adjust.post(`/admin/accounts/${accountId}/adjust`, { preserveScroll: true, onSuccess: () => adjust.reset() });
                        }}
                        className="space-y-3"
                    >
                        <Input
                            type="number"
                            step="0.01"
                            placeholder="Valor em R$ (+ ou -)"
                            value={adjust.data.delta}
                            onChange={(e) => adjust.setData('delta', e.target.value)}
                            required
                        />
                        <Input
                            placeholder="Motivo (auditoria)"
                            value={adjust.data.reason}
                            onChange={(e) => adjust.setData('reason', e.target.value)}
                            required
                        />
                        <Button type="submit" size="sm" disabled={adjust.processing} className="w-full">
                            Aplicar ajuste
                        </Button>
                    </form>
                </div>

                <Separator />

                <div className="space-y-3">
                    <h4 className="text-sm font-medium">Atribuir plano</h4>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            assign.post(`/admin/accounts/${accountId}/assign-plan`, { preserveScroll: true });
                        }}
                        className="space-y-3"
                    >
                        <Select value={assign.data.plan_id} onValueChange={(v) => assign.setData('plan_id', v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione um plano" />
                            </SelectTrigger>
                            <SelectContent>
                                {plans.map((plan) => (
                                    <SelectItem key={plan.id} value={plan.id}>
                                        {plan.name} · {formatBRL(plan.included_credits)}/ciclo
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button type="submit" variant="secondary" size="sm" disabled={plans.length === 0 || assign.processing} className="w-full">
                            Atribuir plano
                        </Button>
                    </form>
                    {plans.length === 0 && (
                        <p className="text-xs text-muted-foreground">Nenhum plano ativo cadastrado.</p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function SubscriptionCard({ subscription }: { subscription: Subscription | null }) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b border-border py-4">
                <CardTitle className="text-base">Assinatura</CardTitle>
            </CardHeader>
            <CardContent className="p-4">
                {subscription ? (
                    <dl className="space-y-3 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Plano</dt>
                            <dd className="font-medium">{subscription.plan_name}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Status</dt>
                            <dd><Badge variant="secondary">{subscriptionStatusLabel(subscription.status)}</Badge></dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Valor</dt>
                            <dd className="font-medium tabular-nums">{formatBRL(subscription.price_cents)}/mês</dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Créditos/ciclo</dt>
                            <dd className="tabular-nums">{formatBRL(subscription.included_credits)}</dd>
                        </div>
                        {subscription.next_billing_at && (
                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-muted-foreground">Próxima cobrança</dt>
                                <dd>{formatDate(subscription.next_billing_at)}</dd>
                            </div>
                        )}
                    </dl>
                ) : (
                    <p className="text-sm text-muted-foreground">Este cliente não possui assinatura ativa.</p>
                )}
            </CardContent>
        </Card>
    );
}

function ApiKeysCard({ apiKeys }: { apiKeys: ApiKeyRow[] }) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b border-border py-4">
                <CardTitle className="flex items-center gap-2 text-base">
                    <KeyRound className="size-4" /> Chaves de API
                </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                {apiKeys.length === 0 ? (
                    <p className="px-6 py-8 text-center text-sm text-muted-foreground">Nenhuma chave emitida.</p>
                ) : (
                    <ul className="divide-y divide-border">
                        {apiKeys.map((key) => (
                            <li key={key.id} className="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                <div>
                                    <p className="font-medium">{key.name}</p>
                                    <p className="font-mono text-xs text-muted-foreground">
                                        {key.prefix}…{key.last_four}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <Badge variant={key.status === 'active' ? 'outline' : 'secondary'}>
                                        {key.status === 'active' ? 'Ativa' : 'Revogada'}
                                    </Badge>
                                    {key.last_used_at && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Usada {formatDate(key.last_used_at)}
                                        </p>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function UsersCard({ users }: { users: UserRow[] }) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b border-border py-4">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Users className="size-4" /> Usuários vinculados
                </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                {users.length === 0 ? (
                    <p className="px-6 py-8 text-center text-sm text-muted-foreground">Nenhum usuário vinculado.</p>
                ) : (
                    <ul className="divide-y divide-border">
                        {users.map((user) => (
                            <li key={user.id} className="flex items-center gap-3 px-6 py-3">
                                <Avatar className="size-9">
                                    <AvatarFallback className="bg-brand-green/10 text-xs font-medium text-brand-green">
                                        {initials(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{user.name}</p>
                                    <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                                </div>
                                <Badge variant="outline" className="shrink-0 capitalize">{user.role}</Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function DataTable({
    headers,
    rows,
    empty,
}: {
    headers: string[];
    rows: React.ReactNode[][];
    empty: string;
}) {
    if (rows.length === 0) {
        return <p className="px-6 py-12 text-center text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <table className="w-full text-sm">
            <thead className="text-left text-muted-foreground">
                <tr className="border-b border-border">
                    {headers.map((header) => (
                        <th key={header} className="px-6 py-3 font-medium">{header}</th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((cells, index) => (
                    <tr key={index} className="border-b border-border last:border-0 hover:bg-muted/40">
                        {cells.map((cell, cellIndex) => (
                            <td key={cellIndex} className="px-6 py-3 text-muted-foreground">{cell}</td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function PaymentStatusBadge({ status }: { status: string }) {
    const styles: Record<string, string> = {
        approved: 'border-transparent bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
        pending: 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        rejected: 'border-transparent bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    };

    const labels: Record<string, string> = {
        approved: 'Aprovado',
        pending: 'Pendente',
        rejected: 'Rejeitado',
    };

    return (
        <Badge variant="outline" className={styles[status] ?? ''}>
            {labels[status] ?? status}
        </Badge>
    );
}

function paymentTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        subscription: 'Assinatura',
        topup: 'Recarga',
        invoice: 'Fatura',
    };

    return labels[type] ?? type;
}

function creditTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        topup: 'Recarga',
        consumption: 'Consumo',
        refund: 'Estorno',
        adjustment: 'Ajuste',
        reservation: 'Reserva',
        release: 'Liberação',
        plan_grant: 'Plano',
    };

    return labels[type] ?? type;
}

function subscriptionStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        active: 'Ativa',
        paused: 'Pausada',
        cancelled: 'Cancelada',
        pending: 'Pendente',
    };

    return labels[status] ?? status;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const normalized = value.includes('T') ? value : `${value}T12:00:00`;
    const date = new Date(normalized);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('pt-BR');
}

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

AdminAccountShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Clientes', href: '/admin/accounts' },
        { title: props.account.name, href: `/admin/accounts/${props.account.id}` },
    ],
});
