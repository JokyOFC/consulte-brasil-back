import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    FlaskConical,
    KeyRound,
    LineChart,
    Pencil,
    Plus,
    Power,
    Search,
    Server,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { ProviderBalanceDisplay  } from '@/components/provider-balance-display';
import type {ProviderBalance} from '@/components/provider-balance-display';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
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
import {
    groupByQueryTypeCategory,
    queryTypeDisplayName,
    queryTypeShortName,
} from '@/lib/query-type-display';

const MARKUP = 1.1;

interface Capability {
    query_type: string;
    query_type_name: string | null;
    priority: number;
    price_cents: number;
    cost_cents: number | null;
    enabled: boolean;
    endpoint: string | null;
    body_key: string | null;
    has_device_token: boolean;
}

interface ProviderRow {
    id: string;
    identifier: string;
    name: string;
    status: string;
    environment: string;
    base_url: string | null;
    has_token: boolean;
    has_sandbox_token: boolean;
    capabilities: Capability[];
    balance: ProviderBalance;
}

interface PageProps {
    providers: ProviderRow[];
    [key: string]: unknown;
}

function capabilityMatches(capability: Capability, term: string): boolean {
    const displayName = queryTypeDisplayName(capability.query_type_name, capability.query_type).toLowerCase();

    return (
        capability.query_type.toLowerCase().includes(term)
        || displayName.includes(term)
        || (capability.endpoint?.toLowerCase().includes(term) ?? false)
    );
}

function providerMatches(provider: ProviderRow, term: string): boolean {
    return (
        provider.name.toLowerCase().includes(term)
        || provider.identifier.toLowerCase().includes(term)
        || (provider.base_url?.toLowerCase().includes(term) ?? false)
        || provider.capabilities.some((capability) => capabilityMatches(capability, term))
    );
}

function ProviderStatusBadge({ status }: { status: string }) {
    return (
        <Badge
            variant="outline"
            className={status === 'enabled'
                ? 'border-transparent bg-green-100 text-green-700'
                : 'border-transparent bg-muted text-muted-foreground'}
        >
            {status === 'enabled' ? 'Habilitado' : 'Desabilitado'}
        </Badge>
    );
}

function ProviderEnvironmentBadge({ environment }: { environment: string }) {
    return (
        <Badge
            variant="outline"
            className={environment === 'production'
                ? 'border-transparent bg-blue-100 text-blue-700'
                : 'border-transparent bg-amber-100 text-amber-700'}
        >
            {environment === 'production' ? 'Produção' : 'Sandbox'}
        </Badge>
    );
}

function ProviderActions({ provider }: { provider: ProviderRow }) {
    return (
        <div className="flex flex-wrap items-center justify-end gap-1.5">
            <Button variant="outline" size="sm" asChild title="Ver dashboard detalhado">
                <Link href={`/admin/providers/${provider.id}`}>
                    <LineChart className="size-4" />
                    Detalhes
                </Link>
            </Button>
            <Button
                variant="outline"
                size="icon"
                className="size-8"
                title={provider.environment === 'production'
                    ? 'Alternar para sandbox'
                    : 'Alternar para produção'}
                onClick={() => router.post(
                    `/admin/providers/${provider.id}/environment`,
                    { environment: provider.environment === 'production' ? 'sandbox' : 'production' },
                    { preserveScroll: true },
                )}
            >
                <FlaskConical className="size-4" />
            </Button>
            <EditProviderDialog
                provider={provider}
                trigger={(
                    <Button variant="outline" size="icon" className="size-8" title="Editar provedor">
                        <Pencil className="size-4" />
                    </Button>
                )}
            />
            <CapabilityDialog
                providerId={provider.id}
                trigger={(
                    <Button variant="outline" size="icon" className="size-8" title="Adicionar capacidade">
                        <Plus className="size-4" />
                    </Button>
                )}
            />
            <Button
                variant={provider.status === 'enabled' ? 'outline' : 'default'}
                size="icon"
                className="size-8"
                title={provider.status === 'enabled' ? 'Desabilitar provedor' : 'Habilitar provedor'}
                onClick={() => router.post(`/admin/providers/${provider.id}/toggle`, {}, { preserveScroll: true })}
            >
                <Power className="size-4" />
            </Button>
        </div>
    );
}

