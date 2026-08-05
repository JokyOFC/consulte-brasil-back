import { Head, Link, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatBRL } from '@/lib/format';

interface InvoiceItem {
    id: string;
    description: string;
    quantity: number;
    amount_cents: number;
}

interface InvoiceDetail {
    id: string;
    number: string | null;
    status: string;
    amount_cents: number;
    description: string | null;
    due_date: string | null;
    due_label: string | null;
    is_renewal: boolean;
    period_start: string | null;
    period_end: string | null;
    paid_at: string | null;
    payment_id: string | null;
    is_payable: boolean;
    items: InvoiceItem[];
}

interface PageProps {
    invoice: InvoiceDetail;
    [key: string]: unknown;
}

const statusLabel: Record<string, string> = {
    open: 'Pendente',
    overdue: 'Vencida',
    paid: 'Paga',
    canceled: 'Cancelada',
};

export default function ClientInvoiceShow() {
    const { invoice } = usePage<PageProps>().props;

    return (
        <>
            <Head title={invoice.number ?? 'Fatura'} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={invoice.number ?? 'Fatura'}
                    description={invoice.description ?? 'Detalhes da cobrança'}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <a href={`/client/invoices/${invoice.id}/pdf`}>
                                    <Download className="size-3.5" />
                                    Baixar PDF
                                </a>
                            </Button>
                            {invoice.is_payable && (
                                <Button asChild>
                                    <Link href="/client/billing">Pagar agora</Link>
                                </Button>
                            )}
                            <Button variant="ghost" asChild>
                                <Link href="/client/invoices">Voltar</Link>
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardContent className="space-y-1 p-5">
                            <div className="text-xs text-muted-foreground">Status</div>
                            <Badge variant="outline">{statusLabel[invoice.status] ?? invoice.status}</Badge>
                            {invoice.is_renewal && (
                                <div className="pt-2">
                                    <Badge variant="outline" className="border-brand-green/30 text-brand-green">
                                        Renovação
                                    </Badge>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="space-y-1 p-5">
                            <div className="text-xs text-muted-foreground">Vencimento</div>
                            <div className="font-semibold">{invoice.due_date ?? '—'}</div>
                            <div className="text-sm text-muted-foreground">{invoice.due_label}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="space-y-1 p-5">
                            <div className="text-xs text-muted-foreground">Valor total</div>
                            <div className="text-xl font-semibold">{formatBRL(invoice.amount_cents)}</div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-0 py-0">
                    <CardContent className="p-0">
                        <div className="border-b border-border px-6 py-3 text-sm font-semibold">Itens</div>
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-6 py-3 font-medium">Descrição</th>
                                    <th className="px-6 py-3 font-medium">Qtd</th>
                                    <th className="px-6 py-3 font-medium">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoice.items.map((item) => (
                                    <tr key={item.id} className="border-b border-border last:border-0">
                                        <td className="px-6 py-3">{item.description}</td>
                                        <td className="px-6 py-3">{item.quantity}</td>
                                        <td className="px-6 py-3 font-medium">{formatBRL(item.amount_cents)}</td>
                                    </tr>
                                ))}
                                {invoice.items.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-8 text-center text-muted-foreground">
                                            {invoice.description ?? 'Sem itens detalhados'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="grid gap-3 p-5 text-sm sm:grid-cols-2">
                        <div>
                            <div className="text-muted-foreground">Período</div>
                            <div>
                                {invoice.period_start ?? '—'} → {invoice.period_end ?? '—'}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Pago em</div>
                            <div>{invoice.paid_at ? new Date(invoice.paid_at).toLocaleString('pt-BR') : '—'}</div>
                        </div>
                        {invoice.is_payable && (
                            <p className="sm:col-span-2 text-muted-foreground">
                                Para pagar, use o botão <strong>Pagar agora</strong> e conclua no Financeiro (PIX, boleto ou cartão).
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ClientInvoiceShow.layout = {
    breadcrumbs: [
        { title: 'Painel', href: '/dashboard' },
        { title: 'Minhas Faturas', href: '/client/invoices' },
        { title: 'Detalhe', href: '#' },
    ],
};
