import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Plus, Settings2, Users, Wallet } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL } from '@/lib/format';

interface AccountRow {
    id: string;
    name: string;
    document: string;
    document_type: string;
    status: string;
    balance: number;
    reserved: number;
    available: number;
}

interface PageProps {
    accounts: AccountRow[];
    [key: string]: unknown;
}

export default function AdminAccountsIndex() {
    usePageFlash();
    const { accounts } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Clientes" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Clientes"
                    description="Contas pagantes, saldos e operações administrativas."
                    actions={<CreateAccountDialog />}
                />

                <Card className="gap-0 py-0">
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-6 py-3 font-medium">Cliente</th>
                                    <th className="px-6 py-3 font-medium">Documento</th>
                                    <th className="px-6 py-3 font-medium">Status</th>
                                    <th className="px-6 py-3 text-right font-medium">Disponível</th>
                                    <th className="px-6 py-3 text-right font-medium">Saldo</th>
                                    <th className="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {accounts.map((a) => (
                                    <tr key={a.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                        <td className="px-6 py-3">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-9">
                                                    <AvatarFallback className="bg-brand-green/10 text-xs font-medium text-brand-green">
                                                        {initials(a.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <Link
                                                    href={`/admin/accounts/${a.id}`}
                                                    className="font-medium hover:text-brand-green hover:underline"
                                                >
                                                    {a.name}
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="px-6 py-3">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <Badge variant="outline" className="uppercase">{a.document_type}</Badge>
                                                {a.document}
                                            </div>
                                        </td>
                                        <td className="px-6 py-3">
                                            <Badge
                                                variant="outline"
                                                className={a.status === 'active'
                                                    ? 'border-transparent bg-green-100 text-green-700'
                                                    : 'border-transparent bg-muted text-muted-foreground'}
                                            >
                                                {a.status === 'active' ? 'Ativa' : 'Suspensa'}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-3 text-right font-semibold tabular-nums">{formatBRL(a.available)}</td>
                                        <td className="px-6 py-3 text-right tabular-nums text-muted-foreground">{formatBRL(a.balance)}</td>
                                        <td className="px-6 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={`/admin/finance?account_id=${a.id}`}>
                                                        <Wallet /> Financeiro
                                                    </Link>
                                                </Button>
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/admin/accounts/${a.id}`}>
                                                        <Settings2 /> Gerenciar
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {accounts.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center">
                                            <Users className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                            <p className="text-sm text-muted-foreground">Nenhum cliente cadastrado ainda.</p>
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

function CreateAccountDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', document: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/accounts', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button><Plus /> Nova conta</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nova conta de cliente</DialogTitle>
                    <DialogDescription>A carteira é criada automaticamente.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="acc-name">Nome / Razão social</Label>
                        <Input id="acc-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="acc-doc">CPF ou CNPJ</Label>
                        <Input id="acc-doc" value={form.data.document} onChange={(e) => form.setData('document', e.target.value)} placeholder="000.000.000-00" required />
                        {form.errors.document && <p className="text-sm text-destructive">{form.errors.document}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>Criar conta</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');
}

AdminAccountsIndex.layout = {
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Clientes', href: '/admin/accounts' },
    ],
};
