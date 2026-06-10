import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export interface RequestLog {
    id: string;
    account_id?: string | null;
    account_name?: string | null;
    api_key_id: string | null;
    method: string;
    path: string;
    route_name: string | null;
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
    created_at: string | null;
}

export function LogStatusBadge({ log }: { log: Pick<RequestLog, 'success' | 'status_code'> }) {
    return (
        <Badge
            variant="outline"
            className={log.success
                ? 'border-transparent bg-green-100 text-green-700'
                : 'border-transparent bg-red-100 text-red-700'}
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

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Badge variant="outline" className="font-mono">{log.method}</Badge>
                        <span className="truncate font-mono text-sm">{log.path}</span>
                    </DialogTitle>
                    <DialogDescription>
                        {log.created_at ? new Date(log.created_at).toLocaleString('pt-BR') : '—'}
                        {' · '}
                        {log.route_name ?? 'sem rota'}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid grid-cols-2 gap-3 text-sm">
                    <Field label="Status">
                        <Badge
                            variant="outline"
                            className={log.success
                                ? 'border-transparent bg-green-100 text-green-700'
                                : 'border-transparent bg-red-100 text-red-700'}
                        >
                            {log.status_code ?? '—'} {log.success ? 'OK' : 'Erro'}
                        </Badge>
                    </Field>
                    <Field label="Latência">{log.duration_ms != null ? `${log.duration_ms} ms` : '—'}</Field>
                    {showAccount && <Field label="Cliente">{log.account_name ?? log.account_id ?? '—'}</Field>}
                    <Field label="API Key">{log.api_key_id ?? '—'}</Field>
                    <Field label="IP">{log.ip_address ?? '—'}</Field>
                    <Field label="Consulta">{log.consultation_id ?? '—'}</Field>
                </div>

                {log.user_agent && (
                    <Field label="User-Agent">
                        <span className="break-all text-xs text-muted-foreground">{log.user_agent}</span>
                    </Field>
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
            <div className="break-all">{children}</div>
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
                <pre className="max-h-64 overflow-auto rounded-lg bg-muted p-3 text-xs">
                    {JSON.stringify(value, null, 2)}
                </pre>
            )}
        </div>
    );
}
