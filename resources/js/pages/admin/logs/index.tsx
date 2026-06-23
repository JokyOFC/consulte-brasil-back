import { Head, Link, router, usePage } from '@inertiajs/react';
import { ScrollText, Search, X } from 'lucide-react';
import { useState } from 'react';
import { LogDetailDialog, LogStatusBadge, LogSummaryCell } from '@/components/log-detail-dialog';
import type { RequestLog } from '@/components/log-detail-dialog';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/datetime';

interface AccountOption {
    id: string;
    name: string;
}

interface PageProps {
    logs: Paginator<RequestLog>;
    filters: { status: string; q: string; account_id: string };
    accounts: AccountOption[];
    selected_account: AccountOption | null;
    [key: string]: unknown;
}

const STATUS_TABS = [
    { id: 'all', label: 'Todos' },
    { id: 'success', label: 'Sucesso' },
    { id: 'error', label: 'Erro' },
];

export default function AdminLogsIndex() {
    const { logs, filters, accounts, selected_account } = usePage<PageProps>().props;
    const [q, setQ] = useState(filters.q ?? '');
    const [selected, setSelected] = useState<RequestLog | null>(null);

    const applyFilters = (next: Partial<{ status: string; q: string; account_id: string }>) => {
        router.get('/admin/logs', { ...filters, ...next }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Logs" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Logs de requisições"
                    description="Auditoria das chamadas à API pública: corpo, status, latência, erros e consultas vinculadas."
                />

                {selected_account && (
                    <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
                        <span className="text-muted-foreground">Filtrando cliente:</span>
                        <span className="font-medium">{selected_account.name}</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="ml-auto h-7"
                            onClick={() => applyFilters({ account_id: '' })}
                        >
                            <X className="size-3.5" />
                            Limpar filtro
                        </Button>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex overflow-hidden rounded-lg border border-border">
                        {STATUS_TABS.map((opt) => (
                            <button
                                key={opt.id}
                                type="button"
                                onClick={() => applyFilters({ status: opt.id })}
                                className={`px-3 py-1.5 text-sm transition-colors ${
                                    (filters.status ?? 'all') === opt.id
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {opt.label}
                            </button>
                        ))}
                    </div>

                    <Select
                        value={filters.account_id || '__all__'}
                        onValueChange={(value) => applyFilters({ account_id: value === '__all__' ? '' : value })}
                    >
                        <SelectTrigger className="w-[220px]">
                            <SelectValue placeholder="Todos os clientes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">Todos os clientes</SelectItem>
                            {accounts.map((account) => (
                                <SelectItem key={account.id} value={account.id}>{account.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            applyFilters({ q });
                        }}
                        className="flex items-center gap-2"
                    >
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Buscar rota ou caminho…"
                                className="pl-8 sm:w-72"
                            />
                        </div>
                        <Button type="submit" variant="outline">Filtrar</Button>
                    </form>
                </div>

                <Card className="gap-0 py-0">
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full min-w-[960px] text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-4 py-3 font-medium">Data (Brasília)</th>
                                    <th className="px-4 py-3 font-medium">Cliente</th>
                                    <th className="px-4 py-3 font-medium">Requisição</th>
                                    <th className="px-4 py-3 font-medium">Detalhe</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 text-right font-medium">Latência</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                        <td className="whitespace-nowrap px-4 py-3 text-muted-foreground">
                                            {formatDateTime(log.created_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {log.account_id ? (
                                                <Link
                                                    href={`/admin/logs?account_id=${log.account_id}`}
                                                    className="hover:text-primary hover:underline"
                                                >
                                                    {log.account_name ?? '—'}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="outline" className="font-mono">{log.method}</Badge>
                                                    <code className="text-xs">{log.path}</code>
                                                </div>
                                                {log.query_type && (
                                                    <Badge variant="secondary" className="font-mono text-[10px]">
                                                        {log.query_type}
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3"><LogSummaryCell log={log} /></td>
                                        <td className="px-4 py-3"><LogStatusBadge log={log} /></td>
                                        <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                            {log.duration_ms != null ? `${log.duration_ms}ms` : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" onClick={() => setSelected(log)}>
                                                Detalhes
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {logs.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-12 text-center">
                                            <ScrollText className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                            <p className="text-sm text-muted-foreground">Nenhuma requisição registrada.</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Pagination paginator={logs} />
            </div>

            <LogDetailDialog log={selected} onOpenChange={(open) => !open && setSelected(null)} showAccount />
        </>
    );
}

AdminLogsIndex.layout = {
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Logs', href: '/admin/logs' },
    ],
};
