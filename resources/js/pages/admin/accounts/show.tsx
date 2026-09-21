import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Check,
    Copy,
    CreditCard,
    KeyRound,
    Pencil,
    QrCode,
    Receipt,
    TrendingUp,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import {
    Area,
    CartesianGrid,
    ComposedChart,
    Line,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    CHART_COLORS,
    ChartEmptyState,
    ChartLegend,
    ChartResponsiveShell,
    MoneyChartTooltip,
    chartYDomainMax,
    formatChartDayLabel,
    moneyChartLayout,
} from '@/components/chart-utils';
import { ConsultationStatusBadge } from '@/components/consultation-status-badge';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useIsMobile } from '@/hooks/use-mobile';
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatDate, formatDateTime } from '@/lib/datetime';
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
    phone: string | null;
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
    /** Presente só para PIX ainda pendente (reabrível pelo admin). */
    pix: { qr_code: string; qr_code_base64: string | null } | null;
}

interface OpenInvoiceRow {
    id: string;
    number: string | null;
    status: string;
    amount_cents: number;
    description: string | null;
    due_date: string | null;
}

/** Cobrança PIX exibida no diálogo (recém-gerada via flash ou reaberta da tabela). */
interface PixCharge {
    id: string;
    status: string;
    amount_cents: number;
    qr_code: string | null;
    qr_code_base64: string | null;
    expires_at: string | null;
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
    price_cents: number;
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
    open_invoices: OpenInvoiceRow[];
    payer_email: string | null;
}

const brlTick = (v: number) => formatBRL(v);

