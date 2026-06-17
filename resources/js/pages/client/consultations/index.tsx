import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Car,
    CheckCircle2,
    ChevronRight,
    ClipboardCopy,
    Coins,
    FileSearch,
    Gauge,
    Layers,
    Loader2,
    MapPin,
    Search,
    Sparkles,
    User,
    Wallet,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ConsultationResultView } from '@/components/consultation-result-view';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageFlash } from '@/hooks/use-page-flash';
import { splitConsultationResult } from '@/lib/consultation-result';
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

const GROUP_ORDER = [
    'Pessoa Física (CPF)',
    'Pessoa Jurídica (CNPJ)',
    'Veículos',
    'Tabela FIPE',
    'Localização e utilidades',
    'Feriados',
    'Outros',
] as const;

const GROUP_META: Record<string, { icon: LucideIcon; short: string; color: string }> = {
    'Pessoa Física (CPF)': {
        icon: User,
        short: 'CPF',
        color: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 ring-emerald-500/20',
    },
    'Pessoa Jurídica (CNPJ)': {
        icon: Building2,
        short: 'CNPJ',
        color: 'bg-blue-500/10 text-blue-700 dark:text-blue-300 ring-blue-500/20',
    },
    Veículos: {
        icon: Car,
        short: 'Veículos',
        color: 'bg-violet-500/10 text-violet-700 dark:text-violet-300 ring-violet-500/20',
    },
    'Tabela FIPE': {
        icon: Gauge,
        short: 'FIPE',
        color: 'bg-orange-500/10 text-orange-700 dark:text-orange-300 ring-orange-500/20',
    },
    'Localização e utilidades': {
        icon: MapPin,
        short: 'Localização',
        color: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 ring-cyan-500/20',
    },
    Feriados: {
        icon: Calendar,
        short: 'Feriados',
        color: 'bg-amber-500/10 text-amber-700 dark:text-amber-300 ring-amber-500/20',
    },
    Outros: {
        icon: Layers,
        short: 'Outros',
        color: 'bg-muted text-muted-foreground ring-border',
    },
};

function groupQueryTypes(items: QueryTypeRow[]): Map<string, QueryTypeRow[]> {
    const groups = new Map<string, QueryTypeRow[]>();

    for (const item of items) {
        const bucket = groups.get(item.group) ?? [];
        bucket.push(item);
        groups.set(item.group, bucket);
    }

    for (const [group, groupItems] of groups) {
        groups.set(
            group,
            groupItems.sort((left, right) => left.name.localeCompare(right.name, 'pt-BR')),
        );
    }

    return groups;
}

