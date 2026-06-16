import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Check, Coins, Layers, Plus, Repeat, Settings2, Sparkles } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL } from '@/lib/format';
import { cn } from '@/lib/utils';

interface PlanRow {
    id: string;
    name: string;
    slug: string;
    price_cents: number;
    currency: string;
    billing_period: string;
    included_credits: number;
    overage_price_cents: number | null;
    status: string;
}

interface PageProps {
    plans: PlanRow[];
    [key: string]: unknown;
}

export default function AdminPlansIndex() {
    usePageFlash();
    const { plans } = usePage<PageProps>().props;

    const sortedPlans = useMemo(
        () => [...plans].sort((a, b) => a.price_cents - b.price_cents),
        [plans],
    );

    const featuredPlanId = useMemo(() => {
        if (sortedPlans.length < 2) {
            return null;
        }

        return sortedPlans[Math.floor(sortedPlans.length / 2)]?.id ?? null;
    }, [sortedPlans]);

    return (
        <>
            <Head title="Planos" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Planos"
                    description="Catálogo de planos vendáveis e o saldo incluso em cada um."
                    actions={<CreatePlanDialog />}
                />

                {plans.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Layers className="size-8 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">Nenhum plano cadastrado ainda.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="mx-auto grid w-full max-w-5xl grid-cols-1 items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {sortedPlans.map((plan) => (
                            <PlanCatalogCard
                                key={plan.id}
                                plan={plan}
                                featured={plan.id === featuredPlanId}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function PlanCatalogCard({
    plan,
    featured,
}: {
    plan: PlanRow;
    featured: boolean;
}) {
    const isActive = plan.status === 'active';
    const billingLabel = plan.billing_period === 'monthly' ? '/mês' : ' único';

    const destaqueBadge = (
        <Badge className="h-5 border-0 bg-brand-green px-2 text-[10px] text-white">
            <Sparkles className="mr-1 size-3" />
            Destaque
        </Badge>
    );

    return (
        <Card
            className={cn(
                'flex h-full w-full flex-col gap-0 py-0',
                featured && 'bg-brand-green/[0.04]',
                !isActive && 'border-dashed opacity-80',
            )}
        >
            <CardHeader className="shrink-0 gap-0 pb-4 pt-6">
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <CardTitle className="text-lg leading-tight">
                            <Link href={`/admin/plans/${plan.id}`} className="hover:text-brand-green hover:underline">
                                {plan.name}
                            </Link>
                        </CardTitle>
                        <p className="mt-1 text-xs text-muted-foreground">{plan.slug}</p>
                    </div>
                    <div className="flex h-12 w-[6.75rem] shrink-0 flex-col items-end justify-between">
                        {featured && isActive ? (
                            destaqueBadge
                        ) : (
                            <span className="pointer-events-none invisible flex h-5 items-center" aria-hidden>
                                {destaqueBadge}
                            </span>
                        )}
                        <Badge
                            variant="outline"
                            className={cn(
                                'h-5 px-2 text-[10px]',
                                isActive
                                    ? 'border-transparent bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                                    : 'border-transparent bg-muted text-muted-foreground',
                            )}
                        >
                            {isActive ? 'Ativo' : 'Arquivado'}
                        </Badge>
                    </div>
                </div>
            </CardHeader>

            <CardContent className="grow space-y-4 pb-6">
                <div>
                    <span className="text-3xl font-bold tabular-nums tracking-tight">
                        {formatBRL(plan.price_cents)}
                    </span>
                    <span className="text-sm text-muted-foreground">{billingLabel}</span>
                </div>

                <ul className="space-y-1.5 text-sm">
                    <li className="flex items-center gap-2">
                        <Check className="size-4 shrink-0 text-brand-green" />
                        <span>
                            <strong>{formatBRL(plan.included_credits)}</strong> de saldo incluso
                        </span>
                    </li>
                    <li className="flex items-center gap-2 text-muted-foreground">
                        <Repeat className="size-4 shrink-0 text-brand-green" />
                        Cobrança {plan.billing_period === 'monthly' ? 'mensal' : 'única'}
                    </li>
                    {plan.overage_price_cents !== null && (
                        <li className="flex items-center gap-2 text-muted-foreground">
                            <Coins className="size-4 shrink-0 text-brand-green" />
                            Excedente: {formatBRL(plan.overage_price_cents)}/consulta
                        </li>
                    )}
                </ul>
            </CardContent>

            <div className="mt-auto shrink-0 border-t border-border px-6 pb-6 pt-4">
                <Button variant="outline" className="w-full" asChild>
                    <Link href={`/admin/plans/${plan.id}`}>
                        <Settings2 />
                        Gerenciar
                    </Link>
                </Button>
            </div>
        </Card>
    );
}

function CreatePlanDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        slug: '',
        price_cents: '',
        included_reais: '',
        billing_period: 'monthly',
        overage_price_cents: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            price_cents: Number(d.price_cents),
            included_credits: Math.round(Number(d.included_reais) * 100),
            overage_price_cents: d.overage_price_cents === '' ? null : Number(d.overage_price_cents),
        }));
        form.post('/admin/plans', {
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
                <Button><Plus /> Novo plano</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Novo plano</DialogTitle>
                    <DialogDescription>Defina preço, saldo incluso e período.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Nome" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Slug" error={form.errors.slug}>
                        <Input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} placeholder="growth" required />
                    </Field>
                    <Field label="Preço (centavos)" error={form.errors.price_cents}>
                        <Input type="number" value={form.data.price_cents} onChange={(e) => form.setData('price_cents', e.target.value)} placeholder="14900" required />
                    </Field>
                    <Field label="Saldo incluso (R$)" error={form.errors.included_reais ?? (form.errors as Record<string, string>).included_credits}>
                        <Input type="number" min={0} step="0.01" value={form.data.included_reais} onChange={(e) => form.setData('included_reais', e.target.value)} placeholder="100.00" required />
                    </Field>
                    <Field label="Período">
                        <Select value={form.data.billing_period} onValueChange={(v) => form.setData('billing_period', v)}>
                            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="monthly">Mensal</SelectItem>
                                <SelectItem value="one_time">Pagamento único</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Excedente (centavos, opcional)">
                        <Input type="number" value={form.data.overage_price_cents} onChange={(e) => form.setData('overage_price_cents', e.target.value)} placeholder="—" />
                    </Field>
                    <DialogFooter className="sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>Criar plano</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

AdminPlansIndex.layout = {
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Planos', href: '/admin/plans' },
    ],
};
