import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Coins,
    Database,
    FileSearch,
    Loader2,
    Search,
    Wallet,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageFlash } from '@/hooks/use-page-flash';
import { formatBRL, formatDocument } from '@/lib/format';
import { cn } from '@/lib/utils';

interface QueryTypeRow {
    code: string;
    name: string;
    description: string | null;
    group: string;
    price_cents: number;
    param_field: string;
    param_label: string;
    param_placeholder: string;
}

interface WalletInfo {
    balance: number;
    reserved: number;
    available: number;
}

interface ConsultationResult {
    consultation_id: string;
    query_type: string;
    amount_charged: number;
    from_cache: boolean;
    data: Record<string, unknown>;
}

interface PageProps {
    query_types: QueryTypeRow[];
    wallet: WalletInfo | null;
    result: ConsultationResult | null;
    selected_query_type: string | null;
    [key: string]: unknown;
}

interface QueryTypeGroup {
    group: string;
    items: QueryTypeRow[];
}

function groupQueryTypes(items: QueryTypeRow[]): QueryTypeGroup[] {
    const groups = new Map<string, QueryTypeRow[]>();

    for (const item of items) {
        const bucket = groups.get(item.group) ?? [];
        bucket.push(item);
        groups.set(item.group, bucket);
    }

    return Array.from(groups.entries()).map(([group, groupItems]) => ({
        group,
        items: groupItems.sort((left, right) => left.name.localeCompare(right.name, 'pt-BR')),
    }));
}