function CapabilityTable({
    capabilities,
    providerId,
}: {
    capabilities: Capability[];
    providerId: string;
}) {
    const groups = useMemo(
        () => groupByQueryTypeCategory(
            capabilities,
            (capability) => queryTypeDisplayName(capability.query_type_name, capability.query_type),
            (capability) => capability.query_type,
        ),
        [capabilities],
    );

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="bg-muted/30 text-left text-xs text-muted-foreground">
                    <tr className="border-b border-border">
                        <th className="px-4 py-2 font-medium md:px-6">Consulta</th>
                        <th className="px-4 py-2 font-medium md:px-6">Código</th>
                        <th className="px-4 py-2 text-right font-medium md:px-6">Prioridade</th>
                        <th className="px-4 py-2 text-right font-medium md:px-6">Custo</th>
                        <th className="px-4 py-2 text-right font-medium md:px-6">Venda</th>
                        <th className="px-4 py-2 font-medium md:px-6">Endpoint</th>
                        <th className="px-4 py-2 font-medium md:px-6">Situação</th>
                        <th className="px-4 py-2 md:px-6"></th>
                    </tr>
                </thead>
                <tbody>
                    {groups.flatMap((group) => [
                        <tr key={`${group.category}-header`} className="bg-muted/30">
                            <td
                                colSpan={8}
                                className="px-4 py-2 text-xs font-medium tracking-wide text-muted-foreground uppercase md:px-6"
                            >
                                {group.category}
                                <span className="ml-2 font-normal normal-case tabular-nums">
                                    · {group.items.length} {group.items.length === 1 ? 'tipo' : 'tipos'}
                                </span>
                            </td>
                        </tr>,
                        ...group.items.map((capability, index) => {
                            const displayName = queryTypeDisplayName(
                                capability.query_type_name,
                                capability.query_type,
                            );

                            return (
                                <tr
                                    key={capability.query_type}
                                    className={`border-b border-border last:border-0 hover:bg-muted/40 ${
                                        index % 2 === 1 ? 'bg-muted/15' : ''
                                    }`}
                                >
                                    <td className="max-w-[14rem] px-4 py-2.5 md:max-w-xs md:px-6">
                                        <p className="truncate font-medium">
                                            {queryTypeShortName(displayName, group.category)}
                                        </p>
                                    </td>
                                    <td className="px-4 py-2.5 md:px-6">
                                        <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
                                            {capability.query_type}
                                        </code>
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums text-muted-foreground md:px-6">
                                        {capability.priority}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums text-muted-foreground md:px-6">
                                        {capability.cost_cents !== null ? formatBRL(capability.cost_cents) : '—'}
                                    </td>
                                    <td className="px-4 py-2.5 text-right font-medium tabular-nums md:px-6">
                                        {formatBRL(capability.price_cents)}
                                    </td>
                                    <td className="max-w-[10rem] px-4 py-2.5 md:px-6">
                                        {capability.endpoint ? (
                                            <div className="flex items-center gap-1.5">
                                                <code className="truncate rounded bg-muted px-1.5 py-0.5 font-mono text-[11px]">
                                                    {capability.endpoint}
                                                </code>
                                                {capability.has_device_token && (
                                                    <Badge variant="outline" className="shrink-0 px-1.5 text-[10px]">
                                                        device
                                                    </Badge>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">padrão</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5 md:px-6">
                                        <Badge
                                            variant="outline"
                                            className={capability.enabled
                                                ? 'border-transparent bg-green-100 text-green-700'
                                                : 'border-transparent bg-muted text-muted-foreground'}
                                        >
                                            {capability.enabled ? 'Ativa' : 'Inativa'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-2.5 text-right md:px-6">
                                        <CapabilityDialog
                                            providerId={providerId}
                                            capability={capability}
                                            trigger={(
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    title="Editar capacidade"
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                        />
                                    </td>
                                </tr>
                            );
                        }),
                    ])}
                </tbody>
            </table>
        </div>
    );
}

function ProviderCard({
    provider,
    defaultOpen,
}: {
    provider: ProviderRow;
    defaultOpen: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const enabledCount = provider.capabilities.filter((capability) => capability.enabled).length;

    return (
        <Card className="gap-0 py-0">
            <Collapsible open={open} onOpenChange={setOpen}>
                <CardHeader className="border-b border-border py-3">
                    <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                        <div className="flex min-w-0 items-start gap-3">
                            <CollapsibleTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mt-0.5 size-8 shrink-0"
                                    title={open ? 'Recolher capacidades' : 'Expandir capacidades'}
                                >
                                    <ChevronDown
                                        className={`size-4 transition-transform ${open ? 'rotate-180' : ''}`}
                                    />
                                </Button>
                            </CollapsibleTrigger>
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                <Server className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="font-semibold">{provider.name}</h3>
                                    <ProviderStatusBadge status={provider.status} />
                                    <ProviderEnvironmentBadge environment={provider.environment} />
                                    <Badge variant="secondary" className="tabular-nums">
                                        {provider.capabilities.length} tipos
                                        {enabledCount !== provider.capabilities.length ? ` · ${enabledCount} ativos` : ''}
                                    </Badge>
                                </div>
                                <p className="mt-1 truncate font-mono text-xs text-muted-foreground">
                                    {provider.identifier}
                                    {provider.base_url ? ` · ${provider.base_url}` : ''}
                                </p>
                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" className="gap-1 text-[11px] font-normal">
                                        <KeyRound className="size-3" />
                                        Prod {provider.has_token ? 'ok' : '—'}
                                    </Badge>
                                    <Badge variant="outline" className="gap-1 text-[11px] font-normal">
                                        <KeyRound className="size-3" />
                                        Sandbox {provider.has_sandbox_token ? 'ok' : '—'}
                                    </Badge>
                                    <ProviderBalanceDisplay
                                        balance={provider.balance}
                                        providerId={provider.identifier === 'mercado_pago' ? undefined : provider.id}
                                        compact
                                    />
                                </div>
                            </div>
                        </div>
                        <ProviderActions provider={provider} />
                    </div>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent className="p-0">
                        {provider.capabilities.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
                                <p className="text-sm text-muted-foreground">
                                    Sem capacidades. Adicione um tipo de consulta para este provedor.
                                </p>
                                <CapabilityDialog providerId={provider.id} />
                            </div>
                        ) : (
                            <CapabilityTable
                                capabilities={provider.capabilities}
                                providerId={provider.id}
                            />
                        )}
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}

export default function AdminProvidersIndex() {
    usePageFlash();
    const { providers } = usePage<PageProps>().props;
    const [search, setSearch] = useState('');

    const filteredProviders = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            return providers;
        }

        return providers
            .filter((provider) => providerMatches(provider, term))
            .map((provider) => {
                const providerHit = (
                    provider.name.toLowerCase().includes(term)
                    || provider.identifier.toLowerCase().includes(term)
                    || (provider.base_url?.toLowerCase().includes(term) ?? false)
                );

                return {
                    ...provider,
                    capabilities: providerHit
                        ? provider.capabilities
                        : provider.capabilities.filter((capability) => capabilityMatches(capability, term)),
                };
            });
    }, [providers, search]);

    const totals = useMemo(() => {
        const capabilities = filteredProviders.flatMap((provider) => provider.capabilities);

        return {
            providers: filteredProviders.length,
            capabilities: capabilities.length,
            active: capabilities.filter((capability) => capability.enabled).length,
        };
    }, [filteredProviders]);

    return (
        <>
            <Head title="Provedores" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Provedores"
                    description="Prioridade, custo e disponibilidade por tipo de consulta. Provedor desabilitado é ignorado pelo roteador."
                    actions={<CreateProviderDialog />}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:max-w-sm">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar provedor ou tipo de consulta..."
                            className="pl-9"
                        />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {totals.providers} de {providers.length} provedores
                        {' · '}
                        {totals.capabilities} capacidades
                        {' · '}
                        {totals.active} ativas
                    </p>
                </div>

                {filteredProviders.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Server className="size-8 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">
                                {providers.length === 0
                                    ? 'Nenhum provedor cadastrado ainda.'
                                    : `Nenhum resultado para "${search.trim()}".`}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {filteredProviders.map((provider, index) => (
                            <ProviderCard
                                key={provider.id}
                                provider={provider}
                                defaultOpen={search.trim().length > 0 || index === 0}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function EnvironmentSelect({ value, onChange }: { value: string; onChange: (value: string) => void }) {
    const options = [
        { id: 'production', label: 'Produção' },
        { id: 'sandbox', label: 'Sandbox' },
    ];

    return (
        <div className="inline-flex rounded-lg border border-border p-0.5">
            {options.map((opt) => (
                <button
                    key={opt.id}
                    type="button"
                    onClick={() => onChange(opt.id)}
                    className={`rounded-md px-3 py-1.5 text-sm transition-colors ${
                        value === opt.id
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    }`}
                >
                    {opt.label}
                </button>
            ))}
        </div>
    );
}

function providerFormDefaults(provider?: ProviderRow) {
    return {
        identifier: provider?.identifier ?? '',
        name: provider?.name ?? '',
        base_url: provider?.base_url ?? '',
        environment: provider?.environment ?? 'production',
        token: '',
        sandbox_token: '',
    };
}

function CreateProviderDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm(providerFormDefaults());

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/providers', {
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
                <Button><Plus /> Cadastrar provedor</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cadastrar provedor</DialogTitle>
                    <DialogDescription>
                        O identificador deve casar com o adapter registrado no código (ex.: <code>api_brasil</code>).
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Identificador</Label>
                        <Input value={form.data.identifier} onChange={(e) => form.setData('identifier', e.target.value)} placeholder="api_brasil" required />
                        {form.errors.identifier && <p className="text-sm text-destructive">{form.errors.identifier}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label>Nome</Label>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </div>
                    <div className="space-y-2">
                        <Label>Base URL (opcional)</Label>
                        <Input value={form.data.base_url} onChange={(e) => form.setData('base_url', e.target.value)} placeholder="https://…" />
                    </div>
                    <div className="space-y-2">
                        <Label>Ambiente</Label>
                        <EnvironmentSelect
                            value={form.data.environment}
                            onChange={(value) => form.setData('environment', value)}
                        />
                        <p className="text-xs text-muted-foreground">
                            Define qual token é usado nas consultas. Sandbox costuma retornar dados de teste.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label>Token de produção (opcional)</Label>
                        <Input
                            type="password"
                            value={form.data.token}
                            onChange={(e) => form.setData('token', e.target.value)}
                            placeholder="Cole o token de produção"
                            autoComplete="off"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Token de sandbox (opcional)</Label>
                        <Input
                            type="password"
                            value={form.data.sandbox_token}
                            onChange={(e) => form.setData('sandbox_token', e.target.value)}
                            placeholder="Cole o token de sandbox/teste"
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            Se não informar, o sistema usa os tokens do <code>.env</code> para cada ambiente.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>Cadastrar</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditProviderDialog({
    provider,
    trigger,
}: {
    provider: ProviderRow;
    trigger?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm(providerFormDefaults(provider));

    const handleOpenChange = (next: boolean) => {
        if (next) {
            form.setData(providerFormDefaults(provider));
            form.clearErrors();
        }

        setOpen(next);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/providers/${provider.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="outline" size="sm">
                        <Pencil className="size-4" />
                        Editar
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Editar provedor</DialogTitle>
                    <DialogDescription>
                        Atualize nome, URL base e credenciais de <code>{provider.identifier}</code>.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Identificador</Label>
                        <Input value={provider.identifier} readOnly className="bg-muted" />
                    </div>
                    <div className="space-y-2">
                        <Label>Nome</Label>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label>Base URL</Label>
                        <Input
                            value={form.data.base_url}
                            onChange={(e) => form.setData('base_url', e.target.value)}
                            placeholder="https://apibrasil.io/api"
                        />
                        {form.errors.base_url && <p className="text-sm text-destructive">{form.errors.base_url}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label>Ambiente</Label>
                        <EnvironmentSelect
                            value={form.data.environment}
                            onChange={(value) => form.setData('environment', value)}
                        />
                        <p className="text-xs text-muted-foreground">
                            Seleciona qual token (produção ou sandbox) é usado nas consultas deste provedor.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label>Token de produção</Label>
                        <Input
                            type="password"
                            value={form.data.token}
                            onChange={(e) => form.setData('token', e.target.value)}
                            placeholder={provider.has_token ? '••••••••••••••••' : 'Cole o token de produção'}
                            autoComplete="new-password"
                        />
                        <p className="text-xs text-muted-foreground">
                            {provider.has_token
                                ? 'Deixe em branco para manter o token atual. Preencha para substituir.'
                                : 'Nenhum token salvo. Informe aqui ou configure no .env.'}
                        </p>
                        {form.errors.token && <p className="text-sm text-destructive">{form.errors.token}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label>Token de sandbox</Label>
                        <Input
                            type="password"
                            value={form.data.sandbox_token}
                            onChange={(e) => form.setData('sandbox_token', e.target.value)}
                            placeholder={provider.has_sandbox_token ? '••••••••••••••••' : 'Cole o token de sandbox/teste'}
                            autoComplete="new-password"
                        />
                        <p className="text-xs text-muted-foreground">
                            {provider.has_sandbox_token
                                ? 'Deixe em branco para manter o token de sandbox atual.'
                                : 'Nenhum token de sandbox salvo. Informe aqui ou configure no .env.'}
                        </p>
                        {form.errors.sandbox_token && <p className="text-sm text-destructive">{form.errors.sandbox_token}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>Salvar</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function capabilityDefaults(capability?: Capability) {
    const cost = capability?.cost_cents != null ? capability.cost_cents : null;

    return {
        query_type: capability?.query_type ?? '',
        priority: String(capability?.priority ?? 100),
        cost_reais: cost !== null ? (cost / 100).toFixed(2) : '',
        price_reais: capability ? (capability.price_cents / 100).toFixed(2) : '0.50',
        enabled: capability?.enabled ?? true,
        endpoint: capability?.endpoint ?? '',
        body_key: capability?.body_key ?? '',
        device_token: '',
    };
}

function CapabilityDialog({
    providerId,
    capability,
    trigger,
}: {
    providerId: string;
    capability?: Capability;
    trigger?: ReactNode;
}) {
    const isEdit = !!capability;
    const [open, setOpen] = useState(false);
    const form = useForm(capabilityDefaults(capability));

    const handleOpenChange = (next: boolean) => {
        if (next) {
            form.setData(capabilityDefaults(capability));
            form.clearErrors();
        }

        setOpen(next);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            priority: Number(d.priority),
            price_cents: Math.round(Number(d.price_reais) * 100),
            cost_cents: d.cost_reais === '' ? null : Math.round(Number(d.cost_reais) * 100),
        }));
        form.post(`/admin/providers/${providerId}/capabilities`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    const onCostChange = (value: string) => {
        form.setData('cost_reais', value);

        if (value !== '' && !Number.isNaN(Number(value))) {
            form.setData('price_reais', (Number(value) * MARKUP).toFixed(2));
        }
    };

    const displayName = capability
        ? queryTypeDisplayName(capability.query_type_name, capability.query_type)
        : null;

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="outline" size="sm">
                        <Plus /> Capacidade
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Editar capacidade' : 'Adicionar capacidade'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? `Altere prioridade, custo ou situação de ${displayName ?? capability?.query_type}.`
                            : 'Define como este provedor atende um tipo de consulta.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Tipo de consulta</Label>
                        <Input
                            value={form.data.query_type}
                            onChange={(e) => form.setData('query_type', e.target.value)}
                            placeholder="cpf"
                            required
                            readOnly={isEdit}
                            className={isEdit ? 'bg-muted font-mono' : 'font-mono'}
                        />
                        {isEdit && displayName && (
                            <p className="text-xs text-muted-foreground">{displayName}</p>
                        )}
                        {isEdit && (
                            <p className="text-xs text-muted-foreground">
                                O tipo de consulta não pode ser alterado após o cadastro.
                            </p>
                        )}
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div className="space-y-2">
                            <Label>Prioridade</Label>
                            <Input
                                type="number"
                                min={0}
                                value={form.data.priority}
                                onChange={(e) => form.setData('priority', e.target.value)}
                                required
                            />
                            <p className="text-xs text-muted-foreground">Menor = primeiro</p>
                        </div>
                        <div className="space-y-2">
                            <Label>Custo do provedor (R$)</Label>
                            <Input
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.cost_reais}
                                onChange={(e) => onCostChange(e.target.value)}
                                placeholder="0.00"
                            />
                            <p className="text-xs text-muted-foreground">Define a venda (+10%)</p>
                        </div>
                        <div className="space-y-2">
                            <Label>Preço de venda (R$)</Label>
                            <Input
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.price_reais}
                                onChange={(e) => form.setData('price_reais', e.target.value)}
                                required
                            />
                        </div>
                    </div>
                    <div className="space-y-3 rounded-lg border border-border bg-muted/30 p-3">
                        <p className="text-xs font-medium text-muted-foreground">
                            Endpoint do provedor (opcional — em branco usa o padrão do .env)
                        </p>
                        <div className="space-y-2">
                            <Label>Caminho (endpoint)</Label>
                            <Input
                                value={form.data.endpoint}
                                onChange={(e) => form.setData('endpoint', e.target.value)}
                                placeholder="dados/cpf"
                            />
                            {form.errors.endpoint && <p className="text-sm text-destructive">{form.errors.endpoint}</p>}
                            <p className="text-xs text-muted-foreground">
                                Recurso do provedor para este tipo. APIBrasil: caminho (ex.: <code>dados/cpf</code>).
                                CPF.CNPJ: ID do pacote (ex.: <code>2</code>, <code>6</code>).
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label>Chave do body (opcional)</Label>
                            <Input
                                value={form.data.body_key}
                                onChange={(e) => form.setData('body_key', e.target.value)}
                                placeholder="cpf"
                            />
                            <p className="text-xs text-muted-foreground">
                                Converte o parâmetro <code>document</code> da consulta para esta chave no body enviado.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label>Device Token (opcional)</Label>
                            <Input
                                type="password"
                                value={form.data.device_token}
                                onChange={(e) => form.setData('device_token', e.target.value)}
                                placeholder={capability?.has_device_token ? '••••••••••••••••' : 'Token do dispositivo deste serviço'}
                                autoComplete="off"
                            />
                            {form.errors.device_token && <p className="text-sm text-destructive">{form.errors.device_token}</p>}
                            <p className="text-xs text-muted-foreground">
                                {capability?.has_device_token
                                    ? 'Já existe um device token salvo. Deixe em branco para mantê-lo; preencha para substituir.'
                                    : 'Guardado de forma cifrada. Necessário para serviços da APIBrasil que exigem DeviceToken.'}
                            </p>
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                            className="size-4 rounded border-input accent-brand-green"
                        />
                        Habilitada
                    </label>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>Salvar</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

AdminProvidersIndex.layout = {
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Provedores', href: '/admin/providers' },
    ],
};
