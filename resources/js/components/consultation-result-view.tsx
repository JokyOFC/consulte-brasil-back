import { Building2, ChevronDown, UserRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConsultationAttachmentsPanel } from '@/components/consultation-attachments-panel';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { findPdfAttachments, sanitizeResultForDisplay } from '@/lib/consultation-attachments';
import {
    formatResultPrimitive,
    getScoreDescription,
    getScoreValue,
    humanizeFieldKey,
    isPlainObject,
    isPrimitive,
    isScoreObject,
    shouldRenderInlineObject,
    sortResultEntries,
    tableColumns,
} from '@/lib/consultation-result';
import { cn } from '@/lib/utils';

export function ConsultationResultView({ data }: { data: Record<string, unknown> }) {
    const attachments = useMemo(() => findPdfAttachments(data), [data]);
    const sanitized = useMemo(() => sanitizeResultForDisplay(data), [data]);
    const entries = sortResultEntries(Object.entries(sanitized));

    if (entries.length === 0 && attachments.length === 0) {
        return (
            <div className="px-6 py-8 text-center text-sm text-muted-foreground">
                Nenhum dado estruturado disponível.
            </div>
        );
    }

    const allPrimitive = entries.every(([, value]) => isPrimitive(value));

    if (allPrimitive) {
        return (
            <>
                <ConsultationAttachmentsPanel attachments={attachments} />
                <div className="grid gap-px bg-border/60 sm:grid-cols-2">
                    {entries.map(([key, value]) => (
                        <FieldCell key={key} label={humanizeFieldKey(key)} value={formatResultPrimitive(value)} />
                    ))}
                </div>
            </>
        );
    }

    return (
        <>
            <ConsultationAttachmentsPanel attachments={attachments} />
            <div className="space-y-3 p-4 md:p-5">
                {entries.map(([key, value], index) => (
                    <ResultSection key={key} title={humanizeFieldKey(key)} value={value} defaultOpen={index < 2} />
                ))}
            </div>
        </>
    );
}

function ResultSection({
    title,
    value,
    defaultOpen = true,
}: {
    title: string;
    value: unknown;
    defaultOpen?: boolean;
}) {
    if (isPrimitive(value)) {
        if (title.toLowerCase().includes('nada consta') && typeof value === 'boolean') {
            return (
                <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                    <NadaConstaField label={title} value={value} inline />
                </div>
            );
        }

        return (
            <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                <FieldCell label={title} value={formatResultPrimitive(value)} inline />
            </div>
        );
    }

    if (Array.isArray(value)) {
        return (
            <SectionShell title={title} defaultOpen={defaultOpen}>
                <ArrayValue value={value} />
            </SectionShell>
        );
    }

    if (!isPlainObject(value)) {
        return (
            <SectionShell title={title} defaultOpen={defaultOpen}>
                <p className="text-sm">{formatResultPrimitive(value)}</p>
            </SectionShell>
        );
    }

    if (isProfileBlock(title, value)) {
        return (
            <SectionShell title={title} defaultOpen defaultOpenLocked>
                <ProfileCard data={value} />
            </SectionShell>
        );
    }

    if (isScoreObject(value)) {
        return (
            <SectionShell title={title} defaultOpen defaultOpenLocked>
                <ScoreCard data={value} />
            </SectionShell>
        );
    }

    if (shouldRenderInlineObject(value)) {
        return (
            <SectionShell title={title} defaultOpen={defaultOpen}>
                <div className="grid gap-3 sm:grid-cols-2">
                    {sortResultEntries(Object.entries(value)).map(([key, entry]) => (
                        <FieldCell key={key} label={humanizeFieldKey(key)} value={formatResultPrimitive(entry)} />
                    ))}
                </div>
            </SectionShell>
        );
    }

    return (
        <SectionShell title={title} defaultOpen={defaultOpen}>
            <ObjectTree data={value} depth={0} />
        </SectionShell>
    );
}

function SectionShell({
    title,
    children,
    defaultOpen = true,
    defaultOpenLocked = false,
}: {
    title: string;
    children: React.ReactNode;
    defaultOpen?: boolean;
    defaultOpenLocked?: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="overflow-hidden rounded-xl border border-border/60 bg-card">
            <CollapsibleTrigger
                className={cn(
                    'flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition-colors hover:bg-muted/30',
                    defaultOpenLocked && 'cursor-default hover:bg-transparent',
                )}
                disabled={defaultOpenLocked}
            >
                <span className="text-sm font-semibold">{title}</span>
                {!defaultOpenLocked && (
                    <ChevronDown className={cn('size-4 shrink-0 text-muted-foreground transition-transform', open && 'rotate-180')} />
                )}
            </CollapsibleTrigger>
            <CollapsibleContent className="border-t border-border/60 px-4 py-4">{children}</CollapsibleContent>
        </Collapsible>
    );
}