/** Props recarregadas quando um PIX é pago (saldo, assinatura, faturas…). */
const BILLING_PROPS = [
    'wallet',
    'stats',
    'subscription',
    'daily',
    'recent_payments',
    'credit_transactions',
    'open_invoices',
];

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
    open_invoices,
    payer_email,
}: Props) {
    usePageFlash();
    const isMobile = useIsMobile();
    const chartLayout = moneyChartLayout(isMobile);

    // Email do pagador compartilhado por todas as cobranças PIX desta tela.
    const [payerEmail, setPayerEmail] = useState(payer_email ?? '');

    // Diálogo do PIX: abre sozinho com a cobrança recém-gerada (flash da sessão)
    // ou quando o admin reabre um PIX pendente pela tabela de pagamentos.
    const { flash, errors } = usePage<{
        flash?: { payment?: PixCharge | null };
        errors?: Record<string, string>;
    }>().props;
    const flashPayment = flash?.payment ?? null;
    const [reopenedCharge, setReopenedCharge] = useState<PixCharge | null>(null);
    const [dismissedChargeId, setDismissedChargeId] = useState<string | null>(null);
    const pixCharge =
        reopenedCharge ?? (flashPayment && flashPayment.id !== dismissedChargeId ? flashPayment : null);

    const closePixDialog = () => {
        setReopenedCharge(null);
        setDismissedChargeId(flashPayment?.id ?? null);
    };

    const hasActiveSubscription =
        subscription !== null && ['active', 'past_due'].includes(subscription.status);

    const dailyChart = useMemo(
        () => daily.map((point) => ({ ...point, label: formatChartDayLabel(point.date) })),
        [daily],
    );

    const consumptionMax = useMemo(
        () => Math.max(...daily.map((point) => point.consumption), 0),
        [daily],
    );

    const paymentsMax = useMemo(
        () => Math.max(...daily.map((point) => point.payments), 0),
        [daily],
    );

    const hasDailyActivity = consumptionMax > 0 || paymentsMax > 0;

    return (
        <>
            <Head title={`Cliente — ${account.name}`} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={account.name}
                    description={`${account.document_type.toUpperCase()} ${account.document}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <EditAccountDialog account={account} />
                            <Button variant="outline" size="sm" asChild>
                                <Link href={`/admin/logs?account_id=${account.id}`}>
                                    <KeyRound /> Logs de API
                                </Link>
                            </Button>
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

                <div className="grid min-w-0 gap-6 lg:grid-cols-3">
                    <Card className="min-w-0 gap-0 py-0 lg:col-span-2">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Consumo e pagamentos</CardTitle>
                            <CardDescription>
                                Últimos 14 dias · consumo {formatBRL(stats.consumption_total)} no total
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="min-w-0 p-4">
                            {hasDailyActivity ? (
                                <>
                                    <ChartLegend
                                        className="mb-3"
                                        items={[
                                            { label: 'Consumo', color: CHART_COLORS.consumption },
                                            { label: 'Pagamentos', color: CHART_COLORS.revenue },
                                        ]}
                                    />
                                    <ChartResponsiveShell height={chartLayout.height}>
                                        {({ width, height }) => (
                                            <ComposedChart
                                                width={width}
                                                height={height}
                                                data={dailyChart}
                                                margin={chartLayout.margin}
                                            >
                                                        <defs>
                                                            <linearGradient id="accountConsumptionFill" x1="0" y1="0" x2="0" y2="1">
                                                                <stop offset="0%" stopColor={CHART_COLORS.consumption} stopOpacity={0.35} />
                                                                <stop offset="100%" stopColor={CHART_COLORS.consumption} stopOpacity={0.02} />
                                                            </linearGradient>
                                                        </defs>
                                                        <CartesianGrid strokeDasharray="3 3" vertical={false} className="stroke-border/60" />
                                                        <XAxis
                                                            dataKey="label"
                                                            tickLine={false}
                                                            axisLine={false}
                                                            fontSize={chartLayout.tickFontSize}
                                                            className="fill-muted-foreground"
                                                            interval="preserveStartEnd"
                                                            minTickGap={chartLayout.minTickGap}
                                                            angle={chartLayout.xAxisAngle}
                                                            textAnchor={chartLayout.xAxisTextAnchor}
                                                            height={chartLayout.xAxisHeight}
                                                        />
                                                        <YAxis
                                                            yAxisId="consumption"
                                                            orientation="left"
                                                            tickLine={false}
                                                            axisLine={false}
                                                            fontSize={chartLayout.tickFontSize}
                                                            className="fill-muted-foreground"
                                                            tickFormatter={brlTick}
                                                            width={chartLayout.yAxisWidth}
                                                            domain={[0, chartYDomainMax(consumptionMax)]}
                                                            stroke={CHART_COLORS.consumption}
                                                        />
                                                        <YAxis
                                                            yAxisId="payments"
                                                            orientation="right"
                                                            tickLine={false}
                                                            axisLine={false}
                                                            fontSize={chartLayout.tickFontSize}
                                                            className="fill-muted-foreground"
                                                            tickFormatter={brlTick}
                                                            width={chartLayout.yAxisWidth}
                                                            domain={[0, chartYDomainMax(paymentsMax)]}
                                                            stroke={CHART_COLORS.revenue}
                                                        />
                                                        <Tooltip content={<MoneyChartTooltip />} />
                                                        <Area
                                                            yAxisId="consumption"
                                                            type="monotone"
                                                            dataKey="consumption"
                                                            name="Consumo"
                                                            fill="url(#accountConsumptionFill)"
                                                            stroke={CHART_COLORS.consumption}
                                                            strokeWidth={2}
                                                            dot={false}
                                                        />
                                                        <Line
                                                            yAxisId="payments"
                                                            type="monotone"
                                                            dataKey="payments"
                                                            name="Pagamentos"
                                                            stroke={CHART_COLORS.revenue}
                                                            strokeWidth={2.5}
                                                            dot={{ r: 2, fill: CHART_COLORS.revenue, strokeWidth: 0 }}
                                                        />
                                                    </ComposedChart>
                                        )}
                                    </ChartResponsiveShell>
                                </>
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
                        <AccountOpsCard
                            accountId={account.id}
                            wallet={wallet}
                            plans={plans}
                            hasActiveSubscription={hasActiveSubscription}
                            payerEmail={payerEmail}
                            onPayerEmailChange={setPayerEmail}
                            payerEmailError={errors?.payer_email}
                        />
                        <SubscriptionCard accountId={account.id} subscription={subscription} />
                    </div>
                </div>

                <OpenInvoicesCard accountId={account.id} invoices={open_invoices} payerEmail={payerEmail} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <ApiKeysCard apiKeys={api_keys} />
                    <UsersCard accountId={account.id} users={users} />
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
                                formatDateTime(c.created_at),
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
                                headers={['Tipo', 'Status', 'Valor', 'Data', '']}
                                rows={recent_payments.map((p) => [
                                    paymentTypeLabel(p.type),
                                    <PaymentStatusBadge key="status" status={p.status} />,
                                    <span key="amount" className="font-medium tabular-nums">{formatBRL(p.amount_cents)}</span>,
                                    formatDate(p.paid_at ?? p.created_at),
                                    p.pix ? (
                                        <Button
                                            key="pix"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setReopenedCharge({
                                                    id: p.id,
                                                    status: p.status,
                                                    amount_cents: p.amount_cents,
                                                    qr_code: p.pix?.qr_code ?? null,
                                                    qr_code_base64: p.pix?.qr_code_base64 ?? null,
                                                    expires_at: null,
                                                })
                                            }
                                        >
                                            <QrCode /> Ver PIX
                                        </Button>
                                    ) : null,
                                ])}
                            />
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Movimentações da carteira</CardTitle>
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

            <PixChargeDialog accountId={account.id} charge={pixCharge} onClose={closePixDialog} />
        </>
    );
}

type PlanBilling = 'pix' | 'courtesy';

function AccountOpsCard({
    accountId,
    wallet,
    plans,
    hasActiveSubscription,
    payerEmail,
    onPayerEmailChange,
    payerEmailError,
}: {
    accountId: string;
    wallet: WalletInfo;
    plans: PlanOption[];
    hasActiveSubscription: boolean;
    payerEmail: string;
    onPayerEmailChange: (email: string) => void;
    payerEmailError?: string;
}) {
    const adjust = useForm<{ delta: string; reason: string }>({ delta: '', reason: '' });
    const assign = useForm<{ plan_id: string; billing: PlanBilling }>({
        plan_id: plans[0]?.id ?? '',
        billing: 'pix',
    });
    const topup = useForm<{ amount: string }>({ amount: '' });

    const selectedPlan = plans.find((plan) => plan.id === assign.data.plan_id);
    const pixBlocked = assign.data.billing === 'pix' && hasActiveSubscription;

    const submitAssign = (e: FormEvent) => {
        e.preventDefault();

        if (assign.data.billing === 'pix') {
            // Assinatura + 1ª fatura + PIX: o saldo só entra após o pagamento.
            assign.transform((d) => ({ plan_id: d.plan_id, payer_email: payerEmail || null }));
            assign.post(`/admin/accounts/${accountId}/charges/plan`, { preserveScroll: true });

            return;
        }

        assign.transform((d) => ({ plan_id: d.plan_id }));
        assign.post(`/admin/accounts/${accountId}/assign-plan`, { preserveScroll: true });
    };

    const submitTopup = (e: FormEvent) => {
        e.preventDefault();
        topup.transform((d) => ({ amount: Number(d.amount), payer_email: payerEmail || null }));
        topup.post(`/admin/accounts/${accountId}/charges/topup`, {
            preserveScroll: true,
            onSuccess: () => topup.reset(),
        });
    };

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
                    <form onSubmit={submitAssign} className="space-y-3">
                        <Select value={assign.data.plan_id} onValueChange={(v) => assign.setData('plan_id', v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione um plano" />
                            </SelectTrigger>
                            <SelectContent>
                                {plans.map((plan) => (
                                    <SelectItem key={plan.id} value={plan.id}>
                                        {plan.name} · {formatBRL(plan.price_cents)}/mês · saldo {formatBRL(plan.included_credits)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={assign.data.billing} onValueChange={(v) => assign.setData('billing', v as PlanBilling)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pix">Cobrar via PIX</SelectItem>
                                <SelectItem value="courtesy">Cortesia (sem cobrança)</SelectItem>
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            {assign.data.billing === 'pix'
                                ? `Cria a assinatura e gera o PIX${selectedPlan ? ` de ${formatBRL(selectedPlan.price_cents)}` : ''} para enviar ao cliente. O saldo entra após o pagamento e as renovações viram faturas mensais.`
                                : 'Libera o saldo do plano agora, sem cobrar e sem renovação automática.'}
                        </p>
                        {assign.errors.plan_id && <p className="text-sm text-destructive">{assign.errors.plan_id}</p>}
                        {pixBlocked && (
                            <p className="text-xs text-amber-600 dark:text-amber-400">
                                O cliente já tem assinatura ativa. Cancele-a no card “Assinatura” para atribuir outro plano com cobrança.
                            </p>
                        )}
                        <Button
                            type="submit"
                            variant="secondary"
                            size="sm"
                            disabled={plans.length === 0 || assign.processing || pixBlocked}
                            className="w-full"
                        >
                            {assign.data.billing === 'pix' ? (
                                <>
                                    <QrCode /> Atribuir e gerar PIX
                                </>
                            ) : (
                                'Atribuir plano'
                            )}
                        </Button>
                    </form>
                    {plans.length === 0 && (
                        <p className="text-xs text-muted-foreground">Nenhum plano ativo cadastrado.</p>
                    )}
                </div>

                <Separator />

                <div className="space-y-3">
                    <h4 className="text-sm font-medium">Gerar PIX de recarga</h4>
                    <form onSubmit={submitTopup} className="space-y-3">
                        <Input
                            type="number"
                            min={1}
                            step="0.01"
                            placeholder="Valor em R$"
                            value={topup.data.amount}
                            onChange={(e) => topup.setData('amount', e.target.value)}
                            required
                        />
                        {topup.errors.amount && <p className="text-sm text-destructive">{topup.errors.amount}</p>}
                        <Button type="submit" variant="secondary" size="sm" disabled={topup.processing} className="w-full">
                            <QrCode /> Gerar PIX
                        </Button>
                    </form>
                </div>

                <Separator />

                <div className="space-y-2">
                    <Label htmlFor="pix-payer-email">Email do pagador (PIX)</Label>
                    <Input
                        id="pix-payer-email"
                        type="email"
                        placeholder="financeiro@cliente.com.br"
                        value={payerEmail}
                        onChange={(e) => onPayerEmailChange(e.target.value)}
                    />
                    {payerEmailError ? (
                        <p className="text-sm text-destructive">{payerEmailError}</p>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            Usado nas cobranças PIX desta tela. Em branco, vale o email do primeiro usuário da conta.
                        </p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function SubscriptionCard({ accountId, subscription }: { accountId: string; subscription: Subscription | null }) {
    const cancelable = subscription !== null && ['active', 'past_due'].includes(subscription.status);

    const cancel = () => {
        if (!subscription || !confirm('Cancelar a assinatura deste cliente? A recorrência será encerrada.')) {
            return;
        }

        router.post(
            `/admin/accounts/${accountId}/subscriptions/${subscription.id}/cancel`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="flex flex-row items-center justify-between border-b border-border py-4">
                <CardTitle className="text-base">Assinatura</CardTitle>
                {cancelable && (
                    <Button
                        variant="outline"
                        size="sm"
                        className="text-destructive hover:border-destructive/40 hover:bg-destructive/5 hover:text-destructive"
                        onClick={cancel}
                    >
                        Cancelar
                    </Button>
                )}
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
                            <dt className="text-muted-foreground">Saldo/ciclo</dt>
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

function OpenInvoicesCard({
    accountId,
    invoices,
    payerEmail,
}: {
    accountId: string;
    invoices: OpenInvoiceRow[];
    payerEmail: string;
}) {
    const [generatingId, setGeneratingId] = useState<string | null>(null);

    const generatePix = (invoiceId: string) => {
        router.post(
            `/admin/accounts/${accountId}/charges/invoices/${invoiceId}`,
            { payer_email: payerEmail || null },
            {
                preserveScroll: true,
                onStart: () => setGeneratingId(invoiceId),
                onFinish: () => setGeneratingId(null),
            },
        );
    };

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b border-border py-4">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Receipt className="size-4" /> Faturas em aberto
                </CardTitle>
                <CardDescription>
                    Gere um PIX para o cliente pagar — útil quando o PIX anterior expirou ou na renovação do plano.
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                <DataTable
                    empty="Nenhuma fatura em aberto."
                    headers={['Fatura', 'Vencimento', 'Status', 'Valor', '']}
                    rows={invoices.map((invoice) => [
                        <div key="invoice">
                            <p className="font-medium text-foreground">{invoice.description ?? 'Fatura'}</p>
                            {invoice.number && <p className="font-mono text-xs">{invoice.number}</p>}
                        </div>,
                        invoice.due_date ? formatDate(`${invoice.due_date}T12:00:00`) : '—',
                        <Badge
                            key="status"
                            variant="outline"
                            className={invoice.status === 'overdue'
                                ? 'border-transparent bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
                                : 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'}
                        >
                            {invoice.status === 'overdue' ? 'Vencida' : 'Em aberto'}
                        </Badge>,
                        <span key="amount" className="font-medium tabular-nums">{formatBRL(invoice.amount_cents)}</span>,
                        <Button
                            key="pix"
                            variant="outline"
                            size="sm"
                            disabled={generatingId !== null}
                            onClick={() => generatePix(invoice.id)}
                        >
                            <QrCode /> Gerar PIX
                        </Button>,
                    ])}
                />
            </CardContent>
        </Card>
    );
}

const FINAL_PAYMENT_STATUSES = ['approved', 'rejected', 'cancelled', 'refunded'];

function PixChargeDialog({
    accountId,
    charge,
    onClose,
}: {
    accountId: string;
    charge: PixCharge | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={charge !== null}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                {charge && <PixChargeContent key={charge.id} accountId={accountId} charge={charge} />}
            </DialogContent>
        </Dialog>
    );
}

function PixChargeContent({ accountId, charge }: { accountId: string; charge: PixCharge }) {
    const [status, setStatus] = useState(charge.status);
    const [copied, setCopied] = useState(false);
    const isFinal = FINAL_PAYMENT_STATUSES.includes(status);

    // Reconsulta o pagamento no gateway ao abrir e enquanto o diálogo está
    // aberto: PIX vencido vira "cancelled"; aprovado, o backend liquida
    // (saldo/fatura) e a tela recarrega os dados financeiros.
    useEffect(() => {
        if (isFinal) {
            return;
        }

        let active = true;

        const check = async () => {
            try {
                const res = await fetch(`/admin/accounts/${accountId}/charges/${charge.id}/status`, {
                    headers: { Accept: 'application/json' },
                });

                if (!res.ok || !active) {
                    return;
                }

                const data = (await res.json()) as { status: string };

                if (!active) {
                    return;
                }

                setStatus(data.status);

                if (FINAL_PAYMENT_STATUSES.includes(data.status)) {
                    router.reload({ only: BILLING_PROPS });
                }
            } catch {
                /* ignora falha de polling */
            }
        };

        void check();
        const timer = setInterval(check, 6000);

        return () => {
            active = false;
            clearInterval(timer);
        };
    }, [accountId, charge.id, isFinal]);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(charge.qr_code ?? '');
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            /* clipboard indisponível */
        }
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>PIX de {formatBRL(charge.amount_cents)}</DialogTitle>
                <DialogDescription>
                    Envie o QR Code ou o código copia e cola ao cliente. O saldo entra automaticamente
                    após a confirmação do pagamento.
                </DialogDescription>
            </DialogHeader>

            {status === 'approved' ? (
                <div className="flex items-center gap-2 rounded-md border border-brand-green/40 bg-brand-green/5 p-4 text-sm font-medium text-brand-green">
                    <Check className="size-4" /> Pagamento confirmado! O saldo do cliente já foi atualizado.
                </div>
            ) : isFinal ? (
                <div className="rounded-md border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
                    Este PIX expirou ou foi cancelado. Gere uma nova cobrança para o cliente.
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="flex items-center justify-between gap-2">
                        <Badge variant="outline" className="border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            Aguardando pagamento
                        </Badge>
                        {charge.expires_at && (
                            <span className="text-xs text-muted-foreground">
                                Válido até {formatDateTime(charge.expires_at)}
                            </span>
                        )}
                    </div>

                    {charge.qr_code_base64 && (
                        <img
                            src={`data:image/png;base64,${charge.qr_code_base64}`}
                            alt="QR Code PIX"
                            className="mx-auto size-48 rounded-md border border-border bg-white p-2"
                        />
                    )}

                    <div className="space-y-2">
                        <Label>PIX copia e cola</Label>
                        <div className="flex items-center gap-2">
                            <code className="min-w-0 flex-1 overflow-x-auto rounded-md border border-border bg-muted/40 px-3 py-2 font-mono text-xs whitespace-nowrap">
                                {charge.qr_code}
                            </code>
                            <Button type="button" variant="outline" size="sm" onClick={copy} aria-label="Copiar código PIX">
                                {copied ? <Check className="text-brand-green" /> : <Copy />}
                            </Button>
                        </div>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Pode fechar esta janela: o pagamento é confirmado automaticamente e o PIX pendente
                        fica disponível em “Pagamentos recentes” → Ver PIX.
                    </p>
                </div>
            )}
        </>
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

function EditAccountDialog({ account }: { account: Account }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: account.name, status: account.status });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/accounts/${account.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    form.setData({ name: account.name, status: account.status });
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm"><Pencil /> Editar cliente</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Editar cliente</DialogTitle>
                    <DialogDescription>
                        {account.document_type.toUpperCase()} {account.document} — o documento não pode ser alterado.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="edit-acc-name">Nome / Razão social</Label>
                        <Input
                            id="edit-acc-name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            required
                        />
                        {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Ativa</SelectItem>
                                <SelectItem value="suspended">Suspensa</SelectItem>
                            </SelectContent>
                        </Select>
                        {form.errors.status && <p className="text-sm text-destructive">{form.errors.status}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>Salvar alterações</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function UsersCard({ accountId, users }: { accountId: string; users: UserRow[] }) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="flex flex-row items-center justify-between border-b border-border py-4">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Users className="size-4" /> Usuários vinculados
                </CardTitle>
                <CreateUserDialog accountId={accountId} />
            </CardHeader>
            <CardContent className="p-0">
                {users.length === 0 ? (
                    <p className="px-6 py-8 text-center text-sm text-muted-foreground">
                        Nenhum usuário vinculado. Crie um acesso para o cliente entrar no painel.
                    </p>
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
                                <EditUserDialog accountId={accountId} user={user} />
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

interface UserFormData {
    name: string;
    email: string;
    phone: string;
    password: string;
    [key: string]: string;
}

function CreateUserDialog({ accountId }: { accountId: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm<UserFormData>({ name: '', email: '', phone: '', password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/accounts/${accountId}/users`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm"><UserPlus /> Novo usuário</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Novo usuário de acesso</DialogTitle>
                    <DialogDescription>
                        Cria o login (email e senha) com que o cliente acessa o painel.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <UserFormFields form={form} idPrefix="create-user" passwordLabel="Senha" passwordRequired />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>Criar usuário</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditUserDialog({ accountId, user }: { accountId: string; user: UserRow }) {
    const [open, setOpen] = useState(false);
    const form = useForm<UserFormData>({ name: user.name, email: user.email, phone: user.phone ?? '', password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/accounts/${accountId}/users/${user.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                form.setData('password', '');
                setOpen(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    form.setData({ name: user.name, email: user.email, phone: user.phone ?? '', password: '' });
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" className="shrink-0" aria-label={`Editar ${user.name}`}>
                    <Pencil />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Editar usuário</DialogTitle>
                    <DialogDescription>
                        Altere os dados de acesso. Deixe a senha em branco para mantê-la.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <UserFormFields form={form} idPrefix={`edit-user-${user.id}`} passwordLabel="Nova senha (opcional)" />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>Salvar alterações</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function UserFormFields({
    form,
    idPrefix,
    passwordLabel,
    passwordRequired = false,
}: {
    form: ReturnType<typeof useForm<UserFormData>>;
    idPrefix: string;
    passwordLabel: string;
    passwordRequired?: boolean;
}) {
    return (
        <>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-name`}>Nome</Label>
                <Input
                    id={`${idPrefix}-name`}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
            </div>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-email`}>Email</Label>
                <Input
                    id={`${idPrefix}-email`}
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    required
                />
                {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
            </div>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-phone`}>Telefone (opcional)</Label>
                <Input
                    id={`${idPrefix}-phone`}
                    value={form.data.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                    placeholder="(11) 99999-9999"
                />
                {form.errors.phone && <p className="text-sm text-destructive">{form.errors.phone}</p>}
            </div>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-password`}>{passwordLabel}</Label>
                <Input
                    id={`${idPrefix}-password`}
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    required={passwordRequired}
                />
                {form.errors.password && <p className="text-sm text-destructive">{form.errors.password}</p>}
            </div>
        </>
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
        in_process: 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        rejected: 'border-transparent bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
        cancelled: 'border-transparent bg-muted text-muted-foreground',
        refunded: 'border-transparent bg-muted text-muted-foreground',
    };

    const labels: Record<string, string> = {
        approved: 'Aprovado',
        pending: 'Pendente',
        in_process: 'Aguardando',
        rejected: 'Rejeitado',
        cancelled: 'Cancelado',
        refunded: 'Estornado',
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
        past_due: 'Em atraso',
        paused: 'Pausada',
        cancelled: 'Cancelada',
        pending: 'Pendente',
    };

    return labels[status] ?? status;
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
