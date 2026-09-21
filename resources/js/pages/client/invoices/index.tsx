import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, Download, Eye, FileText, Layers, Receipt } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatBRL } from '@/lib/format';

interface InvoiceRow {
    id: string;
    number: string | null;
    status: string;
    amount_cents: number;
    description: string | null;
    due_date: string | null;
    due_label: string | null;
    is_renewal: boolean;
    created_at: string | null;
    is_payable: boolean;
}

interface SubscriptionRow {
    id: string;
    plan_name: string | null;
    status: string;
    payment_method: string | null;
    price_cents: number;
    recharge_cents: number;
    current_period_end: string | null;
    next_billing_at: string | null;
}

interface PageProps {
    upcoming: InvoiceRow[];
    subscriptions: SubscriptionRow[];
    history: Paginator<InvoiceRow>;
    filters: { status: string };
    [key: string]: unknown;
}

const statusLabel: Record<string, string> = {
    open: 'Pendente',
    overdue: 'Vencida',
    paid: 'Paga',
    canceled: 'Cancelada',
};

const statusStyles: Record<string, string> = {
    open: 'border-transparent bg-amber-100 text-amber-700',
    overdue: 'border-transparent bg-red-100 text-red-700',
    paid: 'border-transparent bg-green-100 text-green-700',
    canceled: 'border-transparent bg-muted text-muted-foreground',
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant="outline" className={statusStyles[status] ?? 'border-transparent bg-muted text-muted-foreground'}>
            {statusLabel[status] ?? status}
        </Badge>
    );
}

