import { Head, useForm, usePage } from '@inertiajs/react';
import { ChevronDown, Clock, Search } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageFlash } from '@/hooks/use-page-flash';
import {
    cacheTtlSourceLabel,
    cacheTtlSourceShortLabel,
    daysToSeconds,
    formatCacheTtl,
    resolveCacheTtlMode,
    secondsToDays
    
} from '@/lib/cache-ttl';
import type {CacheTtlSource} from '@/lib/cache-ttl';
import { formatBRL } from '@/lib/format';
import {
    groupByQueryTypeCategory,
    queryTypeShortName,
} from '@/lib/query-type-display';

interface CachePreset {
    label: string;
    seconds: number;
}

interface QueryTypeRow {
    id: string;
    code: string;
    name: string;
    description: string | null;
    default_credit_cost: number;
    status: string;
    cache_ttl_seconds: number | null;
    effective_cache_ttl_seconds: number;
    fallback_cache_ttl_seconds: number;
    cache_ttl_source: CacheTtlSource;
}

interface QueryTypeGroup {
    category: string;
    items: QueryTypeRow[];
}

interface PageProps {
    query_types: QueryTypeRow[];
    default_cache_ttl_seconds: number;
    cache_presets: CachePreset[];
    [key: string]: unknown;
}

type TtlMode = 'disabled' | 'default' | 'preset' | 'custom';

function groupQueryTypes(items: QueryTypeRow[]): QueryTypeGroup[] {
    return groupByQueryTypeCategory(
        items,
        (item) => item.name,
        (item) => item.code,
    );
}

