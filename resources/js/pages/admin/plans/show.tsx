import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Coins,
    Layers,
    Repeat,
    Settings2,
    Users,
    Wallet,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL } from '@/lib/format';

interface Plan {
    id: string;
    name: string;
    slug: string;
    price_cents: number;
    currency: string;
    billing_period: string;
    included_credits: number;
    overage_price_cents: number | null;
    status: string;
    features: Record<string, unknown> | unknown[];
    created_at: string | null;
    updated_at: string | null;
}

interface Stats {
    active_subscriptions: number;
    total_subscriptions: number;
    mrr_cents: number;
}

interface SubscriptionRow {
    id: string;
    account_name: string | null;
    status: string;
    payment_method: string | null;
    next_billing_at: string | null;
    created_at: string | null;
}

interface Props {
    plan: Plan;
    stats: Stats;
    recent_subscriptions: SubscriptionRow[];
}

export default function AdminPlanShow({ plan, stats, recent_subscriptions }: Props) {
    usePageFlash();

    const billingLabel = plan.billing_period === 'monthly' ? '/mês' : ' único';
    const isActive = plan.status === 'active';

    return (
        <>
            <Head title={`Plano — ${plan.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={plan.name}
                    description={`${plan.slug} · ${formatBRL(plan.price_cents)}${billingLabel}`}
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/plans">
                                <ArrowLeft /> Voltar
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        className={isActive
                            ? 'border-transparent bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                            : 'border-transparent bg-muted text-muted-foreground'}
                    >
                        {isActive ? 'Ativo' : 'Arquivado'}
                    </Badge>
                    <Badge variant="outline" className="uppercase">{plan.currency}</Badge>
                    <Badge variant="secondary">
                        {plan.billing_period === 'monthly' ? 'Cobrança mensal' : 'Pagamento único'}
                    </Badge>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Assinaturas ativas" value={stats.active_subscriptions} icon={Users} highlight />
                    <StatCard label="Total de assinaturas" value={stats.total_subscriptions} icon={Repeat} />
                    <StatCard
                        label="MRR deste plano"
                        value={formatBRL(stats.mrr_cents)}
                        icon={Wallet}
                        hint={plan.billing_period === 'monthly' ? 'Receita recorrente estimada' : 'Plano avulso'}
                    />
                    <StatCard
                        label="Saldo incluso"
                        value={formatBRL(plan.included_credits)}
                        icon={Coins}
                        hint="Adicionado à carteira por ciclo"
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-0 py-0 lg:col-span-2">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Settings2 className="size-4" />
                                Editar plano
                            </CardTitle>
                            <CardDescription>
                                O slug não pode ser alterado após a criação.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-5">
                            <EditPlanForm plan={plan} />
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Layers className="size-4" />
                                Resumo
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 p-5">
                            <div className="rounded-2xl border border-brand-green/20 bg-gradient-to-br from-brand-green/10 via-brand-green/5 to-transparent p-4">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Preço</p>
                                <p className="mt-1 text-3xl font-bold tabular-nums">
                                    {formatBRL(plan.price_cents)}
                                    <span className="text-base font-normal text-muted-foreground">{billingLabel}</span>
                                </p>
                            </div>

                            <ul className="space-y-2 text-sm">
                                <li className="flex items-center gap-2">
                                    <Check className="size-4 text-brand-green" />
                                    <strong>{formatBRL(plan.included_credits)}</strong> de saldo incluso
                                </li>
                                {plan.overage_price_cents !== null && (
                                    <li className="flex items-center gap-2">
                                        <Check className="size-4 text-brand-green" />
                                        Excedente: {formatBRL(plan.overage_price_cents)}/consulta
                                    </li>
                                )}
                                <li className="flex items-center gap-2 text-muted-foreground">
                                    <Check className="size-4 text-brand-green" />
                                    Identificador: <code className="rounded bg-muted px-1.5 py-0.5 text-xs">{plan.slug}</code>
                                </li>
                            </ul>

                            <Separator />

                            <div className="space-y-1 text-xs text-muted-foreground">
                                {plan.created_at && <p>Criado em {formatDate(plan.created_at)}</p>}
                                {plan.updated_at && <p>Atualizado em {formatDate(plan.updated_at)}</p>}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b border-border py-4">
                        <CardTitle className="text-base">Assinaturas recentes</CardTitle>
                        <CardDescription>Clientes vinculados a este plano</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-6 py-3 font-medium">Cliente</th>
                                    <th className="px-6 py-3 font-medium">Status</th>
                                    <th className="px-6 py-3 font-medium">Pagamento</th>
                                    <th className="px-6 py-3 font-medium">Próxima cobrança</th>
                                    <th className="px-6 py-3 font-medium">Desde</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent_subscriptions.map((subscription) => (
                                    <tr key={subscription.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                        <td className="px-6 py-3 font-medium">{subscription.account_name ?? '—'}</td>
                                        <td className="px-6 py-3">
                                            <Badge
                                                variant="outline"
                                                className={subscription.status === 'active'
                                                    ? 'border-transparent bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                                                    : 'border-transparent bg-muted text-muted-foreground'}
                                            >
                                                {subscriptionStatusLabel(subscription.status)}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {paymentMethodLabel(subscription.payment_method)}
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {subscription.next_billing_at ? formatDateShort(subscription.next_billing_at) : '—'}
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {subscription.created_at ? formatDateShort(subscription.created_at) : '—'}
                                        </td>
                                    </tr>
                                ))}
                                {recent_subscriptions.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center text-muted-foreground">
                                            Nenhuma assinatura neste plano ainda.
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

function EditPlanForm({ plan }: { plan: Plan }) {
    const form = useForm({
        name: plan.name,
        price_reais: (plan.price_cents / 100).toFixed(2),
        included_reais: (plan.included_credits / 100).toFixed(2),
        billing_period: plan.billing_period,
        overage_reais: plan.overage_price_cents !== null ? (plan.overage_price_cents / 100).toFixed(2) : '',
        status: plan.status,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            name: data.name,
            price_cents: Math.round(Number(data.price_reais) * 100),
            included_credits: Math.round(Number(data.included_reais) * 100),
            billing_period: data.billing_period,
            overage_price_cents: data.overage_reais === '' ? null : Math.round(Number(data.overage_reais) * 100),
            status: data.status,
        }));
        form.put(`/admin/plans/${plan.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField label="Nome" error={form.errors.name}>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                </FormField>
                <FormField label="Slug (somente leitura)">
                    <Input value={plan.slug} disabled className="bg-muted/50" />
                </FormField>
                <FormField label="Preço (R$)" error={form.errors.price_reais ?? (form.errors as Record<string, string>).price_cents}>
                    <Input
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.data.price_reais}
                        onChange={(e) => form.setData('price_reais', e.target.value)}
                        required
                    />
                </FormField>
                <FormField label="Saldo incluso (R$)" error={form.errors.included_reais ?? (form.errors as Record<string, string>).included_credits}>
                    <Input
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.data.included_reais}
                        onChange={(e) => form.setData('included_reais', e.target.value)}
                        required
                    />
                </FormField>
                <FormField label="Período" error={form.errors.billing_period}>
                    <Select value={form.data.billing_period} onValueChange={(value) => form.setData('billing_period', value)}>
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="monthly">Mensal</SelectItem>
                            <SelectItem value="one_time">Pagamento único</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField label="Excedente (R$/consulta, opcional)" error={form.errors.overage_reais ?? (form.errors as Record<string, string>).overage_price_cents}>
                    <Input
                        type="number"
                        min={0}
                        step="0.01"
                        placeholder="—"
                        value={form.data.overage_reais}
                        onChange={(e) => form.setData('overage_reais', e.target.value)}
                    />
                </FormField>
                <FormField label="Status" error={form.errors.status}>
                    <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Ativo</SelectItem>
                            <SelectItem value="archived">Arquivado</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                <p className="text-xs text-muted-foreground">
                    Planos arquivados não aparecem para novos clientes.
                </p>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Salvando…' : 'Salvar alterações'}
                </Button>
            </div>
        </form>
    );
}

function FormField({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

function subscriptionStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        active: 'Ativa',
        cancelled: 'Cancelada',
        past_due: 'Em atraso',
    };

    return labels[status] ?? status;
}

function paymentMethodLabel(method: string | null): string {
    if (method === 'credit_card') {
return 'Cartão';
}

    if (method === 'pix') {
return 'PIX';
}

    if (method === 'boleto') {
return 'Boleto';
}

    return method ?? '—';
}

function formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('pt-BR');
}

function formatDateShort(value: string): string {
    const normalized = value.includes('T') ? value : `${value}T12:00:00`;
    const date = new Date(normalized);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('pt-BR');
}

AdminPlanShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Planos', href: '/admin/plans' },
        { title: props.plan.name, href: `/admin/plans/${props.plan.id}` },
    ],
});
