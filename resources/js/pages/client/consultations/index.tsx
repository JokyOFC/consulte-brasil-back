import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Calendar,
    Car,
    CheckCircle2,
    ClipboardCopy,
    Coins,
    FileDown,
    FileSearch,
    Gauge,
    Layers,
    Loader2,
    MapPin,
    Search,
    User,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ConsultationResultView } from '@/components/consultation-result-view';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { usePageFlash } from '@/hooks/use-page-flash';
import { splitConsultationResult } from '@/lib/consultation-result';
import { downloadBase64Pdf, findPdfAttachments, sanitizeResultForDisplay } from '@/lib/consultation-attachments';
import { exportConsultationPdf } from '@/lib/export-consultation-pdf';
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

const GROUP_META: Record<string, { icon: LucideIcon; description: string; accent: string }> = {
    'Pessoa Física (CPF)': {
        icon: User,
        description: 'Dados cadastrais, score de crédito, restrições, protestos e mais informações sobre pessoas físicas.',
        accent: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
    },
    'Pessoa Jurídica (CNPJ)': {
        icon: Building2,
        description: 'Consultas cadastrais, quadro societário, crédito e regularidade de empresas.',
        accent: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
    },
    Veículos: {
        icon: Car,
        description: 'Consultas por placa, débitos, leilão, gravame, FIPE e histórico veicular.',
        accent: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    },
    'Tabela FIPE': {
        icon: Gauge,
        description: 'Tabela de referência, marcas, modelos e valores FIPE.',
        accent: 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
    },
    'Localização e utilidades': {
        icon: MapPin,
        description: 'CEP, DDD, geolocalização, distância entre CEPs e rastreio de encomendas.',
        accent: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
    },
    Feriados: {
        icon: Calendar,
        description: 'Feriados nacionais, estaduais e municipais.',
        accent: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    Outros: {
        icon: Layers,
        description: 'Demais serviços disponíveis na plataforma.',
        accent: 'bg-muted text-muted-foreground',
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
    const [sheetOpen, setSheetOpen] = useState(false);
    const resultRef = useRef<HTMLDivElement>(null);

    const selected = useMemo(
        () => query_types.find((item) => item.code === selectedCode) ?? null,
        [query_types, selectedCode],
    );

    const resultQueryType = useMemo(
        () => (result ? query_types.find((item) => item.code === result.query_type) ?? null : null),
        [query_types, result],
    );

    useEffect(() => {
        if (selected_query_type) {
            setSelectedCode(selected_query_type);
            const match = query_types.find((item) => item.code === selected_query_type);
            if (match) {
                setActiveGroup(match.group);
            }
            if (!result || result.query_type !== selected_query_type) {
                setSheetOpen(true);
            }
        }
    }, [selected_query_type, query_types, result]);

    useEffect(() => {
        if (result && resultRef.current) {
            resultRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, [result?.consultation_id]);

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

    function openConsultation(code: string) {
        setSelectedCode(code);
        setSheetOpen(true);
    }

    function closeSheet() {
        setSheetOpen(false);
    }

    const visibleGroups =
        activeGroup === 'all'
            ? GROUP_ORDER.filter((group) => groupedFiltered.has(group))
            : GROUP_ORDER.filter((group) => group === activeGroup && groupedFiltered.has(group));

    return (
        <>
            <Head title="Consultas" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader
                    title="Consultas"
                    description="Escolha o serviço desejado e execute consultas em tempo real pelo painel."
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

                {result && resultQueryType && (
                    <div ref={resultRef}>
                        <ConsultationResultPanel result={result} queryType={resultQueryType} />
                    </div>
                )}

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
                            {filteredTypes.length} consulta{filteredTypes.length === 1 ? '' : 's'}
                        </p>
                    </div>

                    <div className="flex gap-2 overflow-x-auto pb-1">
                        <FilterChip active={activeGroup === 'all'} onClick={() => setActiveGroup('all')} label="Todos" />
                        {availableGroups.map((group) => (
                            <FilterChip
                                key={group}
                                active={activeGroup === group}
                                onClick={() => setActiveGroup(group)}
                                label={group.replace(/\s*\([^)]*\)/, '')}
                            />
                        ))}
                    </div>
                </div>

                {filteredTypes.length === 0 ? (
                    <EmptyCatalogState onClear={() => { setSearch(''); setActiveGroup('all'); }} />
                ) : (
                    <div className="space-y-10">
                        {visibleGroups.map((group) => {
                            const items = groupedFiltered.get(group) ?? [];
                            const meta = GROUP_META[group] ?? GROUP_META.Outros;
                            const Icon = meta.icon;

                            return (
                                <section key={group} className="space-y-5">
                                    <div className="flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="flex items-start gap-4">
                                            <span className={cn('flex size-12 shrink-0 items-center justify-center rounded-2xl', meta.accent)}>
                                                <Icon className="size-6" />
                                            </span>
                                            <div className="space-y-1.5">
                                                <h2 className="text-xl font-semibold tracking-tight">{group}</h2>
                                                <p className="max-w-3xl text-sm leading-relaxed text-muted-foreground">
                                                    {meta.description}
                                                </p>
                                            </div>
                                        </div>
                                        <Badge variant="outline" className="w-fit shrink-0 px-3 py-1 text-xs font-normal">
                                            {items.length} consulta{items.length === 1 ? '' : 's'}
                                        </Badge>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        {items.map((item) => (
                                            <CatalogCard
                                                key={item.code}
                                                item={item}
                                                onConsult={() => openConsultation(item.code)}
                                            />
                                        ))}
                                    </div>
                                </section>
                            );
                        })}
                    </div>
                )}
            </div>

            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                    {selected && (
                        <ConsultationSheetForm
                            queryType={selected}
                            wallet={wallet}
                            onClose={closeSheet}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

function WalletBanner({ wallet }: { wallet: WalletInfo }) {
    const lowBalance = wallet.available < 500;

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-2xl border px-5 py-4 sm:flex-row sm:items-center sm:justify-between',
                lowBalance ? 'border-amber-500/30 bg-amber-500/5' : 'border-border bg-card',
            )}
        >
            <div className="flex items-center gap-3">
                <span className="flex size-10 items-center justify-center rounded-xl bg-brand-green/10 text-brand-green">
                    <Coins className="size-5" />
                </span>
                <div>
                    <p className="text-xs text-muted-foreground">Saldo disponível</p>
                    <p className="text-xl font-bold tabular-nums">{formatBRL(wallet.available)}</p>
                </div>
            </div>
            <p className="text-sm text-muted-foreground">
                Total {formatBRL(wallet.balance)} · Reservado {formatBRL(wallet.reserved)}
            </p>
        </div>
    );
}

function FilterChip({
    active,
    onClick,
    label,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'shrink-0 rounded-full border px-4 py-2 text-sm font-medium transition-colors',
                active
                    ? 'border-brand-green bg-brand-green text-white'
                    : 'border-border bg-background text-muted-foreground hover:text-foreground',
            )}
        >
            {label}
        </button>
    );
}

function CatalogCard({
    item,
    onConsult,
}: {
    item: QueryTypeRow;
    onConsult: () => void;
}) {
    return (
        <article className="flex h-full flex-col rounded-2xl border border-border/80 bg-card p-5 shadow-sm transition-shadow hover:shadow-md">
            <div className="flex-1 space-y-3">
                <h3 className="text-base leading-snug font-semibold">{item.name}</h3>
                {item.description && (
                    <p className="line-clamp-3 text-sm leading-relaxed text-muted-foreground">
                        {item.description}
                    </p>
                )}
            </div>

            <div className="mt-6 space-y-4">
                <div>
                    <p className="text-xs text-muted-foreground">A partir de</p>
                    <p className="mt-0.5 flex items-baseline gap-1">
                        <span className="text-2xl font-bold text-brand-green tabular-nums">
                            {formatBRL(item.price_cents)}
                        </span>
                        <span className="text-sm text-muted-foreground">/ consulta</span>
                    </p>
                </div>

                <Button variant="outline" className="w-full justify-between" onClick={onConsult}>
                    Consultar agora
                    <ArrowRight className="size-4" />
                </Button>
            </div>
        </article>
    );
}

function EmptyCatalogState({ onClear }: { onClear: () => void }) {
    return (
        <Card className="border-dashed">
            <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                <FileSearch className="size-10 text-muted-foreground/40" />
                <p className="text-sm text-muted-foreground">Nenhuma consulta encontrada com esses filtros.</p>
                <Button variant="outline" size="sm" onClick={onClear}>
                    Limpar filtros
                </Button>
            </CardContent>
        </Card>
    );
}

function ConsultationSheetForm({
    queryType,
    wallet,
    onClose,
}: {
    queryType: QueryTypeRow;
    wallet: WalletInfo | null;
    onClose: () => void;
}) {
    const field = queryType.param_field;
    const form = useForm<Record<string, string>>({ [field]: '' });
    const insufficientBalance = wallet !== null && wallet.available < queryType.price_cents;

    function handleSubmit() {
        form.post(`/client/consultations/${queryType.code}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
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
        <>
            <SheetHeader className="border-b border-border/60 pb-4 text-left">
                <Button variant="ghost" size="sm" onClick={onClose} className="-ml-2 mb-2 h-8 w-fit gap-1 text-muted-foreground">
                    <ArrowLeft className="size-4" />
                    Voltar
                </Button>
                <SheetTitle className="text-xl leading-tight">{queryType.name}</SheetTitle>
                <SheetDescription>
                    {queryType.description ?? 'Informe o dado solicitado para executar a consulta.'}
                </SheetDescription>
            </SheetHeader>

            <div className="mt-6 space-y-6">
                <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                    <p className="text-xs text-muted-foreground">Valor da consulta</p>
                    <p className="text-lg font-bold tabular-nums text-brand-green">{formatBRL(queryType.price_cents)}</p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`param-${field}`}>{queryType.param_label}</Label>
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
                </div>

                {insufficientBalance && (
                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                        Saldo insuficiente.{' '}
                        <Link href="/client/billing" className="font-medium underline">
                            Recarregar carteira
                        </Link>
                    </div>
                )}

                <Button
                    type="button"
                    onClick={handleSubmit}
                    disabled={form.processing || insufficientBalance || !(form.data[field] ?? '').trim()}
                    className="h-11 w-full"
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
            </div>
        </>
    );
}

function ConsultationResultPanel({
    result,
    queryType,
}: {
    result: ConsultationResult;
    queryType: QueryTypeRow;
}) {
    const { display } = splitConsultationResult(result.data);
    const pdfAttachments = useMemo(() => findPdfAttachments(result.data), [result.data]);
    const [copied, setCopied] = useState(false);
    const [exporting, setExporting] = useState(false);

    async function copyConsultationId() {
        await navigator.clipboard.writeText(result.consultation_id);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    function handleDownloadCertificate() {
        const attachment = pdfAttachments[0];

        if (!attachment) {
            return;
        }

        downloadBase64Pdf(
            attachment.base64,
            `consulta-${queryType.code}-${result.consultation_id.slice(0, 8)}.pdf`,
        );
    }

    async function handleExportSummaryPdf() {
        setExporting(true);
        try {
            await exportConsultationPdf({
                consultationId: result.consultation_id,
                queryTypeName: queryType.name,
                queryTypeCode: queryType.code,
                amountCharged: result.amount_charged,
                fromCache: result.from_cache,
                data: result.data,
            });
        } finally {
            setExporting(false);
        }
    }

    return (
        <Card className="overflow-hidden gap-0 border-brand-green/25 py-0 shadow-md">
            <div className="flex flex-col gap-4 border-b border-border/60 bg-brand-green/5 px-5 py-5 sm:flex-row sm:items-center sm:justify-between md:px-6">
                <div className="flex items-start gap-3">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-green/15 text-brand-green">
                        <CheckCircle2 className="size-5" />
                    </span>
                    <div>
                        <h3 className="text-lg font-semibold">Consulta concluída</h3>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {queryType.name} · {formatBRL(result.amount_charged)} debitado
                            {result.from_cache && ' · cache'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {pdfAttachments.length > 0 && (
                        <Button variant="default" size="sm" onClick={handleDownloadCertificate}>
                            <FileDown className="size-4" />
                            Baixar certificado PDF
                        </Button>
                    )}
                    <Button
                        variant={pdfAttachments.length > 0 ? 'outline' : 'default'}
                        size="sm"
                        disabled={exporting}
                        onClick={() => void handleExportSummaryPdf()}
                    >
                        {exporting ? <Loader2 className="animate-spin" /> : <FileDown className="size-4" />}
                        {exporting ? 'Gerando PDF…' : pdfAttachments.length > 0 ? 'Exportar resumo' : 'Exportar PDF'}
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => void copyConsultationId()}>
                        <ClipboardCopy className="size-4" />
                        {copied ? 'Copiado!' : 'Copiar ID'}
                    </Button>
                </div>
            </div>

            <CardContent className="space-y-4 p-0">
                <ConsultationResultView data={display} />

                <details className="border-t border-border/60 px-5 py-4 md:px-6">
                    <summary className="cursor-pointer text-sm font-medium text-muted-foreground hover:text-foreground">
                        Resposta completa (JSON)
                    </summary>
                    <pre className="mt-3 max-h-80 overflow-auto rounded-xl border border-border/60 bg-muted/40 p-4 text-xs leading-relaxed">
                        {JSON.stringify(sanitizeResultForDisplay(display), null, 2)}
                    </pre>
                </details>

                <p className="border-t border-border/60 px-5 py-3 text-xs text-muted-foreground md:px-6">
                    ID <code className="rounded bg-muted px-1 py-0.5">{result.consultation_id}</code>
                </p>
            </CardContent>
        </Card>
    );
}

ClientConsultationsIndex.layout = {
    breadcrumbs: [{ title: 'Consultas', href: '/client/consultations' }],
};