export default function ClientConsultationsIndex() {
    usePageFlash();
    const { query_types, wallet, result, selected_query_type } = usePage<PageProps>().props;
    const [search, setSearch] = useState('');
    const [activeGroup, setActiveGroup] = useState<string>('all');
    const [selectedCode, setSelectedCode] = useState<string | null>(selected_query_type ?? null);
    const formRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (selected_query_type) {
            setSelectedCode(selected_query_type);
            const match = query_types.find((item) => item.code === selected_query_type);
            if (match) {
                setActiveGroup(match.group);
            }
        }
    }, [selected_query_type, query_types]);

    const selected = useMemo(
        () => query_types.find((item) => item.code === selectedCode) ?? null,
        [query_types, selectedCode],
    );

    const filteredTypes = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return query_types.filter((item) => {
            const matchesSearch =
                !needle ||
                item.code.toLowerCase().includes(needle) ||
                item.name.toLowerCase().includes(needle) ||
                item.group.toLowerCase().includes(needle);

            const matchesGroup = activeGroup === 'all' || item.group === activeGroup;

            return matchesSearch && matchesGroup;
        });
    }, [query_types, search, activeGroup]);

    const groupedFiltered = useMemo(() => groupQueryTypes(filteredTypes), [filteredTypes]);

    const availableGroups = useMemo(() => {
        const present = new Set(query_types.map((item) => item.group));

        return GROUP_ORDER.filter((group) => present.has(group));
    }, [query_types]);

    const currentStep = result && selected && result.query_type === selected.code ? 3 : selected ? 2 : 1;

    function selectQueryType(code: string) {
        const item = query_types.find((row) => row.code === code);
        setSelectedCode(code);
        if (item) {
            setActiveGroup(item.group);
        }
        requestAnimationFrame(() => {
            formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    function clearSelection() {
        setSelectedCode(null);
    }

    return (
        <>
            <Head title="Consultas" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Consultas"
                    description="Escolha o serviço, informe o dado solicitado e receba o resultado na hora."
                    actions={
                        wallet ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href="/client/billing">
                                    <Wallet className="size-4" />
                                    Recarregar saldo
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                {wallet && <WalletBanner wallet={wallet} />}

                <StepIndicator step={currentStep} />

                <div className="space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Buscar consulta por nome ou código…"
                                className="h-11 pl-9"
                            />
                        </div>
                        <p className="shrink-0 text-sm text-muted-foreground">
                            {filteredTypes.length} serviço{filteredTypes.length === 1 ? '' : 's'}
                        </p>
                    </div>

                    <div className="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <CategoryChip
                            active={activeGroup === 'all'}
                            onClick={() => setActiveGroup('all')}
                            label="Todos"
                            count={query_types.length}
                        />
                        {availableGroups.map((group) => {
                            const meta = GROUP_META[group] ?? GROUP_META.Outros;
                            const count = query_types.filter((item) => item.group === group).length;

                            return (
                                <CategoryChip
                                    key={group}
                                    active={activeGroup === group}
                                    onClick={() => setActiveGroup(group)}
                                    label={meta.short}
                                    count={count}
                                    icon={meta.icon}
                                />
                            );
                        })}
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-5">
                    <section className="space-y-4 xl:col-span-2">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold tracking-tight">
                                {activeGroup === 'all' ? 'Catálogo de serviços' : activeGroup}
                            </h2>
                            {selected && (
                                <Button variant="ghost" size="sm" onClick={clearSelection} className="h-8 text-xs">
                                    Limpar seleção
                                </Button>
                            )}
                        </div>

                        {filteredTypes.length === 0 ? (
                            <EmptyCatalogState onClear={() => { setSearch(''); setActiveGroup('all'); }} />
                        ) : activeGroup === 'all' && !search ? (
                            <div className="space-y-5">
                                {GROUP_ORDER.filter((group) => groupedFiltered.has(group)).map((group) => (
                                    <ServiceGroupSection
                                        key={group}
                                        group={group}
                                        items={groupedFiltered.get(group) ?? []}
                                        selectedCode={selectedCode}
                                        onSelect={selectQueryType}
                                        collapsed={Boolean(selectedCode)}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                {filteredTypes.map((item) => (
                                    <ServiceCard
                                        key={item.code}
                                        item={item}
                                        selected={selectedCode === item.code}
                                        onSelect={() => selectQueryType(item.code)}
                                    />
                                ))}
                            </div>
                        )}
                    </section>

                    <div ref={formRef} className="flex flex-col gap-6 xl:col-span-3">
                        {selected ? (
                            <>
                                <ConsultationForm queryType={selected} wallet={wallet} onBack={clearSelection} />
                                {result && result.query_type === selected.code && (
                                    <ConsultationResultPanel result={result} queryType={selected} />
                                )}
                            </>
                        ) : (
                            <WelcomePanel />
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function WalletBanner({ wallet }: { wallet: WalletInfo }) {
    const lowBalance = wallet.available < 500;

    return (
        <div
            className={cn(
                'flex flex-col gap-4 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5',
                lowBalance
                    ? 'border-amber-500/30 bg-gradient-to-r from-amber-500/10 to-transparent'
                    : 'border-brand-green/25 bg-gradient-to-r from-brand-green/8 to-transparent',
            )}
        >
            <div className="flex items-center gap-4">
                <div
                    className={cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-2xl',
                        lowBalance ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-brand-green/15 text-brand-green',
                    )}
                >
                    <Coins className="size-6" />
                </div>
                <div>
                    <p className="text-sm text-muted-foreground">Saldo disponível para consultas</p>
                    <p className="text-2xl font-bold tracking-tight">{formatBRL(wallet.available)}</p>
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                <span>Total {formatBRL(wallet.balance)}</span>
                <span className="hidden h-4 w-px bg-border sm:block" />
                <span>Reservado {formatBRL(wallet.reserved)}</span>
                {lowBalance && (
                    <Badge variant="outline" className="border-amber-500/40 text-amber-800 dark:text-amber-200">
                        Saldo baixo
                    </Badge>
                )}
            </div>
        </div>
    );
}

function StepIndicator({ step }: { step: number }) {
    const steps = [
        { id: 1, label: 'Escolher serviço' },
        { id: 2, label: 'Preencher dados' },
        { id: 3, label: 'Ver resultado' },
    ];

    return (
        <ol className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-0">
            {steps.map((item, index) => (
                <li key={item.id} className="flex items-center gap-2 sm:flex-1">
                    <div className="flex items-center gap-2">
                        <span
                            className={cn(
                                'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors',
                                step >= item.id
                                    ? 'bg-brand-green text-white'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {step > item.id ? <CheckCircle2 className="size-4" /> : item.id}
                        </span>
                        <span
                            className={cn(
                                'text-sm font-medium',
                                step >= item.id ? 'text-foreground' : 'text-muted-foreground',
                            )}
                        >
                            {item.label}
                        </span>
                    </div>
                    {index < steps.length - 1 && (
                        <ChevronRight className="mx-2 hidden size-4 shrink-0 text-muted-foreground/50 sm:block" />
                    )}
                </li>
            ))}
        </ol>
    );
}

function CategoryChip({
    active,
    onClick,
    label,
    count,
    icon: Icon,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
    count: number;
    icon?: LucideIcon;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-medium transition-all',
                active
                    ? 'border-brand-green/40 bg-brand-green text-white shadow-sm'
                    : 'border-border bg-background text-muted-foreground hover:border-brand-green/30 hover:text-foreground',
            )}
        >
            {Icon && <Icon className="size-3.5" />}
            {label}
            <span
                className={cn(
                    'rounded-full px-1.5 py-0.5 text-[10px] tabular-nums',
                    active ? 'bg-white/20 text-white' : 'bg-muted text-muted-foreground',
                )}
            >
                {count}
            </span>
        </button>
    );
}

function ServiceGroupSection({
    group,
    items,
    selectedCode,
    onSelect,
    collapsed,
}: {
    group: string;
    items: QueryTypeRow[];
    selectedCode: string | null;
    onSelect: (code: string) => void;
    collapsed: boolean;
}) {
    const meta = GROUP_META[group] ?? GROUP_META.Outros;
    const Icon = meta.icon;
    const visibleItems = collapsed && selectedCode
        ? items.filter((item) => item.code === selectedCode)
        : items.slice(0, collapsed ? 3 : items.length);

    if (collapsed && selectedCode && visibleItems.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2 px-1">
                <span className={cn('inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium ring-1 ring-inset', meta.color)}>
                    <Icon className="size-3.5" />
                    {meta.short}
                </span>
                <span className="text-xs text-muted-foreground">{items.length} opções</span>
            </div>
            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                {visibleItems.map((item) => (
                    <ServiceCard
                        key={item.code}
                        item={item}
                        selected={selectedCode === item.code}
                        onSelect={() => onSelect(item.code)}
                        compact
                    />
                ))}
            </div>
        </div>
    );
}

function ServiceCard({
    item,
    selected,
    onSelect,
    compact = false,
}: {
    item: QueryTypeRow;
    selected: boolean;
    onSelect: () => void;
    compact?: boolean;
}) {
    const meta = GROUP_META[item.group] ?? GROUP_META.Outros;
    const Icon = meta.icon;

    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'group flex w-full flex-col rounded-xl border text-left transition-all',
                compact ? 'gap-2 p-3' : 'gap-3 p-4',
                selected
                    ? 'border-brand-green/50 bg-brand-green/8 shadow-sm ring-2 ring-brand-green/25'
                    : 'border-border bg-card hover:border-brand-green/30 hover:bg-muted/30',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-start gap-3">
                    <span
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset',
                            selected ? 'bg-brand-green/15 text-brand-green ring-brand-green/20' : meta.color,
                        )}
                    >
                        <Icon className="size-4" />
                    </span>
                    <div className="min-w-0 space-y-1">
                        <p className="line-clamp-2 text-sm leading-snug font-semibold">{item.name}</p>
                        {!compact && item.description && (
                            <p className="line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                {item.description}
                            </p>
                        )}
                    </div>
                </div>
                <ChevronRight
                    className={cn(
                        'size-4 shrink-0 transition-transform',
                        selected ? 'text-brand-green' : 'text-muted-foreground/40 group-hover:translate-x-0.5',
                    )}
                />
            </div>
            <div className="flex items-center justify-between gap-2 pl-12">
                <code className="truncate text-[11px] text-muted-foreground">{item.code}</code>
                <Badge variant={selected ? 'default' : 'secondary'} className="shrink-0 tabular-nums">
                    {formatBRL(item.price_cents)}
                </Badge>
            </div>
        </button>
    );
}

function WelcomePanel() {
    return (
        <Card className="overflow-hidden border-dashed">
            <CardContent className="flex flex-col items-center justify-center gap-5 px-6 py-16 text-center">
                <div className="flex size-16 items-center justify-center rounded-2xl bg-brand-green/10 text-brand-green">
                    <Sparkles className="size-8" />
                </div>
                <div className="max-w-md space-y-2">
                    <h3 className="text-lg font-semibold">Comece escolhendo uma consulta</h3>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        Use os filtros acima para encontrar CPF, CNPJ, veículos e outros serviços.
                        O valor é debitado do seu saldo apenas quando a consulta for concluída.
                    </p>
                </div>
                <div className="grid w-full max-w-sm gap-2 text-left text-sm text-muted-foreground">
                    <div className="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2">
                        <Zap className="size-4 shrink-0 text-brand-green" />
                        Resultado exibido na hora, sem sair do painel
                    </div>
                    <div className="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2">
                        <FileSearch className="size-4 shrink-0 text-brand-green" />
                        Histórico completo disponível em{' '}
                        <Link href="/client/logs" className="font-medium text-foreground underline-offset-4 hover:underline">
                            Meus logs
                        </Link>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function EmptyCatalogState({ onClear }: { onClear: () => void }) {
    return (
        <Card className="border-dashed">
            <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                <Search className="size-8 text-muted-foreground/40" />
                <p className="text-sm text-muted-foreground">Nenhum serviço encontrado com esses filtros.</p>
                <Button variant="outline" size="sm" onClick={onClear}>
                    Limpar filtros
                </Button>
            </CardContent>
        </Card>
    );
}

function ConsultationForm({
    queryType,
    wallet,
    onBack,
}: {
    queryType: QueryTypeRow;
    wallet: WalletInfo | null;
    onBack: () => void;
}) {
    const field = queryType.param_field;
    const form = useForm<Record<string, string>>({
        [field]: '',
    });

    const insufficientBalance = wallet !== null && wallet.available < queryType.price_cents;
    const meta = GROUP_META[queryType.group] ?? GROUP_META.Outros;
    const GroupIcon = meta.icon;

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
        <Card className="overflow-hidden border-brand-green/20 shadow-sm">
            <div className="h-1 bg-gradient-to-r from-brand-green/80 via-brand-green to-brand-green/60" />
            <CardHeader className="space-y-4 border-b border-border/60 pb-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-3">
                        <Button variant="ghost" size="sm" onClick={onBack} className="-ml-2 h-8 gap-1 text-muted-foreground">
                            <ArrowLeft className="size-4" />
                            Voltar ao catálogo
                        </Button>
                        <div className="flex items-start gap-3">
                            <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset', meta.color)}>
                                <GroupIcon className="size-5" />
                            </span>
                            <div className="space-y-1">
                                <CardTitle className="text-xl leading-tight">{queryType.name}</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Código <code className="rounded bg-muted px-1 py-0.5 text-xs">{queryType.code}</code>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-border/60 bg-muted/30 px-4 py-3 text-right">
                        <p className="text-xs text-muted-foreground">Custo da consulta</p>
                        <p className="text-lg font-bold tabular-nums">{formatBRL(queryType.price_cents)}</p>
                    </div>
                </div>
                {queryType.description && (
                    <p className="text-sm leading-relaxed text-muted-foreground">{queryType.description}</p>
                )}
            </CardHeader>
            <CardContent className="space-y-5 pt-6">
                <div className="grid gap-2">
                    <Label htmlFor={`param-${field}`} className="text-sm font-medium">
                        {queryType.param_label}
                    </Label>
                    <Input
                        id={`param-${field}`}
                        value={form.data[field] ?? ''}
                        onChange={(event) => handleChange(event.target.value)}
                        placeholder={queryType.param_placeholder}
                        autoComplete="off"
                        disabled={form.processing}
                        className="h-12 text-base"
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !form.processing && !insufficientBalance && (form.data[field] ?? '').trim()) {
                                event.preventDefault();
                                handleSubmit();
                            }
                        }}
                    />
                    <InputError message={form.errors[field]} />
                    <p className="text-xs text-muted-foreground">
                        Pressione Enter ou clique no botão abaixo para consultar.
                    </p>
                </div>

                {insufficientBalance && (
                    <div className="flex flex-col gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-amber-900 dark:text-amber-100">
                            Saldo insuficiente para esta consulta. Recarregue sua carteira para continuar.
                        </p>
                        <Button asChild variant="outline" size="sm" className="shrink-0 border-amber-500/40">
                            <Link href="/client/billing">Recarregar</Link>
                        </Button>
                    </div>
                )}

                <Button
                    type="button"
                    onClick={handleSubmit}
                    disabled={form.processing || insufficientBalance || !(form.data[field] ?? '').trim()}
                    className="h-11 w-full text-base sm:w-auto sm:min-w-48"
                    size="lg"
                >
                    {form.processing ? (
                        <>
                            <Loader2 className="animate-spin" />
                            Consultando…
                        </>
                    ) : (
                        <>
                            <Search />
                            Realizar consulta
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
    const { display, raw } = splitConsultationResult(result.data);
    const [copied, setCopied] = useState(false);

    async function copyConsultationId() {
        await navigator.clipboard.writeText(result.consultation_id);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    return (
        <Card className="overflow-hidden gap-0 py-0">
            <div className="flex flex-col gap-3 border-b border-border/60 bg-brand-green/5 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-green/15 text-brand-green">
                        <CheckCircle2 className="size-5" />
                    </span>
                    <div>
                        <CardTitle className="text-base">Consulta concluída</CardTitle>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {formatBRL(result.amount_charged)} debitado do saldo
                            {result.from_cache && ' · resposta do cache'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {result.from_cache && <Badge variant="outline">Cache</Badge>}
                    <Badge variant="secondary">{queryType.name}</Badge>
                </div>
            </div>

            <CardContent className="space-y-4 p-0">
                <ConsultationResultView data={display} />

                {raw !== undefined && (
                    <details className="border-t border-border/60 px-6 py-4">
                        <summary className="cursor-pointer text-sm font-medium text-muted-foreground hover:text-foreground">
                            Resposta completa (JSON)
                        </summary>
                        <pre className="mt-3 max-h-80 overflow-auto rounded-xl border border-border/60 bg-muted/40 p-4 text-xs leading-relaxed">
                            {JSON.stringify(raw, null, 2)}
                        </pre>
                    </details>
                )}

                <div className="flex flex-col gap-3 border-t border-border/60 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs text-muted-foreground">
                        ID <code className="rounded bg-muted px-1 py-0.5">{result.consultation_id}</code>
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" onClick={() => void copyConsultationId()}>
                            <ClipboardCopy className="size-3.5" />
                            {copied ? 'Copiado!' : 'Copiar ID'}
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/client/logs">Ver nos logs</Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

ClientConsultationsIndex.layout = {
    breadcrumbs: [{ title: 'Consultas', href: '/client/consultations' }],
};