export default function ClientInvoicesIndex() {
    const { upcoming, subscriptions, history, filters } = usePage<PageProps>().props;

    const setStatus = (status: string) => {
        router.get('/client/invoices', { status }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Minhas Faturas" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Minhas Faturas"
                    description="Próximos vencimentos, planos ativos e histórico de cobranças."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/client/billing">Ir ao Financeiro</Link>
                        </Button>
                    }
                />

                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <CalendarDays className="size-4 text-muted-foreground" />
                        <h2 className="text-sm font-semibold">Próximos vencimentos</h2>
                    </div>
                    {upcoming.length === 0 ? (
                        <Card>
                            <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                Nenhuma fatura pendente no momento.
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {upcoming.map((inv) => (
                                <Card key={inv.id}>
                                    <CardContent className="flex flex-col gap-4 p-5">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <div className="font-semibold">{inv.number ?? 'Fatura'}</div>
                                                <div className="text-sm text-muted-foreground">
                                                    {inv.description ?? 'Cobrança'}
                                                </div>
                                            </div>
                                            <div className="flex flex-col items-end gap-1">
                                                <StatusBadge status={inv.status} />
                                                {inv.is_renewal && (
                                                    <Badge variant="outline" className="border-brand-green/30 text-brand-green">
                                                        Renovação
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-end justify-between">
                                            <div>
                                                <div className="text-xs text-muted-foreground">Valor</div>
                                                <div className="text-lg font-semibold">{formatBRL(inv.amount_cents)}</div>
                                            </div>
                                            <div className="text-right text-sm">
                                                <div className="text-muted-foreground">{inv.due_date ?? '—'}</div>
                                                <div className="font-medium">{inv.due_label}</div>
                                            </div>
                                        </div>
                                        {inv.is_renewal && (
                                            <p className="text-xs text-muted-foreground">
                                                Pagamento antecipado soma os dias do novo ciclo ao tempo restante do plano.
                                            </p>
                                        )}
                                        <div className="flex flex-wrap gap-2">
                                            {inv.is_payable && (
                                                <Button size="sm" asChild>
                                                    <Link href="/client/billing">Pagar</Link>
                                                </Button>
                                            )}
                                            <Button size="sm" variant="outline" asChild>
                                                <a href={`/client/invoices/${inv.id}/pdf`}>
                                                    <Download className="size-3.5" />
                                                    PDF
                                                </a>
                                            </Button>
                                            <Button size="sm" variant="ghost" asChild>
                                                <Link href={`/client/invoices/${inv.id}`}>
                                                    <Eye className="size-3.5" />
                                                    Detalhes
                                                </Link>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Layers className="size-4 text-muted-foreground" />
                        <h2 className="text-sm font-semibold">Planos contratados</h2>
                    </div>
                    <Card className="gap-0 py-0">
                        {/* overflow-x-auto: no celular a tabela rola dentro do card em vez de ser cortada. */}
                        <CardContent className="overflow-x-auto p-0">
                            {subscriptions.length === 0 ? (
                                <p className="px-6 py-10 text-center text-sm text-muted-foreground">Nenhum plano ativo.</p>
                            ) : (
                                <table className="w-full min-w-[540px] text-sm whitespace-nowrap">
                                    <thead className="text-left text-muted-foreground">
                                        <tr className="border-b border-border">
                                            <th className="px-4 py-3 font-medium sm:px-6">Plano</th>
                                            <th className="px-4 py-3 font-medium sm:px-6">Créditos</th>
                                            <th className="px-4 py-3 font-medium sm:px-6">Valor</th>
                                            <th className="px-4 py-3 font-medium sm:px-6">Validade</th>
                                            <th className="px-4 py-3 font-medium sm:px-6">Método</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {subscriptions.map((s) => (
                                            <tr key={s.id} className="border-b border-border last:border-0">
                                                <td className="px-4 py-3 font-medium sm:px-6">{s.plan_name ?? '—'}</td>
                                                <td className="px-4 py-3 sm:px-6">{formatBRL(s.recharge_cents)}</td>
                                                <td className="px-4 py-3 sm:px-6">{formatBRL(s.price_cents)}</td>
                                                <td className="px-4 py-3 text-muted-foreground sm:px-6">
                                                    {s.current_period_end ?? s.next_billing_at ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 capitalize text-muted-foreground sm:px-6">
                                                    {s.payment_method === 'manual' ? 'PIX/Boleto' : s.payment_method ?? '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section className="space-y-3">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <Receipt className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold">Histórico de faturas</h2>
                        </div>
                        <div className="flex flex-wrap gap-1">
                            {['all', 'open', 'overdue', 'paid', 'canceled'].map((s) => (
                                <Button
                                    key={s}
                                    size="sm"
                                    variant={filters.status === s ? 'default' : 'outline'}
                                    onClick={() => setStatus(s)}
                                >
                                    {s === 'all' ? 'Todas' : statusLabel[s] ?? s}
                                </Button>
                            ))}
                        </div>
                    </div>
                    <Card className="gap-0 py-0">
                        <CardContent className="p-0">
                            {history.data.length === 0 ? (
                                <p className="px-6 py-10 text-center text-sm text-muted-foreground">Nenhuma fatura encontrada.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[720px] text-sm whitespace-nowrap">
                                        <thead className="text-left text-muted-foreground">
                                            <tr className="border-b border-border">
                                                <th className="px-4 py-3 font-medium sm:px-6">Número</th>
                                                <th className="px-4 py-3 font-medium sm:px-6">Descrição</th>
                                                <th className="px-4 py-3 font-medium sm:px-6">Vencimento</th>
                                                <th className="px-4 py-3 font-medium sm:px-6">Valor</th>
                                                <th className="px-4 py-3 font-medium sm:px-6">Status</th>
                                                <th className="px-4 py-3 sm:px-6"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {history.data.map((inv) => (
                                                <tr key={inv.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                                    <td className="px-4 py-3 font-medium sm:px-6">
                                                        {/* O número leva ao detalhe: no celular a coluna
                                                            "Detalhes" fica fora da área visível. */}
                                                        <Link
                                                            href={`/client/invoices/${inv.id}`}
                                                            className="flex items-center gap-2 hover:text-brand-green hover:underline"
                                                        >
                                                            <FileText className="size-3.5 text-muted-foreground" />
                                                            {inv.number ?? '—'}
                                                        </Link>
                                                    </td>
                                                    <td className="px-4 py-3 sm:px-6">
                                                        {inv.description ?? 'Fatura'}
                                                        {inv.is_renewal && (
                                                            <Badge variant="outline" className="ml-2 border-brand-green/30 text-brand-green">
                                                                Renovação
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground sm:px-6">{inv.due_date ?? '—'}</td>
                                                    <td className="px-4 py-3 font-medium sm:px-6">{formatBRL(inv.amount_cents)}</td>
                                                    <td className="px-4 py-3 sm:px-6">
                                                        <StatusBadge status={inv.status} />
                                                    </td>
                                                    <td className="px-4 py-3 text-right sm:px-6">
                                                        <Button size="sm" variant="ghost" asChild>
                                                            <Link href={`/client/invoices/${inv.id}`}>Detalhes</Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="px-4 py-3 sm:px-6">
                                <Pagination paginator={history} />
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

ClientInvoicesIndex.layout = {
    breadcrumbs: [
        { title: 'Painel', href: '/dashboard' },
        { title: 'Minhas Faturas', href: '/client/invoices' },
    ],
};
