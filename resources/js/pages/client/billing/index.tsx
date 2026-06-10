import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Check,
    Copy,
    CreditCard,
    FileText,
    Lock,
    QrCode,
    Receipt,
    Repeat,
    ShieldCheck,
    Sparkles,
    Wallet,
    Zap,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL } from '@/lib/format';
import { cn } from '@/lib/utils';

type Method = 'pix' | 'boleto' | 'credit_card';

interface WalletInfo {
    balance: number;
    reserved: number;
    available: number;
}

interface Invoice {
    id: string;
    status: string;
    amount_cents: number;
    description: string | null;
    due_date: string | null;
}

interface Subscription {
    id: string;
    plan_id: string;
    plan_name: string | null;
    status: string;
    payment_method: string | null;
    next_billing_at: string | null;
}

interface Transaction {
    type: string;
    direction: string;
    amount_cents: number;
    created_at: string | null;
}

interface PaymentRow {
    id: string;
    type: string;
    method: string;
    status: string;
    amount_cents: number;
    created_at: string | null;
}

interface PlanRow {
    id: string;
    name: string;
    price_cents: number;
    recharge_cents: number;
}

interface PendingPayment {
    id: string;
    type: string;
    method: Method;
    status: string;
    amount_cents: number;
    qr_code: string | null;
    qr_code_base64: string | null;
    ticket_url: string | null;
    barcode: string | null;
    expires_at: string | null;
}

interface PageProps {
    wallet: WalletInfo | null;
    invoices: Invoice[];
    subscription: Subscription | null;
    transactions: Transaction[];
    payments: PaymentRow[];
    plans: PlanRow[];
    mp_public_key: string;
    flash?: { payment?: PendingPayment };
    [key: string]: unknown;
}

export default function ClientBillingIndex() {
    usePageFlash();
    const { wallet, invoices, subscription, transactions, payments, plans, flash } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Financeiro" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Financeiro"
                    description="Gerencie seu saldo, faturas e assinatura."
                    actions={<TopupDialog />}
                />

                {wallet && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatCard label="Saldo disponível" value={formatBRL(wallet.available)} icon={Wallet} highlight />
                        <StatCard label="Saldo total" value={formatBRL(wallet.balance)} icon={Receipt} />
                        <StatCard label="Reservado" value={formatBRL(wallet.reserved)} icon={Lock} />
                    </div>
                )}

                {flash?.payment && <PaymentResult payment={flash.payment} />}

                <SubscriptionCard subscription={subscription} plans={plans} />

                <InvoicesCard invoices={invoices} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <HistoryCard title="Pagamentos" rows={payments} />
                    <TransactionsCard rows={transactions} />
                </div>
            </div>
        </>
    );
}

