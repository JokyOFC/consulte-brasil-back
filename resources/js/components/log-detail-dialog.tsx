import { Link } from '@inertiajs/react';
import { AlertTriangle, Copy } from 'lucide-react';
import { ConsultationStatusBadge } from '@/components/consultation-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatBRL } from '@/lib/format';
import { formatDateTime } from '@/lib/datetime';

export interface RequestLog {
    id: string;
    account_id?: string | null;
    account_name?: string | null;
    api_key_id: string | null;
    api_key_name?: string | null;
    method: string;
    path: string;
    route_name: string | null;
    query_type?: string | null;
    status_code: number | null;
    success: boolean;
    duration_ms: number | null;
    ip_address?: string | null;
    user_agent?: string | null;
    query: Record<string, unknown> | null;
    headers?: Record<string, unknown> | null;
    body: Record<string, unknown> | null;
    response: Record<string, unknown> | null;
    consultation_id: string | null;
    consultation_status?: string | null;
    consultation_latency_ms?: number | null;
    from_cache?: boolean | null;
    provider?: string | null;
    amount_charged?: number | null;
    error_type?: string | null;
    error_message?: string | null;
    response_truncated?: boolean;
    created_at: string | null;
}

export function LogStatusBadge({ log }: { log: Pick<RequestLog, 'success' | 'status_code'> }) {
    return (
        <Badge
            variant="outline"
            className={log.success
                ? 'border-transparent bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                : 'border-transparent bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'}
        >
            {log.status_code ?? '—'} {log.success ? 'OK' : 'Erro'}
        </Badge>
    );
}

export function LogDetailDialog({
    log,
    onOpenChange,
    showAccount = false,
}: {
    log: RequestLog | null;
    onOpenChange: (open: boolean) => void;
    showAccount?: boolean;
}) {
    if (!log) {
        return null;
    }

    const copyId = (value: string) => {
        void navigator.clipboard.writeText(value);
    };

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="font-mono">{log.method}</Badge>
                        <span className="truncate font-mono text-sm">{log.path}</span>
                    </DialogTitle>
                    <DialogDescription>
                        {formatDateTime(log.created_at)}
                        {' · '}
                        {log.route_name ?? 'sem rota'}
                        {log.duration_ms != null && ` · ${log.duration_ms} ms`}
                    </DialogDescription>
                </DialogHeader>

                {!log.success && log.error_message && (
                    <div className="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <div className="space-y-1">
                            {log.error_type && (
                                <p className="font-mono text-xs uppercase tracking-wide opacity-80">{log.error_type}</p>
                            )}
                            <p className="break-words">{log.error_message}</p>
                        </div>
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Status HTTP"><LogStatusBadge log={log} /></Field>
                    <Field label="Latência">{log.duration_ms != null ? `${log.duration_ms} ms` : '—'}</Field>
                    {showAccount && (
                        <Field label="Cliente">
                            {log.account_id ? (
                                <Link
                                    href={`/admin/logs?account_id=${log.account_id}`}
                                    className="text-primary hover:underline"
                                >
                                    {log.account_name ?? log.account_id}
                                </Link>
                            ) : (
                                '—'
                            )}
                        </Field>
                    )}
                    <Field label="Chave de API">
                        {log.api_key_name
                            ? `${log.api_key_name} (${log.api_key_id?.slice(0, 8)}…)`
                            : (log.api_key_id ?? '—')}
                    </Field>
                    <Field label="IP">{log.ip_address ?? '—'}</Field>
                    <Field label="Tipo de consulta">
                        {log.query_type ? (
                            <Badge variant="secondary" className="font-mono text-xs">{log.query_type}</Badge>
                        ) : '—'}
                    </Field>
                    <Field label="Provedor">{log.provider ?? '—'}</Field>
                    <Field label="Cache">
                        {log.from_cache === true ? 'Sim' : log.from_cache === false ? 'Não' : '—'}
                    </Field>
                    <Field label="Valor cobrado">
                        {log.amount_charged != null ? formatBRL(log.amount_charged) : '—'}
                    </Field>
                </div>

                {log.consultation_id && (
                    <div className="rounded-lg border border-border bg-muted/30 p-3">
                        <p className="mb-2 text-xs font-medium text-muted-foreground">Consulta vinculada</p>
                        <div className="flex flex-wrap items-center gap-3 text-sm">
                            <code className="rounded bg-muted px-2 py-0.5 text-xs">{log.consultation_id}</code>
                            {log.consultation_status && (
                                <ConsultationStatusBadge status={log.consultation_status} />
                            )}
                            {log.consultation_latency_ms != null && (
                                <span className="text-muted-foreground">{log.consultation_latency_ms} ms (provedor)</span>
                            )}
                            <Button type="button" variant="ghost" size="sm" onClick={() => copyId(log.consultation_id!)}>
                                <Copy className="size-3.5" />
                                Copiar ID
                            </Button>
                        </div>
                    </div>
                )}

                {log.user_agent && (
                    <Field label="User-Agent">
                        <span className="break-all text-xs text-muted-foreground">{log.user_agent}</span>
                    </Field>
                )}

                {log.response_truncated && (
                    <p className="text-xs text-amber-600 dark:text-amber-400">
                        A resposta foi resumida antes de gravar (payload muito grande). Metadados principais foram preservados.
                    </p>
                )}

                <JsonBlock title="Corpo da requisição" value={log.body} />
                <JsonBlock title="Query string" value={log.query} />
                <JsonBlock title="Resposta" value={log.response} />
                {log.headers && <JsonBlock title="Headers" value={log.headers} />}
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <div className="break-all text-sm">{children}</div>
        </div>
    );
}

function JsonBlock({ title, value }: { title: string; value: unknown }) {
    const isEmpty = value == null || (typeof value === 'object' && Object.keys(value as object).length === 0);

    return (
        <div className="space-y-1">
            <p className="text-xs font-medium text-muted-foreground">{title}</p>
            {isEmpty ? (
                <p className="text-xs text-muted-foreground/60">vazio</p>
            ) : (
                <pre className="max-h-72 overflow-auto rounded-lg bg-muted p-3 text-xs leading-relaxed">
                    {JSON.stringify(value, null, 2)}
                </pre>
            )}
        </div>
    );
}

export function LogSummaryCell({ log }: { log: RequestLog }) {
    if (log.success) {
        return (
            <div className="space-y-0.5">
                {log.from_cache === true && (
                    <Badge variant="outline" className="text-[10px]">cache</Badge>
                )}
                {log.provider && (
                    <p className="text-xs text-muted-foreground">{log.provider}</p>
                )}
            </div>
        );
    }

    return (
        <p className="max-w-xs truncate text-xs text-red-600 dark:text-red-400" title={log.error_message ?? undefined}>
            {log.error_message ?? log.error_type ?? 'Erro desconhecido'}
        </p>
    );
}