function ObjectTree({ data, depth }: { data: Record<string, unknown>; depth: number }) {
    const entries = sortResultEntries(Object.entries(data));

    return (
        <div className={cn('space-y-4', depth > 0 && 'rounded-lg border border-border/50 bg-muted/15 p-3')}>
            {entries.map(([key, value]) => (
                <ResultNode key={key} label={humanizeFieldKey(key)} value={value} depth={depth} />
            ))}
        </div>
    );
}

function ResultNode({ label, value, depth }: { label: string; value: unknown; depth: number }) {
    if (isPrimitive(value)) {
        if (label.toLowerCase().includes('nada consta') && typeof value === 'boolean') {
            return <NadaConstaField label={label} value={value} />;
        }

        return <FieldCell label={label} value={formatResultPrimitive(value)} inline />;
    }

    if (Array.isArray(value)) {
        return (
            <div className="space-y-2">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{label}</p>
                <ArrayValue value={value} />
            </div>
        );
    }

    if (!isPlainObject(value)) {
        return <FieldCell label={label} value={formatResultPrimitive(value)} inline />;
    }

    if (isScoreObject(value)) {
        return (
            <div className="space-y-2">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{label}</p>
                <ScoreCard data={value} compact />
            </div>
        );
    }

    if (isProfileBlock(label, value)) {
        return (
            <div className="space-y-2">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{label}</p>
                <ProfileCard data={value} />
            </div>
        );
    }

    if (shouldRenderInlineObject(value)) {
        return (
            <div className="space-y-2">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{label}</p>
                <div className="grid gap-2 sm:grid-cols-2">
                    {sortResultEntries(Object.entries(value)).map(([key, entry]) => (
                        <FieldCell key={key} label={humanizeFieldKey(key)} value={formatResultPrimitive(entry)} compact />
                    ))}
                </div>
            </div>
        );
    }

    if (depth >= 2) {
        return (
            <details className="rounded-lg border border-border/50 bg-muted/10 px-3 py-2">
                <summary className="cursor-pointer text-sm font-medium">{label}</summary>
                <pre className="mt-2 max-h-48 overflow-auto text-xs text-muted-foreground">
                    {JSON.stringify(sanitizeResultForDisplay(value), null, 2)}
                </pre>
            </details>
        );
    }

    return (
        <div className="space-y-2">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{label}</p>
            <ObjectTree data={value} depth={depth + 1} />
        </div>
    );
}

