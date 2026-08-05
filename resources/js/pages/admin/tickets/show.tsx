import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, Paperclip } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/datetime';
import { usePageFlash } from '@/hooks/use-page-flash';
import { cn } from '@/lib/utils';

interface Attachment {
    id: string;
    original_name: string;
    download_url: string;
}

interface Message {
    id: string;
    body: string;
    is_staff: boolean;
    author_name: string;
    created_at: string | null;
    attachments: Attachment[];
}

interface Ticket {
    id: string;
    category_label: string;
    title: string;
    body: string;
    status: string;
    status_label: string;
    created_at: string | null;
    client_name: string | null;
    client_email: string | null;
    opening_attachments: Attachment[];
    messages: Message[];
}

interface PageProps {
    ticket: Ticket;
    statuses: { value: string; label: string }[];
    [key: string]: unknown;
}

function Attachments({ items }: { items: Attachment[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="mt-3 flex flex-wrap gap-2">
            {items.map((a) => (
                <a
                    key={a.id}
                    href={a.download_url}
                    className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs hover:bg-muted"
                >
                    <Download className="size-3" />
                    {a.original_name}
                </a>
            ))}
        </div>
    );
}

export default function AdminTicketShow() {
    usePageFlash();
    const { ticket, statuses } = usePage<PageProps>().props;
    const form = useForm<{ body: string; attachments: File[] }>({
        body: '',
        attachments: [],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/tickets/${ticket.id}/reply`, {
            forceFormData: true,
            onSuccess: () => form.reset('body', 'attachments'),
        });
    };

    const changeStatus = (status: string) => {
        router.patch(`/admin/tickets/${ticket.id}`, { status }, { preserveScroll: true });
    };

    return (
        <>
            <Head title={ticket.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={ticket.title}
                    description={`${ticket.client_name} · ${ticket.client_email} · ${ticket.category_label}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <select
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={ticket.status}
                                onChange={(e) => changeStatus(e.target.value)}
                            >
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                            <Badge variant="outline">{ticket.status_label}</Badge>
                            <Button variant="ghost" asChild>
                                <Link href="/admin/tickets">Voltar</Link>
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardContent className="space-y-2 p-5">
                        <div className="text-xs font-medium text-muted-foreground">
                            Aberto em {formatDateTime(ticket.created_at)}
                        </div>
                        <p className="whitespace-pre-wrap text-sm">{ticket.body}</p>
                        <Attachments items={ticket.opening_attachments} />
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    {ticket.messages.map((m) => (
                        <Card
                            key={m.id}
                            className={cn(m.is_staff && 'border-brand-green/40 bg-brand-green/5')}
                        >
                            <CardContent className="space-y-2 p-5">
                                <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        {m.author_name}
                                        {m.is_staff ? ' · Equipe' : ' · Cliente'}
                                    </span>
                                    <span>{formatDateTime(m.created_at)}</span>
                                </div>
                                <p className="whitespace-pre-wrap text-sm">{m.body}</p>
                                <Attachments items={m.attachments} />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardContent className="p-5">
                        <form className="space-y-4" onSubmit={submit}>
                            <div className="space-y-2">
                                <Label htmlFor="body">Responder como equipe</Label>
                                <textarea
                                    id="body"
                                    className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={form.data.body}
                                    onChange={(e) => form.setData('body', e.target.value)}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="attachments" className="inline-flex items-center gap-1">
                                    <Paperclip className="size-3.5" />
                                    Anexos
                                </Label>
                                <Input
                                    id="attachments"
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    onChange={(e) =>
                                        form.setData('attachments', Array.from(e.target.files ?? []).slice(0, 5))
                                    }
                                />
                            </div>
                            <div className="flex justify-end">
                                <Button type="submit" disabled={form.processing}>
                                    Enviar resposta
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminTicketShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Tickets', href: '/admin/tickets' },
        { title: 'Detalhe', href: '#' },
    ],
};
