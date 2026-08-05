import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/datetime';

interface TicketRow {
    id: string;
    category_label: string;
    title: string;
    status: string;
    status_label: string;
    messages_count: number;
    last_reply_at: string | null;
    created_at: string | null;
    is_unread: boolean;
    is_new: boolean;
    client_name: string | null;
    client_email: string | null;
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

export default function AdminTicketsIndex() {
    const { tickets, filters, categories } = usePage<PageProps>().props;
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (next: Partial<typeof filters>) => {
        router.get('/admin/tickets', { ...filters, ...next }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Tickets de suporte" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tickets de suporte"
                    description="Fila de atendimento dos clientes."
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
                        className="flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q });
                        }}
                    >
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar título, nome ou e-mail"
                            className="h-8 w-64"
                        />
                        <Button type="submit" size="sm" variant="outline">
                            <Search className="size-3.5" />
                        </Button>
                    </form>
                </div>

                <Card className="gap-0 py-0">
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-6 py-3 font-medium">Cliente</th>
                                    <th className="px-6 py-3 font-medium">Assunto</th>
                                    <th className="px-6 py-3 font-medium">Categoria</th>
                                    <th className="px-6 py-3 font-medium">Status</th>
                                    <th className="px-6 py-3 font-medium">Msgs</th>
                                    <th className="px-6 py-3 font-medium">Atualizado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tickets.data.map((t) => (
                                    <tr key={t.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                        <td className="px-6 py-3">
                                            <div className="font-medium">{t.client_name}</div>
                                            <div className="text-xs text-muted-foreground">{t.client_email}</div>
                                        </td>
                                        <td className="px-6 py-3">
                                            <Link href={`/admin/tickets/${t.id}`} className="font-medium hover:underline">
                                                {t.title}
                                            </Link>
                                            {t.is_new && (
                                                <Badge className="ml-2 border-transparent bg-red-100 text-red-700" variant="outline">
                                                    novo
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">{t.category_label}</td>
                                        <td className="px-6 py-3">
                                            <Badge variant="outline" className={statusStyles[t.status]}>
                                                {t.status_label}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">{t.messages_count}</td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {formatDateTime(t.last_reply_at ?? t.created_at)}
                                        </td>
                                    </tr>
                                ))}
                                {tickets.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-10 text-center text-muted-foreground">
                                            Nenhum ticket encontrado.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                        <div className="px-6 py-3">
                            <Pagination paginator={tickets} />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminTicketsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Tickets', href: '/admin/tickets' },
    ],
};