function PaymentResult({ payment }: { payment: PendingPayment }) {
    const [copied, setCopied] = useState(false);
    const [status, setStatus] = useState(payment.status);

    useEffect(() => {
        if (status === 'approved' || payment.method === 'credit_card') {
            return;
        }
        const timer = setInterval(async () => {
            try {
                const res = await fetch(`/client/billing/payments/${payment.id}/status`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = (await res.json()) as { status: string };
                setStatus(data.status);
                if (data.status === 'approved') {
                    clearInterval(timer);
                    router.reload({ only: ['wallet', 'invoices', 'payments', 'transactions'] });
                }
            } catch {
                /* ignora falha de polling */
            }
        }, 5000);

        return () => clearInterval(timer);
    }, [payment.id, payment.method, status]);

    const copy = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            /* clipboard indisponível */
        }
    };

    if (status === 'approved') {
        return (
            <Card className="gap-0 border-brand-green/40 bg-brand-green/5 py-0">
                <CardContent className="flex items-center gap-2 p-5 text-sm font-medium text-brand-green">
                    <Check className="size-4" /> Pagamento confirmado! Seu saldo já foi atualizado.
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="gap-0 border-brand-green/40 bg-brand-green/5 py-0">
            <CardContent className="space-y-4 p-5">
                <div className="flex items-center justify-between">
                    <div className="text-sm font-medium">
                        Cobrança de {formatBRL(payment.amount_cents)} —{' '}
                        {payment.method === 'pix' ? 'PIX' : payment.method === 'boleto' ? 'Boleto' : 'Cartão'}
                    </div>
                    <Badge variant="outline" className="border-transparent bg-amber-100 text-amber-700">
                        Aguardando pagamento
                    </Badge>
                </div>

                {payment.method === 'pix' && payment.qr_code_base64 && (
                    <div className="flex flex-col items-center gap-3 sm:flex-row sm:items-start">
                        <img
                            src={`data:image/png;base64,${payment.qr_code_base64}`}
                            alt="QR Code PIX"
                            className="size-44 rounded-md border border-border bg-white p-2"
                        />
                        <div className="w-full space-y-2">
                            <Label>PIX copia e cola</Label>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 overflow-x-auto rounded-md border border-border bg-background px-3 py-2 font-mono text-xs">
                                    {payment.qr_code}
                                </code>
                                <Button variant="outline" size="sm" onClick={() => copy(payment.qr_code ?? '')}>
                                    {copied ? <Check className="text-brand-green" /> : <Copy />}
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Após pagar, a confirmação chega automaticamente em alguns segundos.
                            </p>
                        </div>
                    </div>
                )}

                {payment.method === 'boleto' && payment.ticket_url && (
                    <div className="space-y-2">
                        {payment.barcode && (
                            <code className="block overflow-x-auto rounded-md border border-border bg-background px-3 py-2 font-mono text-xs">
                                {payment.barcode}
                            </code>
                        )}
                        <Button asChild variant="outline">
                            <a href={payment.ticket_url} target="_blank" rel="noreferrer">
                                <FileText /> Abrir boleto
                            </a>
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

const PAYMENT_METHODS: {
    id: Method;
    label: string;
    description: string;
    icon: typeof QrCode;
}[] = [
    { id: 'pix', label: 'PIX', description: 'Confirmação rápida', icon: QrCode },
    { id: 'boleto', label: 'Boleto', description: 'Vence em 3 dias', icon: FileText },
    { id: 'credit_card', label: 'Cartão', description: 'Em breve', icon: CreditCard },
];

function MethodPicker({ value, onChange }: { value: Method; onChange: (m: Method) => void }) {
    return (
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
            {PAYMENT_METHODS.map((option) => {
                const Icon = option.icon;
                const selected = value === option.id;
                const disabled = option.id === 'credit_card';

                return (
                    <button
                        key={option.id}
                        type="button"
                        disabled={disabled}
                        onClick={() => onChange(option.id)}
                        className={cn(
                            'flex flex-col items-start gap-2 rounded-xl border p-3 text-left transition-all',
                            selected
                                ? 'border-brand-green bg-brand-green/10 ring-2 ring-brand-green/25'
                                : 'border-border bg-background hover:border-brand-green/40 hover:bg-muted/30',
                            disabled && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        <div className="flex w-full items-center justify-between gap-2">
                            <span className={cn('flex size-8 items-center justify-center rounded-lg', selected ? 'bg-brand-green text-white' : 'bg-muted text-muted-foreground')}>
                                <Icon className="size-4" />
                            </span>
                            {selected && <Check className="size-4 text-brand-green" />}
                        </div>
                        <div>
                            <p className="text-sm font-semibold">{option.label}</p>
                            <p className="text-xs text-muted-foreground">{option.description}</p>
                        </div>
                    </button>
                );
            })}
        </div>
    );
}

function popularPlanId(plans: PlanRow[]): string | null {
    if (plans.length < 2) {
        return null;
    }

    return plans[Math.floor(plans.length / 2)]?.id ?? null;
}

function PlanPicker({
    plans,
    value,
    onChange,
    compact = false,
}: {
    plans: PlanRow[];
    value: string;
    onChange: (planId: string) => void;
    compact?: boolean;
}) {
    const highlighted = popularPlanId(plans);

    return (
        <div className={cn('grid gap-3', compact ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2')}>
            {plans.map((plan) => {
                const selected = value === plan.id;
                const isPopular = plan.id === highlighted;

                return (
                    <button
                        key={plan.id}
                        type="button"
                        onClick={() => onChange(plan.id)}
                        className={cn(
                            'relative flex flex-col rounded-2xl border p-4 text-left transition-all',
                            compact ? 'p-3' : 'p-5',
                            selected
                                ? 'border-brand-green bg-gradient-to-br from-brand-green/10 via-brand-green/5 to-transparent shadow-md ring-2 ring-brand-green/25'
                                : 'border-border bg-card hover:border-brand-green/40 hover:shadow-sm',
                        )}
                    >
                        {isPopular && (
                            <Badge className="absolute -top-2.5 left-4 border-0 bg-brand-green text-white shadow-sm">
                                Mais popular
                            </Badge>
                        )}

                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="font-semibold">{plan.name}</p>
                                <div className="mt-1 flex items-baseline gap-1">
                                    <span className={cn('font-bold tabular-nums text-foreground', compact ? 'text-xl' : 'text-3xl')}>
                                        {formatBRL(plan.price_cents)}
                                    </span>
                                    <span className="text-xs text-muted-foreground">/mês</span>
                                </div>
                            </div>
                            {selected && (
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-green text-white">
                                    <Check className="size-3.5" />
                                </span>
                            )}
                        </div>

                        <ul className={cn('space-y-2 text-muted-foreground', compact ? 'mt-3 text-xs' : 'mt-4 text-sm')}>
                            <li className="flex items-center gap-2">
                                <Zap className="size-3.5 shrink-0 text-brand-green" />
                                <span>
                                    Recarrega <strong className="text-foreground">{formatBRL(plan.recharge_cents)}</strong> no saldo
                                </span>
                            </li>
                            <li className="flex items-center gap-2">
                                <Repeat className="size-3.5 shrink-0 text-brand-green" />
                                Renovação automática mensal
                            </li>
                            {!compact && (
                                <li className="flex items-center gap-2">
                                    <ShieldCheck className="size-3.5 shrink-0 text-brand-green" />
                                    Cancele quando quiser
                                </li>
                            )}
                        </ul>
                    </button>
                );
            })}
        </div>
    );
}

function TopupDialog() {
    const [open, setOpen] = useState(false);
    const [method, setMethod] = useState<Method>('pix');
    const form = useForm({ amount: '50,00', method: 'pix' as Method, card_token: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, method }));
        form.post('/client/billing/topup', {
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
                <Button>
                    <Wallet /> Recarregar saldo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Recarregar saldo</DialogTitle>
                    <DialogDescription>Escolha o valor e a forma de pagamento.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="amount">Valor (R$)</Label>
                        <Input
                            id="amount"
                            type="number"
                            min={1}
                            step="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            required
                        />
                        {form.errors.amount && <p className="text-sm text-destructive">{form.errors.amount}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label>Forma de pagamento</Label>
                        <MethodPicker value={method} onChange={setMethod} />
                    </div>
                    {method === 'credit_card' && (
                        <p className="rounded-md bg-muted p-3 text-xs text-muted-foreground">
                            Pagamento com cartão requer o checkout seguro do Mercado Pago. Use PIX ou boleto para
                            confirmação imediata neste painel.
                        </p>
                    )}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || method === 'credit_card'}>
                            Gerar cobrança
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function subscriptionStatusBadge(status: string) {
    if (status === 'active') {
        return {
            label: 'Ativa',
            className: 'border-transparent bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
        };
    }

    if (status === 'past_due') {
        return {
            label: 'Em atraso',
            className: 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        };
    }

    return {
        label: status,
        className: 'border-transparent bg-muted text-muted-foreground',
    };
}

function paymentMethodInfo(method: string | null) {
    if (method === 'credit_card') {
        return { label: 'Cartão automático', description: 'Cobrança recorrente no cartão', icon: CreditCard };
    }

    if (method === 'pix') {
        return { label: 'PIX manual', description: 'Pague a fatura quando gerada', icon: QrCode };
    }

    if (method === 'boleto') {
        return { label: 'Boleto manual', description: 'Pague a fatura quando gerada', icon: FileText };
    }

    return { label: 'PIX ou boleto', description: 'Pagamento manual da fatura mensal', icon: QrCode };
}

function formatBillingDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(`${value}T12:00:00`);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('pt-BR');
}

function daysUntilBilling(value: string | null): number | null {
    if (!value) {
        return null;
    }

    const target = new Date(`${value}T12:00:00`);
    const today = new Date();
    today.setHours(12, 0, 0, 0);

    if (Number.isNaN(target.getTime())) {
        return null;
    }

    return Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
}

function ActiveSubscriptionState({
    subscription,
    plans,
}: {
    subscription: Subscription;
    plans: PlanRow[];
}) {
    const currentPlan = useMemo(
        () => plans.find((plan) => plan.id === subscription.plan_id),
        [plans, subscription.plan_id],
    );
    const status = subscriptionStatusBadge(subscription.status);
    const payment = paymentMethodInfo(subscription.payment_method);
    const PaymentIcon = payment.icon;
    const daysLeft = daysUntilBilling(subscription.next_billing_at);

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-sm font-semibold">Assinatura</h2>
                <Badge variant="outline" className={status.className}>
                    {status.label}
                </Badge>
            </div>

            <div className="overflow-hidden rounded-2xl border border-brand-green/20 bg-gradient-to-br from-brand-green/10 via-brand-green/5 to-transparent">
                <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-green/15 text-brand-green">
                            <Repeat className="size-6" />
                        </div>
                        <div>
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Plano atual</p>
                            <p className="mt-1 text-2xl font-bold">{subscription.plan_name ?? 'Plano'}</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Recarga mensal automática no seu saldo
                            </p>
                        </div>
                    </div>
                    {currentPlan && (
                        <div className="rounded-xl border border-border/60 bg-background/60 px-4 py-3 text-right backdrop-blur-sm">
                            <p className="text-xs text-muted-foreground">Valor mensal</p>
                            <p className="text-2xl font-bold tabular-nums">{formatBRL(currentPlan.price_cents)}</p>
                        </div>
                    )}
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SubscriptionDetailTile
                    icon={Zap}
                    label="Recarga mensal"
                    value={currentPlan ? formatBRL(currentPlan.recharge_cents) : '—'}
                    hint="Creditado no saldo após pagamento"
                />
                <SubscriptionDetailTile
                    icon={PaymentIcon}
                    label="Pagamento"
                    value={payment.label}
                    hint={payment.description}
                />
                <SubscriptionDetailTile
                    icon={CalendarDays}
                    label="Próxima cobrança"
                    value={formatBillingDate(subscription.next_billing_at)}
                    hint={
                        daysLeft === null
                            ? 'Data ainda não definida'
                            : daysLeft === 0
                              ? 'Cobrança prevista para hoje'
                              : daysLeft === 1
                                ? 'Falta 1 dia'
                                : daysLeft > 0
                                  ? `Faltam ${daysLeft} dias`
                                  : `${Math.abs(daysLeft)} dia(s) em atraso`
                    }
                />
                <SubscriptionDetailTile
                    icon={ShieldCheck}
                    label="Renovação"
                    value="Mensal"
                    hint="Você pode alterar ou cancelar quando quiser"
                />
            </div>

            <div className="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-muted-foreground">
                    Ao alterar o plano, a cobrança automática no cartão é encerrada e passa a ser manual.
                </p>
                <div className="flex flex-wrap gap-2">
                    <ChangePlanDialog subscriptionId={subscription.id} plans={plans} />
                    <Button
                        variant="outline"
                        className="text-destructive hover:border-destructive/40 hover:bg-destructive/5 hover:text-destructive"
                        onClick={() => {
                            if (confirm('Cancelar a assinatura? A recorrência será encerrada.')) {
                                router.post(
                                    `/client/billing/subscriptions/${subscription.id}/cancel`,
                                    {},
                                    { preserveScroll: true },
                                );
                            }
                        }}
                    >
                        Cancelar assinatura
                    </Button>
                </div>
            </div>
        </div>
    );
}

function SubscriptionDetailTile({
    icon: Icon,
    label,
    value,
    hint,
}: {
    icon: typeof Zap;
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-4">
            <div className="flex items-center gap-2 text-muted-foreground">
                <span className="flex size-7 items-center justify-center rounded-lg bg-brand-green/10 text-brand-green">
                    <Icon className="size-3.5" />
                </span>
                <span className="text-xs font-medium tracking-wide uppercase">{label}</span>
            </div>
            <p className="mt-3 font-semibold">{value}</p>
            <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
        </div>
    );
}

function SubscriptionCard({ subscription, plans }: { subscription: Subscription | null; plans: PlanRow[] }) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="p-5">
                {subscription ? (
                    <ActiveSubscriptionState subscription={subscription} plans={plans} />
                ) : (
                    <NoSubscriptionState plans={plans} />
                )}
            </CardContent>
        </Card>
    );
}

function NoSubscriptionState({ plans }: { plans: PlanRow[] }) {
    const [open, setOpen] = useState(false);
    const [initialPlanId, setInitialPlanId] = useState<string | undefined>();

    const openSubscribe = (planId?: string) => {
        setInitialPlanId(planId);
        setOpen(true);
    };

    return (
        <div className="space-y-5">
            <div className="flex flex-col gap-4 rounded-2xl border border-brand-green/20 bg-gradient-to-br from-brand-green/10 via-brand-green/5 to-transparent p-5 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-4">
                    <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-green/15 text-brand-green">
                        <Sparkles className="size-6" />
                    </div>
                    <div>
                        <h3 className="font-semibold">Recarga mensal automática</h3>
                        <p className="mt-1 max-w-xl text-sm text-muted-foreground">
                            Escolha um plano e receba créditos no saldo todo mês, sem precisar recarregar manualmente.
                        </p>
                    </div>
                </div>
                <Button className="shrink-0" disabled={plans.length === 0} onClick={() => openSubscribe()}>
                    Ver planos <ArrowRight />
                </Button>
            </div>

            {plans.length > 0 && (
                <PlanPicker
                    plans={plans}
                    value={initialPlanId ?? plans[0]?.id ?? ''}
                    onChange={(planId) => openSubscribe(planId)}
                    compact
                />
            )}

            <SubscribeDialog
                plans={plans}
                open={open}
                onOpenChange={setOpen}
                initialPlanId={initialPlanId}
            />
        </div>
    );
}

function SubscribeDialog({
    plans,
    open,
    onOpenChange,
    initialPlanId,
    trigger,
}: {
    plans: PlanRow[];
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    initialPlanId?: string;
    trigger?: ReactNode;
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const isControlled = open !== undefined;
    const dialogOpen = isControlled ? open : internalOpen;
    const setDialogOpen = isControlled ? (onOpenChange ?? (() => {})) : setInternalOpen;

    const [method, setMethod] = useState<Method>('pix');
    const defaultPlanId = initialPlanId ?? plans[0]?.id ?? '';
    const form = useForm({ plan_id: defaultPlanId, method: 'pix' as Method });

    const selectedPlan = useMemo(
        () => plans.find((plan) => plan.id === form.data.plan_id) ?? plans[0],
        [plans, form.data.plan_id],
    );

    useEffect(() => {
        if (dialogOpen) {
            form.setData('plan_id', initialPlanId ?? plans[0]?.id ?? '');
            setMethod('pix');
        }
    }, [dialogOpen, initialPlanId, plans]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, method }));
        form.post('/client/billing/subscribe', {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        });
    };

    const methodLabel = PAYMENT_METHODS.find((option) => option.id === method)?.label ?? method;

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <div className="border-b border-border bg-gradient-to-br from-brand-green/10 via-transparent to-transparent px-6 py-5">
                    <DialogHeader className="gap-1.5 text-left">
                        <DialogTitle className="text-xl">Escolha seu plano</DialogTitle>
                        <DialogDescription>
                            Assinatura mensal com recarga automática de saldo para suas consultas.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={submit} className="space-y-6 px-6 py-5">
                    <div className="space-y-3">
                        <Label className="text-sm font-medium">Planos disponíveis</Label>
                        <PlanPicker
                            plans={plans}
                            value={form.data.plan_id}
                            onChange={(planId) => form.setData('plan_id', planId)}
                        />
                        {form.errors.plan_id && <p className="text-sm text-destructive">{form.errors.plan_id}</p>}
                    </div>

                    <div className="space-y-3">
                        <Label className="text-sm font-medium">Como deseja pagar?</Label>
                        <MethodPicker value={method} onChange={setMethod} />
                    </div>

                    {selectedPlan && (
                        <div className="rounded-xl border border-border bg-muted/30 p-4">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Resumo</p>
                            <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <p className="font-semibold">{selectedPlan.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {formatBRL(selectedPlan.price_cents)}/mês via {methodLabel}
                                    </p>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Saldo: <span className="font-medium text-brand-green">+{formatBRL(selectedPlan.recharge_cents)}</span>/mês
                                </p>
                            </div>
                        </div>
                    )}

                    <DialogFooter className="gap-2 border-t border-border px-0 pt-4 sm:justify-between">
                        <p className="text-xs text-muted-foreground">
                            Você pode cancelar a assinatura a qualquer momento.
                        </p>
                        <Button type="submit" disabled={form.processing || method === 'credit_card' || plans.length === 0} className="min-w-36">
                            {form.processing ? 'Processando…' : 'Assinar agora'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ChangePlanDialog({ subscriptionId, plans }: { subscriptionId: string; plans: PlanRow[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ plan_id: plans[0]?.id ?? '' });

    const selectedPlan = useMemo(
        () => plans.find((plan) => plan.id === form.data.plan_id) ?? plans[0],
        [plans, form.data.plan_id],
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/client/billing/subscriptions/${subscriptionId}/change`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Alterar plano</Button>
            </DialogTrigger>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <div className="border-b border-border px-6 py-5">
                    <DialogHeader className="text-left">
                        <DialogTitle>Alterar plano</DialogTitle>
                        <DialogDescription>
                            Ao trocar o valor recorrente, a cobrança automática no cartão é encerrada e passa a manual.
                        </DialogDescription>
                    </DialogHeader>
                </div>
                <form onSubmit={submit} className="space-y-5 px-6 py-5">
                    <PlanPicker
                        plans={plans}
                        value={form.data.plan_id}
                        onChange={(planId) => form.setData('plan_id', planId)}
                    />
                    {selectedPlan && (
                        <div className="rounded-xl border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
                            Novo valor: <span className="font-semibold text-foreground">{formatBRL(selectedPlan.price_cents)}/mês</span>
                            {' · '}
                            Recarga: <span className="font-semibold text-brand-green">{formatBRL(selectedPlan.recharge_cents)}</span>
                        </div>
                    )}
                    <DialogFooter className="border-t border-border px-0 pt-4">
                        <Button type="submit" disabled={form.processing}>
                            Confirmar alteração
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function InvoicesCard({ invoices }: { invoices: Invoice[] }) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="p-0">
                <div className="border-b border-border px-6 py-3 text-sm font-semibold">Faturas em aberto</div>
                <table className="w-full text-sm">
                    <thead className="text-left text-muted-foreground">
                        <tr className="border-b border-border">
                            <th className="px-6 py-3 font-medium">Descrição</th>
                            <th className="px-6 py-3 font-medium">Vencimento</th>
                            <th className="px-6 py-3 font-medium">Valor</th>
                            <th className="px-6 py-3 font-medium">Status</th>
                            <th className="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.map((inv) => (
                            <tr key={inv.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                <td className="px-6 py-3">{inv.description ?? 'Fatura'}</td>
                                <td className="px-6 py-3 text-muted-foreground">{inv.due_date ?? '—'}</td>
                                <td className="px-6 py-3 font-medium">{formatBRL(inv.amount_cents)}</td>
                                <td className="px-6 py-3">
                                    <Badge
                                        variant="outline"
                                        className={
                                            inv.status === 'overdue'
                                                ? 'border-transparent bg-red-100 text-red-700'
                                                : 'border-transparent bg-amber-100 text-amber-700'
                                        }
                                    >
                                        {inv.status === 'overdue' ? 'Vencida' : 'Em aberto'}
                                    </Badge>
                                </td>
                                <td className="px-6 py-3 text-right">
                                    <PayInvoiceDialog invoice={inv} />
                                </td>
                            </tr>
                        ))}
                        {invoices.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-6 py-10 text-center text-sm text-muted-foreground">
                                    Nenhuma fatura em aberto.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function PayInvoiceDialog({ invoice }: { invoice: Invoice }) {
    const [open, setOpen] = useState(false);
    const [method, setMethod] = useState<Method>('pix');
    const form = useForm({ invoice_id: invoice.id, method: 'pix' as Method });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, method }));
        form.post('/client/billing/invoices/pay', {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">Pagar</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pagar fatura</DialogTitle>
                    <DialogDescription>
                        {invoice.description ?? 'Fatura'} — {formatBRL(invoice.amount_cents)}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Forma de pagamento</Label>
                        <MethodPicker value={method} onChange={setMethod} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || method === 'credit_card'}>
                            Gerar cobrança
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function HistoryCard({ title, rows }: { title: string; rows: PaymentRow[] }) {
    const label = (s: string) =>
        ({ approved: 'Aprovado', pending: 'Pendente', rejected: 'Recusado', cancelled: 'Cancelado' })[s] ?? s;

    return (
        <Card className="gap-0 py-0">
            <CardContent className="p-0">
                <div className="border-b border-border px-6 py-3 text-sm font-semibold">{title}</div>
                <table className="w-full text-sm">
                    <tbody>
                        {rows.map((p) => (
                            <tr key={p.id} className="border-b border-border last:border-0">
                                <td className="px-6 py-3 text-muted-foreground">{formatDateTime(p.created_at)}</td>
                                <td className="px-6 py-3 capitalize">{p.method}</td>
                                <td className="px-6 py-3 font-medium">{formatBRL(p.amount_cents)}</td>
                                <td className="px-6 py-3 text-right text-muted-foreground">{label(p.status)}</td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td className="px-6 py-10 text-center text-sm text-muted-foreground">
                                    Nenhum pagamento ainda.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function TransactionsCard({ rows }: { rows: Transaction[] }) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="p-0">
                <div className="border-b border-border px-6 py-3 text-sm font-semibold">Extrato da carteira</div>
                <table className="w-full text-sm">
                    <tbody>
                        {rows.map((t, i) => (
                            <tr key={i} className="border-b border-border last:border-0">
                                <td className="px-6 py-3 text-muted-foreground">{formatDateTime(t.created_at)}</td>
                                <td className="px-6 py-3 capitalize">{t.type}</td>
                                <td
                                    className={`px-6 py-3 text-right font-medium ${t.direction === 'credit' ? 'text-brand-green' : 'text-foreground'}`}
                                >
                                    {t.direction === 'credit' ? '+' : '−'}
                                    {formatBRL(t.amount_cents)}
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td className="px-6 py-10 text-center text-sm text-muted-foreground">
                                    Sem movimentações.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function formatDateTime(value: string | null): string {
    if (!value) return '—';
    const d = new Date(value.replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('pt-BR');
}

ClientBillingIndex.layout = {
    breadcrumbs: [{ title: 'Financeiro', href: '/client/billing' }],
};
