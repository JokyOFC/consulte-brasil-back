import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface PageProps {
    categories: { value: string; label: string }[];
    [key: string]: unknown;
}

export default function ClientTicketsCreate() {
    const { categories } = usePage<PageProps>().props;
    const form = useForm<{
        category: string;
        title: string;
        body: string;
        attachments: File[];
    }>({
        category: categories[0]?.value ?? 'technical',
        title: '',
        body: '',
        attachments: [],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/client/tickets', { forceFormData: true });
    };

    return (
        <>
            <Head title="Novo chamado" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Novo chamado"
                    description="Descreva o problema com o máximo de detalhes possível."
                    actions={
                        <Button variant="ghost" asChild>
                            <Link href="/client/tickets">Cancelar</Link>
                        </Button>
                    }
                />

                <Card>
                    <CardContent className="p-6">
                        <form className="space-y-4" onSubmit={submit}>
                            <div className="space-y-2">
                                <Label htmlFor="category">Categoria</Label>
                                <select
                                    id="category"
                                    className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    value={form.data.category}
                                    onChange={(e) => form.setData('category', e.target.value)}
                                >
                                    {categories.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.category && (
                                    <p className="text-xs text-destructive">{form.errors.category}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="title">Título</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                    maxLength={255}
                                    required
                                />
                                {form.errors.title && <p className="text-xs text-destructive">{form.errors.title}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="body">Descrição</Label>
                                <textarea
                                    id="body"
                                    className="min-h-40 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={form.data.body}
                                    onChange={(e) => form.setData('body', e.target.value)}
                                    maxLength={20000}
                                    required
                                />
                                {form.errors.body && <p className="text-xs text-destructive">{form.errors.body}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="attachments">Anexos (opcional)</Label>
                                <Input
                                    id="attachments"
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    onChange={(e) =>
                                        form.setData('attachments', Array.from(e.target.files ?? []).slice(0, 5))
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Até 5 arquivos · JPG, PNG ou PDF · máx. 10 MB cada
                                </p>
                                {form.errors.attachments && (
                                    <p className="text-xs text-destructive">{form.errors.attachments}</p>
                                )}
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button type="submit" disabled={form.processing}>
                                    Abrir chamado
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ClientTicketsCreate.layout = {
    breadcrumbs: [
        { title: 'Painel', href: '/dashboard' },
        { title: 'Suporte', href: '/client/tickets' },
        { title: 'Novo', href: '/client/tickets/create' },
    ],
};
