import { Head, router, usePage } from '@inertiajs/react';
import { CircleDollarSign, FileWarning, Receipt, Repeat, TrendingUp, Wallet } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { formatDateTime, Pagination, type Paginator } from '@/components/pagination';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatBRL } from '@/lib/format';

interface Summary {
    revenue_total: number;
    revenue_month: number;
    open_invoices: number;
    overdue_invoices: number;
    active_subscriptions: number;
    mrr: number;
}

interface PaymentRow {
    id: string;
    account_name: string;
    type: string;
    method: string;
    status: string;
    amount_cents: number;
    created_at: string | null;
    paid_at: string | null;
}

interface InvoiceRow {
    id: string;
    account_id: string;
    status: string;
    amount_cents: number;
    description: string | null;
    due_date: string | null;
    paid_at: string | null;
}

interface SubscriptionRow {
    id: string;
    account_name: string | null;
    plan_name: string | null;
    status: string;
    payment_method: string | null;
    price_cents: number;
    next_billing_at: string | null;
}

interface Filters {
    account_id: string | null;
    account_name: string | null;
    status: string;
}

interface PageProps {
    summary: Summary;
    payments: Paginator<PaymentRow>;
    invoices: InvoiceRow[];
    subscriptions: SubscriptionRow[];
    filters: Filters;
    [key: string]: unknown;
}

const statusStyles: Record<string, string> = {
    approved: 'border-transparent bg-green-100 text-green-700',
    paid: 'border-transparent bg-green-100 text-green-700',
    pending: 'border-transparent bg-amber-100 text-amber-700',
    open: 'border-transparent bg-amber-100 text-amber-700',
    overdue: 'border-transparent bg-red-100 text-red-700',
    rejected: 'border-transparent bg-red-100 text-red-700',
    cancelled: 'border-transparent bg-muted text-muted-foreground',
    canceled: 'border-transparent bg-muted text-muted-foreground',
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant="outline" className={statusStyles[status] ?? 'border-transparent bg-muted text-muted-foreground'}>
            {status}
        </Badge>
    );
}

export default function AdminFinanceIndex() {
    const { summary, payments, invoices, subscriptions, filters } = usePage<PageProps>().props;

    const setStatus = (status: string) => {
        router.get(
            '/admin/finance',
            { status, account_id: filters.account_id ?? undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Financeiro" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Financeiro"
                    description={
                        filters.account_name
                            ? `Visão financeira de ${filters.account_name}`
                            : 'Receita, faturas, assinaturas e pagamentos.'
                    }
                    actions={
                        filters.account_id ? (
                            <Button variant="outline" onClick={() => router.get('/admin/finance')}>
                                Limpar filtro de cliente
                            </Button>
                        ) : undefined
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard label="Receita total" value={formatBRL(summary.revenue_total)} icon={CircleDollarSign} highlight />
                    <StatCard label="Receita no mês" value={formatBRL(summary.revenue_month)} icon={TrendingUp} />
                    <StatCard label="MRR (assinaturas ativas)" value={formatBRL(summary.mrr)} icon={Repeat} />
                    <StatCard label="Assinaturas ativas" value={summary.active_subscriptions} icon={Wallet} />
                    <StatCard label="Faturas em aberto" value={formatBRL(summary.open_invoices)} icon={Receipt} />
                    <StatCard label="Faturas vencidas" value={formatBRL(summary.overdue_invoices)} icon={FileWarning} />
                </div>

                <Card className="gap-0 py-0">
                    <CardContent className="p-0">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-6 py-3">
                            <span className="text-sm font-semibold">Pagamentos</span>
                            <div className="flex gap-1">
                                {['all', 'approved', 'pending', 'rejected'].map((s) => (
                                    <Button
                                        key={s}
                                        size="sm"
                                        variant={filters.status === s ? 'default' : 'outline'}
                                        onClick={() => setStatus(s)}
                                    >
                                        {s === 'all' ? 'Todos' : s}
                                    </Button>
                                ))}
                            </div>
                        </div>
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-6 py-3 font-medium">Cliente</th>
                                    <th className="px-6 py-3 font-medium">Tipo</th>
                                    <th className="px-6 py-3 font-medium">Método</th>
                                    <th className="px-6 py-3 font-medium">Valor</th>
                                    <th className="px-6 py-3 font-medium">Status</th>
                                    <th className="px-6 py-3 font-medium">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.data.map((p) => (
                                    <tr key={p.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                        <td className="px-6 py-3 font-medium">{p.account_name}</td>
                                        <td className="px-6 py-3 capitalize text-muted-foreground">{p.type}</td>
                                        <td className="px-6 py-3 capitalize text-muted-foreground">{p.method}</td>
                                        <td className="px-6 py-3 font-medium">{formatBRL(p.amount_cents)}</td>
                                        <td className="px-6 py-3">
                                            <StatusBadge status={p.status} />
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">{formatDateTime(p.created_at)}</td>
                                    </tr>
                                ))}
                                {payments.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-10 text-center text-sm text-muted-foreground">
                                            Nenhum pagamento encontrado.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                        <div className="px-6 py-3">
                            <Pagination paginator={payments} />
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="gap-0 py-0">
                        <CardContent className="p-0">
                            <div className="border-b border-border px-6 py-3 text-sm font-semibold">Faturas</div>
                            <table className="w-full text-sm">
                                <tbody>
                                    {invoices.map((inv) => (
                                        <tr key={inv.id} className="border-b border-border last:border-0">
                                            <td className="px-6 py-3">{inv.description ?? 'Fatura'}</td>
                                            <td className="px-6 py-3 text-muted-foreground">{inv.due_date ?? '—'}</td>
                                            <td className="px-6 py-3 font-medium">{formatBRL(inv.amount_cents)}</td>
                                            <td className="px-6 py-3 text-right">
                                                <StatusBadge status={inv.status} />
                                            </td>
                                        </tr>
                                    ))}
                                    {invoices.length === 0 && (
                                        <tr>
                                            <td className="px-6 py-10 text-center text-sm text-muted-foreground">
                                                Nenhuma fatura.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardContent className="p-0">
                            <div className="border-b border-border px-6 py-3 text-sm font-semibold">Assinaturas</div>
                            <table className="w-full text-sm">
                                <tbody>
                                    {subscriptions.map((s) => (
                                        <tr key={s.id} className="border-b border-border last:border-0">
                                            <td className="px-6 py-3 font-medium">{s.account_name}</td>
                                            <td className="px-6 py-3 text-muted-foreground">{s.plan_name}</td>
                                            <td className="px-6 py-3">{formatBRL(s.price_cents)}</td>
                                            <td className="px-6 py-3 text-right">
                                                <StatusBadge status={s.status} />
                                            </td>
                                        </tr>
                                    ))}
                                    {subscriptions.length === 0 && (
                                        <tr>
                                            <td className="px-6 py-10 text-center text-sm text-muted-foreground">
                                                Nenhuma assinatura.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminFinanceIndex.layout = {
    breadcrumbs: [{ title: 'Financeiro', href: '/admin/finance' }],
};
