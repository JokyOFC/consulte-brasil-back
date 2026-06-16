import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Coins, Copy, KeyRound, Lock, Plus, Trash2, Wallet } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
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

interface KeyRow {
    id: string;
    name: string;
    prefix: string;
    last_four: string;
    status: string;
    last_used_at: string | null;
    created_at: string;
}

interface WalletInfo {
    balance: number;
    reserved: number;
    available: number;
}

interface PageProps {
    keys: KeyRow[];
    wallet: WalletInfo | null;
    flash?: { plain_token?: string };
    [key: string]: unknown;
}

export default function ClientApiKeysIndex() {
    usePageFlash();
    const { keys, wallet, flash } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Minhas chaves" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Minhas chaves de API"
                    description="Autentique com Authorization: Bearer cb_live_…"
                    actions={<CreateKeyDialog />}
                />

                {wallet && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatCard label="Saldo disponível" value={formatBRL(wallet.available)} icon={Coins} highlight />
                        <StatCard label="Saldo total" value={formatBRL(wallet.balance)} icon={Wallet} />
                        <StatCard label="Reservado" value={formatBRL(wallet.reserved)} icon={Lock} />
                    </div>
                )}

                {flash?.plain_token && <TokenReveal token={flash.plain_token} />}

                <Card className="gap-0 py-0">
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-6 py-3 font-medium">Nome</th>
                                    <th className="px-6 py-3 font-medium">Chave</th>
                                    <th className="px-6 py-3 font-medium">Status</th>
                                    <th className="px-6 py-3 font-medium">Último uso</th>
                                    <th className="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {keys.map((k) => (
                                    <tr key={k.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                                        <td className="px-6 py-3 font-medium">{k.name}</td>
                                        <td className="px-6 py-3 font-mono text-xs text-muted-foreground">
                                            {k.prefix}…{k.last_four}
                                        </td>
                                        <td className="px-6 py-3">
                                            <Badge
                                                variant="outline"
                                                className={k.status === 'active'
                                                    ? 'border-transparent bg-green-100 text-green-700'
                                                    : 'border-transparent bg-muted text-muted-foreground'}
                                            >
                                                {k.status === 'active' ? 'Ativa' : 'Revogada'}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {k.last_used_at ? formatDate(k.last_used_at) : 'nunca'}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {k.status === 'active' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        if (confirm(`Revogar a chave "${k.name}"? Esta ação não pode ser desfeita.`)) {
                                                            router.delete(`/client/api-keys/${k.id}`, { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    <Trash2 /> Revogar
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {keys.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center">
                                            <KeyRound className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                            <p className="text-sm text-muted-foreground">
                                                Nenhuma chave emitida. Crie a primeira para usar a API.
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

function TokenReveal({ token }: { token: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(token);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            /* clipboard indisponível */
        }
    };

    return (
        <Card className="gap-0 border-brand-green/40 bg-brand-green/5 py-0">
            <CardContent className="space-y-3 p-5">
                <div className="flex items-center gap-2 text-sm font-medium text-brand-green">
                    <KeyRound className="size-4" /> Chave gerada — copie agora, não exibiremos novamente.
                </div>
                <div className="flex items-center gap-2">
                    <code className="flex-1 overflow-x-auto rounded-md border border-border bg-background px-3 py-2 font-mono text-xs">
                        {token}
                    </code>
                    <Button variant="outline" size="sm" onClick={copy}>
                        {copied ? <Check className="text-brand-green" /> : <Copy />}
                        {copied ? 'Copiado' : 'Copiar'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function CreateKeyDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/client/api-keys', {
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
                <Button><Plus /> Nova chave</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nova chave de API</DialogTitle>
                    <DialogDescription>Dê um nome para identificar onde a chave será usada.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="key-name">Nome</Label>
                        <Input
                            id="key-name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="backend-produção"
                            required
                        />
                        {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>Emitir chave</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function formatDate(value: string): string {
    const d = new Date(value.replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('pt-BR');
}

ClientApiKeysIndex.layout = {
    breadcrumbs: [{ title: 'Minhas chaves', href: '/client/api-keys' }],
};
