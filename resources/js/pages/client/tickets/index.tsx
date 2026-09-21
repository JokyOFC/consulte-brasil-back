import { Head, Link, router, usePage } from '@inertiajs/react';
import { Headset, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/datetime';
import { usePageFlash } from '@/hooks/use-page-flash';

interface TicketRow {
    id: string;
    category: string;
    category_label: string;
    title: string;
    status: string;
    status_label: string;
    messages_count: number;
    last_reply_at: string | null;
    created_at: string | null;
    is_unread: boolean;
    is_new: boolean;
}

interface PageProps {
    tickets: Paginator<TicketRow>;
    filters: { status: string; category: string; q: string };
    categories: { value: string; label: string }[];
    [key: string]: unknown;
}

const statusStyles: Record<string, string> = {
    open: 'border-transparent bg-amber-100 text-amber-700',
    in_progress: 'border-transparent bg-blue-100 text-blue-700',
    closed: 'border-transparent bg-muted text-muted-foreground',
};

export default function ClientTicketsIndex() {
    usePageFlash();
    const { tickets, filters, categories } = usePage<PageProps>().props;
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (next: Partial<typeof filters>) => {
        router.get('/client/tickets', { ...filters, ...next }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Suporte / Chamados" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Suporte / Chamados"
                    description="Abra chamados e acompanhe a conversa com a equipe."
                    actions={
                        <Button asChild>
                            <Link href="/client/tickets/create">
                                <Plus className="size-4" />
                                Novo chamado
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    {['all', 'open', 'in_progress', 'closed'].map((s) => (
                        <Button
                            key={s}
                            size="sm"
                            variant={filters.status === s ? 'default' : 'outline'}
                            onClick={() => apply({ status: s })}
                        >
                            {s === 'all' ? 'Todos' : s === 'open' ? 'Aberto' : s === 'in_progress' ? 'Em andamento' : 'Encerrado'}
                        </Button>
                    ))}
                    <select
                        className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                        value={filters.category}
                        onChange={(e) => apply({ category: e.target.value })}
                    >
                        <option value="all">Todas as categorias</option>
                        {categories.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                    <form
                        className="flex w-full gap-2 sm:w-auto"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q });
                        }}
                    >
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar título ou descrição"
                            className="h-8 w-full sm:w-56"
                        />
                        <Button type="submit" size="sm" variant="outline">
                            <Search className="size-3.5" />
                        </Button>
                    </form>
                </div>

                <Card className="gap-0 py-0">
                    <CardContent className="p-0">
                        {tickets.data.length === 0 ? (
                            <div className="px-6 py-12 text-center text-sm text-muted-foreground">
                                <Headset className="mx-auto mb-2 size-6 opacity-50" />
                                Nenhum chamado encontrado.
                            </div>
                        ) : (
                            // overflow-x-auto: no celular a tabela rola dentro do card em vez de ser cortada.
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[640px] text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr className="border-b border-border">
                                            <th className="px-4 py-3 font-medium sm:px-6">Assunto</th>
                                            <th className="px-4 py-3 font-medium sm:px-6">Categoria</th>
                                            <th className="px-4 py-3 font-medium sm:px-6">Status</th>
                                            <th className="px-4 py-3 font-medium sm:px-6">Msgs</th>
                                            <th className="px-4 py-3 font-medium sm:px-6">Atualizado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tickets.data.map((t) => (
                                            <tr key={t.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                                <td className="px-4 py-3 sm:px-6">
                                                    <Link href={`/client/tickets/${t.id}`} className="font-medium hover:underline">
                                                        {t.title}
                                                    </Link>
                                                    {t.is_new && (
                                                        <Badge className="ml-2 border-transparent bg-red-100 text-red-700" variant="outline">
                                                            novo
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-muted-foreground sm:px-6">{t.category_label}</td>
                                                <td className="px-4 py-3 whitespace-nowrap sm:px-6">
                                                    <Badge variant="outline" className={statusStyles[t.status]}>
                                                        {t.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground sm:px-6">{t.messages_count}</td>
                                                <td className="px-4 py-3 whitespace-nowrap text-muted-foreground sm:px-6">
                                                    {formatDateTime(t.last_reply_at ?? t.created_at)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <div className="px-4 py-3 sm:px-6">
                            <Pagination paginator={tickets} />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ClientTicketsIndex.layout = {
    breadcrumbs: [
        { title: 'Painel', href: '/dashboard' },
        { title: 'Suporte', href: '/client/tickets' },
    ],
};