export default function ClientConsultationsIndex() {
    usePageFlash();
    const { query_types, wallet, result, selected_query_type } = usePage<PageProps>().props;
    const [search, setSearch] = useState('');
    const [selectedCode, setSelectedCode] = useState<string | null>(selected_query_type ?? null);

    const selected = useMemo(
        () => query_types.find((item) => item.code === selectedCode) ?? null,
        [query_types, selectedCode],
    );

    const groups = useMemo(() => {
        const needle = search.trim().toLowerCase();
        const filtered = needle
            ? query_types.filter(
                  (item) =>
                      item.code.toLowerCase().includes(needle) ||
                      item.name.toLowerCase().includes(needle) ||
                      item.group.toLowerCase().includes(needle),
              )
            : query_types;

        return groupQueryTypes(filtered);
    }, [query_types, search]);

    return (
        <>
            <Head title="Consultas" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Consultas"
                    description="Execute consultas diretamente pelo painel — o valor é debitado do seu saldo."
                />

                {wallet && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatCard
                            label="Saldo disponível"
                            value={formatBRL(wallet.available)}
                            icon={Coins}
                            highlight
                        />
                        <StatCard label="Saldo total" value={formatBRL(wallet.balance)} icon={Wallet} />
                        <StatCard label="Reservado" value={formatBRL(wallet.reserved)} icon={Database} />
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-5">
                    <Card className="gap-0 py-0 lg:col-span-2">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">Tipos disponíveis</CardTitle>
                            <div className="relative mt-3">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Buscar por nome ou código…"
                                    className="pl-9"
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="max-h-[32rem] overflow-y-auto p-3">
                            {groups.length === 0 ? (
                                <p className="px-2 py-8 text-center text-sm text-muted-foreground">
                                    Nenhum tipo encontrado.
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {groups.map((group) => (
                                        <Collapsible key={group.group} defaultOpen={search.length > 0}>
                                            <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-sm font-medium hover:bg-muted/60">
                                                <span>{group.group}</span>
                                                <ChevronDown className="size-4 text-muted-foreground" />
                                            </CollapsibleTrigger>
                                            <CollapsibleContent className="space-y-1 pb-2 pl-1">
                                                {group.items.map((item) => (
                                                    <button
                                                        key={item.code}
                                                        type="button"
                                                        onClick={() => setSelectedCode(item.code)}
                                                        className={cn(
                                                            'flex w-full flex-col gap-0.5 rounded-lg px-3 py-2.5 text-left transition-colors',
                                                            selectedCode === item.code
                                                                ? 'bg-brand-green/10 ring-1 ring-brand-green/30'
                                                                : 'hover:bg-muted/50',
                                                        )}
                                                    >
                                                        <span className="text-sm font-medium">{item.name}</span>
                                                        <span className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                                            <code>{item.code}</code>
                                                            <span>{formatBRL(item.price_cents)}</span>
                                                        </span>
                                                    </button>
                                                ))}
                                            </CollapsibleContent>
                                        </Collapsible>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-6 lg:col-span-3">
                        {selected ? (
                            <ConsultationForm
                                key={selected.code}
                                queryType={selected}
                                wallet={wallet}
                            />
                        ) : (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                                    <FileSearch className="size-10 text-muted-foreground/50" />
                                    <p className="text-sm text-muted-foreground">
                                        Selecione um tipo de consulta à esquerda para começar.
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {result && selected && result.query_type === selected.code && (
                            <ConsultationResultPanel result={result} queryType={selected} />
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function ConsultationForm({
    queryType,
    wallet,
}: {
    queryType: QueryTypeRow;
    wallet: WalletInfo | null;
}) {
    const field = queryType.param_field;
    const form = useForm<Record<string, string>>({
        [field]: '',
    });

    const insufficientBalance =
        wallet !== null && wallet.available < queryType.price_cents;

    function handleSubmit() {
        form.post(`/client/consultations/${queryType.code}`, {
            preserveScroll: true,
        });
    }

    function handleChange(value: string) {
        const formatted =
            field === 'document' && queryType.param_field === 'document'
                ? formatDocument(value)
                : value;

        form.setData(field, formatted);
    }

    return (
        <Card>
            <CardHeader className="border-b border-border">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle className="text-lg">{queryType.name}</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Código <code className="text-xs">{queryType.code}</code>
                        </p>
                    </div>
                    <Badge variant="secondary">{formatBRL(queryType.price_cents)} por consulta</Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-5 pt-6">
                {queryType.description && (
                    <p className="text-sm text-muted-foreground">{queryType.description}</p>
                )}

                <div className="grid gap-1.5">
                    <Label htmlFor={`param-${field}`}>{queryType.param_label}</Label>
                    <Input
                        id={`param-${field}`}
                        value={form.data[field] ?? ''}
                        onChange={(event) => handleChange(event.target.value)}
                        placeholder={queryType.param_placeholder}
                        autoComplete="off"
                        disabled={form.processing}
                    />
                    <InputError message={form.errors[field]} />
                </div>

                {insufficientBalance && (
                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
                        Saldo insuficiente para esta consulta.{' '}
                        <Link href="/client/billing" className="font-medium underline">
                            Recarregar carteira
                        </Link>
                    </div>
                )}

                <Button
                    type="button"
                    onClick={handleSubmit}
                    disabled={form.processing || insufficientBalance || !(form.data[field] ?? '').trim()}
                    className="w-full sm:w-auto"
                >
                    {form.processing ? (
                        <>
                            <Loader2 className="animate-spin" /> Consultando…
                        </>
                    ) : (
                        <>
                            <Search /> Realizar consulta
                        </>
                    )}
                </Button>
            </CardContent>
        </Card>
    );
}

function ConsultationResultPanel({
    result,
    queryType,
}: {
    result: ConsultationResult;
    queryType: QueryTypeRow;
}) {
    const { display, raw } = splitResultData(result.data);

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b border-border py-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <CardTitle className="text-base">Resultado</CardTitle>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span>{formatBRL(result.amount_charged)} debitado</span>
                        {result.from_cache && (
                            <Badge variant="outline">Do cache</Badge>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4 p-0">
                {Object.keys(display).length > 0 && (
                    <dl className="divide-y divide-border">
                        {Object.entries(display).map(([key, value]) => (
                            <div key={key} className="grid gap-1 px-6 py-3 sm:grid-cols-3">
                                <dt className="text-sm font-medium text-muted-foreground">{formatFieldLabel(key)}</dt>
                                <dd className="text-sm sm:col-span-2">{formatFieldValue(value)}</dd>
                            </div>
                        ))}
                    </dl>
                )}

                {raw !== undefined && (
                    <details className="border-t border-border px-6 py-4">
                        <summary className="cursor-pointer text-sm font-medium text-muted-foreground">
                            Resposta completa (JSON)
                        </summary>
                        <pre className="mt-3 max-h-80 overflow-auto rounded-lg bg-muted/50 p-4 text-xs">
                            {JSON.stringify(raw, null, 2)}
                        </pre>
                    </details>
                )}

                <p className="border-t border-border px-6 py-3 text-xs text-muted-foreground">
                    ID da consulta: <code>{result.consultation_id}</code> · Tipo: {queryType.name}
                </p>
            </CardContent>
        </Card>
    );
}

function splitResultData(data: Record<string, unknown>): {
    display: Record<string, unknown>;
    raw: unknown;
} {
    const { raw, ...rest } = data;

    return { display: rest, raw };
}

function formatFieldLabel(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatFieldValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

ClientConsultationsIndex.layout = {
    breadcrumbs: [{ title: 'Consultas', href: '/client/consultations' }],
};
