import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowUpRight, CreditCard, FileSearch, FileText, KeyRound, TrendingUp } from 'lucide-react';
import { ConsultationStatusBadge } from '@/components/consultation-status-badge';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL } from '@/lib/format';
import { dashboard } from '@/routes';

interface Wallet {
    balance: number;
    reserved: number;
    available: number;
}

interface Stats {
    total: number;
    success: number;
    refunded: number;
    this_month: number;
    credits_spent: number;
    active_keys: number;
    success_rate: number;
}

interface ConsumptionPoint {
    date: string;
    count: number;
}

interface RecentConsultation {
    id: string;
    query_type: string;
    status: string;
    credit_cost: number;
    provider: string | null;
    created_at: string;
}

interface Props {
    wallet: Wallet | null;
    stats: Stats;
    consumption: ConsumptionPoint[];
    recent: RecentConsultation[];
}

export default function Dashboard({ wallet, stats, consumption, recent }: Props) {
    usePageFlash();

    return (
        <>
            <Head title="Painel" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">Olá 👋</h1>
                        <p className="text-sm text-muted-foreground">Resumo da sua conta e consumo.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild>
                            <Link href="/client/consultations">
                                <FileSearch /> Nova consulta
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/client/billing">
                                <CreditCard /> Recarregar saldo
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/client/api-keys">
                                <KeyRound /> Gerenciar chaves
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Saldo disponível" value={formatBRL(wallet?.available ?? 0)} icon={CreditCard} highlight hint="pronto para consultar" />
                    <StatCard label="Consultas no mês" value={stats.this_month} icon={Activity} />
                    <StatCard label="Taxa de sucesso" value={`${stats.success_rate}%`} icon={TrendingUp} hint={`${stats.total} no total`} />
                    <StatCard label="Chaves ativas" value={stats.active_keys} icon={KeyRound} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">Carteira</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-4xl font-bold tracking-tight">{formatBRL(wallet?.available ?? 0)}</p>
                                <p className="text-xs text-muted-foreground">disponível para uso</p>
                            </div>
                            <div className="space-y-2 border-t border-border pt-4 text-sm">
                                <Row label="Saldo total" value={wallet?.balance ?? 0} />
                                <Row label="Reservado" value={wallet?.reserved ?? 0} />
                                <Row label="Já consumido" value={stats.credits_spent} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Consumo — últimos 7 dias
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ConsumptionChart data={consumption} />
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-0 py-0">
                    <CardHeader className="flex flex-row items-center justify-between border-b border-border py-4">
                        <CardTitle className="text-base">Consultas recentes</CardTitle>
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/client/consultations">
                                Ver consultas <ArrowUpRight />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
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
                                        <td className="px-6 py-3">
                                            <Badge variant="secondary" className="uppercase">{c.query_type}</Badge>
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">{c.provider ?? '—'}</td>
                                        <td className="px-6 py-3"><ConsultationStatusBadge status={c.status} /></td>
                                        <td className="px-6 py-3 text-right font-medium">{formatBRL(c.credit_cost)}</td>
                                        <td className="px-6 py-3 text-muted-foreground">{formatDate(c.created_at)}</td>
                                    </tr>
                                ))}
                                {recent.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center">
                                            <FileText className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                            <p className="text-sm text-muted-foreground">
                                                Você ainda não fez consultas.{' '}
                                                <Link href="/client/consultations" className="text-brand-green hover:underline">
                                                    Realize sua primeira consulta
                                                </Link>{' '}
                                                ou integre via{' '}
                                                <a href="/docs/api" className="text-brand-green hover:underline">API</a>.
                                            </p>
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

function Row({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium tabular-nums">{formatBRL(value)}</span>
        </div>
    );
}

function ConsumptionChart({ data }: { data: ConsumptionPoint[] }) {
    const max = Math.max(1, ...data.map((d) => d.count));

    return (
        <div className="flex h-44 items-end gap-3 pt-2">
            {data.map((d) => (
                <div key={d.date} className="flex flex-1 flex-col items-center gap-2">
                    <span className="text-xs font-medium tabular-nums text-muted-foreground">{d.count || ''}</span>
                    <div className="flex w-full flex-1 items-end">
                        <div
                            className="w-full rounded-t-md bg-gradient-to-t from-brand-green/60 to-brand-green transition-all"
                            style={{ height: `${(d.count / max) * 100}%`, minHeight: d.count > 0 ? 6 : 2 }}
                            title={`${d.count} consulta(s)`}
                        />
                    </div>
                    <span className="text-[10px] text-muted-foreground">{d.date.slice(5)}</span>
                </div>
            ))}
        </div>
    );
}

function formatDate(value: string): string {
    const d = new Date(value.replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('pt-BR');
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Painel', href: dashboard() }],
};