function ArrayValue({ value }: { value: unknown[] }) {
    if (value.length === 0) {
        return (
            <Badge variant="outline" className="font-normal text-muted-foreground">
                Nenhum registro encontrado
            </Badge>
        );
    }

    if (value.every((item) => isPlainObject(item))) {
        const rows = value as Record<string, unknown>[];
        const columns = tableColumns(rows);

        if (columns.length === 0) {
            return (
                <pre className="max-h-48 overflow-auto rounded-lg bg-muted/30 p-3 text-xs">
                    {JSON.stringify(
                        value.map((item) => (isPlainObject(item) ? sanitizeResultForDisplay(item) : item)),
                        null,
                        2,
                    )}
                </pre>
            );
        }

        return (
            <div className="overflow-x-auto rounded-lg border border-border/60">
                <table className="w-full min-w-[420px] text-sm">
                    <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            {columns.map((column) => (
                                <th key={column} className="px-3 py-2 font-medium">
                                    {humanizeFieldKey(column)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={index} className="border-t border-border/60">
                                {columns.map((column) => (
                                    <td key={column} className="px-3 py-2 align-top text-sm break-words">
                                        {formatResultPrimitive(row[column])}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            {value.map((item, index) => (
                <Badge key={index} variant="secondary" className="font-normal">
                    {formatResultPrimitive(item)}
                </Badge>
            ))}
        </div>
    );
}

function ProfileCard({ data }: { data: Record<string, unknown> }) {
    const name = [data.nome, data.name].find((value) => typeof value === 'string') as string | undefined;
    const document = [data.cpf, data.cnpj, data.documento].find((value) => typeof value === 'string') as string | undefined;

    const addressParts = isPlainObject(data.endereco)
        ? [
              data.endereco.logradouro,
              data.endereco.numero,
              data.endereco.bairro,
              data.endereco.cidade,
              data.endereco.uf,
              data.endereco.cep,
          ]
              .filter((part) => part !== null && part !== undefined && String(part).trim() !== '')
              .map(String)
        : [];

    const otherEntries = sortResultEntries(
        Object.entries(data).filter(([key]) => !['nome', 'name', 'cpf', 'cnpj', 'documento', 'endereco'].includes(key)),
    );

    // Campos simples vão na grade compacta; objetos/arrays aninhados são
    // renderizados recursivamente (senão virariam "[object Object]").
    const simpleEntries = otherEntries.filter(([, value]) => isPrimitive(value));
    const complexEntries = otherEntries.filter(([, value]) => !isPrimitive(value));

    return (
        <div className="space-y-4">
            <div className="flex items-start gap-3 rounded-xl bg-muted/30 p-4">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-green/10 text-brand-green">
                    {data.cnpj ? <Building2 className="size-5" /> : <UserRound className="size-5" />}
                </span>
                <div className="min-w-0 space-y-1">
                    {name && <p className="text-lg font-semibold leading-tight">{name}</p>}
                    {document && <p className="text-sm text-muted-foreground">{document}</p>}
                    {addressParts.length > 0 && (
                        <p className="text-sm leading-relaxed text-muted-foreground">{addressParts.join(', ')}</p>
                    )}
                </div>
            </div>

            {simpleEntries.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2">
                    {simpleEntries.map(([key, value]) => (
                        <FieldCell key={key} label={humanizeFieldKey(key)} value={formatResultPrimitive(value)} compact />
                    ))}
                </div>
            )}

            {complexEntries.length > 0 && (
                <div className="space-y-3">
                    {complexEntries.map(([key, value]) => (
                        <ResultNode key={key} label={humanizeFieldKey(key)} value={value} depth={1} />
                    ))}
                </div>
            )}
        </div>
    );
}

function ScoreCard({ data, compact = false }: { data: Record<string, unknown>; compact?: boolean }) {
    const score = getScoreValue(data);
    const description = getScoreDescription(data);
    const extras = sortResultEntries(
        Object.entries(data).filter(([key]) => !['score', 'pontuacao', 'texto', 'mensagem', 'descricao', 'message'].includes(key)),
    );
    const simpleExtras = extras.filter(([, value]) => isPrimitive(value));
    const complexExtras = extras.filter(([, value]) => !isPrimitive(value));

    return (
        <div className={cn('rounded-xl border border-brand-green/20 bg-gradient-to-br from-brand-green/10 to-transparent p-4', compact && 'p-3')}>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                {score !== null && (
                    <div>
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Pontuação</p>
                        <p className={cn('font-bold tabular-nums text-brand-green', compact ? 'text-3xl' : 'text-4xl')}>{score}</p>
                    </div>
                )}
                {description && (
                    <p className={cn('max-w-2xl text-sm leading-relaxed text-muted-foreground', compact && 'text-xs')}>
                        {description}
                    </p>
                )}
            </div>
            {simpleExtras.length > 0 && (
                <div className="mt-4 grid gap-2 border-t border-border/50 pt-4 sm:grid-cols-2">
                    {simpleExtras.map(([key, value]) => (
                        <FieldCell key={key} label={humanizeFieldKey(key)} value={formatResultPrimitive(value)} compact />
                    ))}
                </div>
            )}
            {complexExtras.length > 0 && (
                <div className="mt-4 space-y-3 border-t border-border/50 pt-4">
                    {complexExtras.map(([key, value]) => (
                        <ResultNode key={key} label={humanizeFieldKey(key)} value={value} depth={1} />
                    ))}
                </div>
            )}
        </div>
    );
}

function NadaConstaField({
    label,
    value,
    inline = false,
}: {
    label: string;
    value: boolean;
    inline?: boolean;
}) {
    const positive = value;

    return (
        <div className={cn(!inline && 'rounded-lg bg-muted/20 px-3 py-2.5')}>
            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{label}</p>
            <div className="mt-2">
                <Badge
                    variant={positive ? 'default' : 'destructive'}
                    className={cn(
                        'text-sm font-semibold',
                        positive && 'bg-brand-green hover:bg-brand-green/90',
                    )}
                >
                    {positive ? 'NADA CONSTA' : 'CONSTAM REGISTROS'}
                </Badge>
            </div>
        </div>
    );
}

function FieldCell({
    label,
    value,
    inline = false,
    compact = false,
}: {
    label: string;
    value: string;
    inline?: boolean;
    compact?: boolean;
}) {
    const longText = value.length > 180;

    return (
        <div className={cn(!inline && 'rounded-lg bg-muted/20 px-3 py-2.5', compact && 'px-0 py-0')}>
            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{label}</p>
            <p
                className={cn(
                    'mt-1 text-sm leading-relaxed break-words',
                    longText && 'max-h-32 overflow-y-auto pr-1',
                )}
            >
                {value}
            </p>
        </div>
    );
}

function isProfileBlock(title: string, data: Record<string, unknown>): boolean {
    const normalized = title.toLowerCase();

    if (normalized.includes('cadastr')) {
        return true;
    }

    return typeof data.nome === 'string' || typeof data.name === 'string';
}