function EditCacheTtlDialog({
    queryType,
    presets,
}: {
    queryType: QueryTypeRow;
    presets: CachePreset[];
}) {
    const [open, setOpen] = useState(false);
    const initialMode = resolveCacheTtlMode(queryType.cache_ttl_seconds, presets);
    const [mode, setMode] = useState<TtlMode>(initialMode);
    const [presetSeconds, setPresetSeconds] = useState<number>(
        initialMode === 'preset' && queryType.cache_ttl_seconds !== null
            ? queryType.cache_ttl_seconds
            : presets[2]?.seconds ?? 86400,
    );
    const [customDays, setCustomDays] = useState(
        initialMode === 'custom' && queryType.cache_ttl_seconds !== null
            ? secondsToDays(queryType.cache_ttl_seconds)
            : '14',
    );

    const form = useForm<{ cache_ttl_seconds: number | null }>({
        cache_ttl_seconds: queryType.cache_ttl_seconds,
    });

    const effectivePreview = useMemo(() => {
        switch (mode) {
            case 'disabled':
                return 0;
            case 'default':
                return queryType.fallback_cache_ttl_seconds;
            case 'preset':
                return presetSeconds;
            case 'custom':
                return daysToSeconds(customDays);
        }
    }, [customDays, mode, presetSeconds, queryType.fallback_cache_ttl_seconds]);

    function resetFormState(): void {
        const nextMode = resolveCacheTtlMode(queryType.cache_ttl_seconds, presets);
        setMode(nextMode);
        setPresetSeconds(
            nextMode === 'preset' && queryType.cache_ttl_seconds !== null
                ? queryType.cache_ttl_seconds
                : presets[2]?.seconds ?? 86400,
        );
        setCustomDays(
            nextMode === 'custom' && queryType.cache_ttl_seconds !== null
                ? secondsToDays(queryType.cache_ttl_seconds)
                : '14',
        );
        form.setData('cache_ttl_seconds', queryType.cache_ttl_seconds);
        form.clearErrors();
    }

    function handleOpenChange(nextOpen: boolean): void {
        setOpen(nextOpen);

        if (nextOpen) {
            resetFormState();
        }
    }

    function handleSubmit(event: FormEvent): void {
        event.preventDefault();

        let cacheTtlSeconds: number | null = null;

        switch (mode) {
            case 'disabled':
                cacheTtlSeconds = 0;
                break;
            case 'default':
                cacheTtlSeconds = null;
                break;
            case 'preset':
                cacheTtlSeconds = presetSeconds;
                break;
            case 'custom':
                cacheTtlSeconds = daysToSeconds(customDays);
                break;
        }

        form.setData('cache_ttl_seconds', cacheTtlSeconds);
        form.put(`/admin/query-types/${queryType.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <Button
                variant="ghost"
                size="icon"
                className="size-8 shrink-0"
                title="Editar cache"
                onClick={() => setOpen(true)}
            >
                <Clock className="size-4" />
            </Button>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Cache — {queryType.name}</DialogTitle>
                    <DialogDescription>
                        {queryType.description ?? 'Define por quanto tempo consultas idênticas podem retornar a mesma resposta.'}
                        {' '}Alterar o TTL invalida entradas antigas automaticamente.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm">
                        <p className="font-mono text-xs text-muted-foreground">{queryType.code}</p>
                        <p className="mt-2 text-muted-foreground">TTL efetivo atual</p>
                        <p className="font-medium">{formatCacheTtl(queryType.effective_cache_ttl_seconds)}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {cacheTtlSourceLabel(queryType.cache_ttl_source)}
                        </p>
                    </div>

                    <Field label="Política de cache">
                        <div className="space-y-2">
                            <label className="flex items-start gap-3 rounded-lg border border-border px-3 py-2">
                                <input
                                    type="radio"
                                    name={`cache-mode-${queryType.id}`}
                                    checked={mode === 'disabled'}
                                    onChange={() => setMode('disabled')}
                                    className="mt-1"
                                />
                                <span>
                                    <span className="block font-medium">Desabilitado</span>
                                    <span className="text-sm text-muted-foreground">
                                        Nunca reutiliza respostas em cache para este tipo.
                                    </span>
                                </span>
                            </label>

                            <label className="flex items-start gap-3 rounded-lg border border-border px-3 py-2">
                                <input
                                    type="radio"
                                    name={`cache-mode-${queryType.id}`}
                                    checked={mode === 'default'}
                                    onChange={() => setMode('default')}
                                    className="mt-1"
                                />
                                <span>
                                    <span className="block font-medium">Padrão do sistema</span>
                                    <span className="text-sm text-muted-foreground">
                                        Usa configuração por tipo ou padrão global (
                                        {formatCacheTtl(queryType.fallback_cache_ttl_seconds)}).
                                    </span>
                                </span>
                            </label>

                            <label className="flex items-start gap-3 rounded-lg border border-border px-3 py-2">
                                <input
                                    type="radio"
                                    name={`cache-mode-${queryType.id}`}
                                    checked={mode === 'preset'}
                                    onChange={() => setMode('preset')}
                                    className="mt-1"
                                />
                                <span className="flex-1">
                                    <span className="block font-medium">Intervalo predefinido</span>
                                    <select
                                        value={presetSeconds}
                                        onChange={(event) => {
                                            setMode('preset');
                                            setPresetSeconds(Number(event.target.value));
                                        }}
                                        className="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        {presets.filter((preset) => preset.seconds > 0).map((preset) => (
                                            <option key={preset.seconds} value={preset.seconds}>
                                                {preset.label}
                                            </option>
                                        ))}
                                    </select>
                                </span>
                            </label>

                            <label className="flex items-start gap-3 rounded-lg border border-border px-3 py-2">
                                <input
                                    type="radio"
                                    name={`cache-mode-${queryType.id}`}
                                    checked={mode === 'custom'}
                                    onChange={() => setMode('custom')}
                                    className="mt-1"
                                />
                                <span className="flex-1">
                                    <span className="block font-medium">Personalizado</span>
                                    <div className="mt-2 flex items-center gap-2">
                                        <Input
                                            type="number"
                                            min={1}
                                            value={customDays}
                                            onChange={(event) => {
                                                setMode('custom');
                                                setCustomDays(event.target.value);
                                            }}
                                            className="w-24"
                                        />
                                        <span className="text-sm text-muted-foreground">dias</span>
                                    </div>
                                </span>
                            </label>
                        </div>
                    </Field>

                    <div className="rounded-lg border border-dashed border-border px-4 py-3 text-sm">
                        <p className="text-muted-foreground">Após salvar</p>
                        <p className="font-medium">{formatCacheTtl(effectivePreview)}</p>
                    </div>

                    {form.errors.cache_ttl_seconds && (
                        <p className="text-sm text-destructive">{form.errors.cache_ttl_seconds}</p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Salvar TTL
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function QueryTypeGroupCard({
    group,
    presets,
    defaultOpen,
}: {
    group: QueryTypeGroup;
    presets: CachePreset[];
    defaultOpen: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <Card className="gap-0 py-0">
            <Collapsible open={open} onOpenChange={setOpen}>
                <CardHeader className="flex flex-row items-center justify-between border-b border-border py-3">
                    <div className="flex items-center gap-3">
                        <CollapsibleTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0"
                                title={open ? 'Recolher' : 'Expandir'}
                            >
                                <ChevronDown
                                    className={`size-4 transition-transform ${open ? 'rotate-180' : ''}`}
                                />
                            </Button>
                        </CollapsibleTrigger>
                        <div>
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">{group.category}</CardTitle>
                                <Badge variant="secondary" className="tabular-nums">
                                    {group.items.length} {group.items.length === 1 ? 'tipo' : 'tipos'}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr className="border-b border-border">
                                        <th className="px-4 py-2 font-medium md:px-6">Consulta</th>
                                        <th className="px-4 py-2 font-medium md:px-6">Código</th>
                                        <th className="px-4 py-2 text-right font-medium md:px-6">Preço</th>
                                        <th className="px-4 py-2 font-medium md:px-6">Cache</th>
                                        <th className="px-4 py-2 md:px-6"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {group.items.map((queryType, index) => (
                                        <tr
                                            key={queryType.id}
                                            className={`border-b border-border last:border-0 hover:bg-muted/40 ${
                                                index % 2 === 1 ? 'bg-muted/15' : ''
                                            }`}
                                        >
                                            <td className="max-w-[16rem] px-4 py-2.5 md:max-w-xs md:px-6">
                                                <p className="truncate font-medium">
                                                    {queryTypeShortName(queryType.name, group.category)}
                                                </p>
                                            </td>
                                            <td className="px-4 py-2.5 md:px-6">
                                                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
                                                    {queryType.code}
                                                </code>
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-medium tabular-nums md:px-6">
                                                {formatBRL(queryType.default_credit_cost)}
                                            </td>
                                            <td className="px-4 py-2.5 md:px-6">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">
                                                        {formatCacheTtl(queryType.effective_cache_ttl_seconds)}
                                                    </span>
                                                    <Badge variant="outline" className="text-[10px] font-normal">
                                                        {cacheTtlSourceShortLabel(queryType.cache_ttl_source)}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="px-4 py-2.5 text-right md:px-6">
                                                <EditCacheTtlDialog queryType={queryType} presets={presets} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}

export default function AdminQueryTypesIndex() {
    usePageFlash();
    const { query_types, cache_presets } = usePage<PageProps>().props;
    const [search, setSearch] = useState('');

    const filteredTypes = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            return query_types;
        }

        return query_types.filter((queryType) => {
            const category = queryType.name.includes(' — ')
                ? queryType.name.slice(0, queryType.name.indexOf(' — ')).toLowerCase()
                : queryType.code.split('_')[0].toLowerCase();

            return (
                queryType.name.toLowerCase().includes(term)
                || queryType.code.toLowerCase().includes(term)
                || category.includes(term)
                || (queryType.description?.toLowerCase().includes(term) ?? false)
            );
        });
    }, [query_types, search]);

    const groups = useMemo(() => groupQueryTypes(filteredTypes), [filteredTypes]);
    const hasResults = groups.length > 0;

    return (
        <>
            <Head title="Tipos de consulta" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tipos de consulta"
                    description="Catálogo de consultas e TTL de cache por tipo. Consultas idênticas podem retornar resposta em cache enquanto o TTL estiver válido — o saldo continua sendo cobrado."
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:max-w-sm">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar por nome, código ou categoria..."
                            className="pl-9"
                        />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {filteredTypes.length} de {query_types.length} tipos
                        {groups.length > 0 && ` · ${groups.length} categorias`}
                    </p>
                </div>

                {!hasResults ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Search className="size-8 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">
                                Nenhum tipo encontrado para &quot;{search.trim()}&quot;.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {groups.map((group, index) => (
                            <QueryTypeGroupCard
                                key={group.category}
                                group={group}
                                presets={cache_presets}
                                defaultOpen={search.trim().length > 0 || index < 3}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

AdminQueryTypesIndex.layout = {
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Tipos de consulta', href: '/admin/query-types' },
    ],
};
