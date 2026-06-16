import { Loader2, RefreshCw } from 'lucide-react';
import { useCallback, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface ProviderBalance {
    status: 'ok' | 'error' | 'unsupported' | 'no_token';
    summary?: string | null;
    total?: number | null;
    packages?: { id: string; name: string; balance: number }[];
    error?: string | null;
    fetched_at?: string | null;
}

interface ProviderBalanceDisplayProps {
    balance: ProviderBalance;
    providerId?: string;
    compact?: boolean;
    className?: string;
}

function formatCredits(value: number): string {
    return value.toLocaleString('pt-BR');
}

function compactLabel(balance: ProviderBalance): string {
    if (balance.status === 'ok') {
        if (balance.total != null) {
            return `${formatCredits(balance.total)} créditos`;
        }
        if (balance.packages?.length === 1) {
            return `${formatCredits(balance.packages[0].balance)} créditos`;
        }
    }
    if (balance.status === 'unsupported') {
        return 'N/A';
    }
    if (balance.status === 'no_token') {
        return 'Sem token';
    }
    return 'Indisponível';
}

export function ProviderBalanceDisplay({
    balance: initialBalance,
    providerId,
    compact = false,
    className,
}: ProviderBalanceDisplayProps) {
    const [balance, setBalance] = useState(initialBalance);
    const [loading, setLoading] = useState(false);

    const refresh = useCallback(async () => {
        if (!providerId) {
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(`/admin/providers/${providerId}/balance`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (response.ok) {
                const data = await response.json();
                setBalance(data.balance);
            }
        } finally {
            setLoading(false);
        }
    }, [providerId]);

    if (compact) {
        const isOk = balance.status === 'ok';

        return (
            <div className={cn('flex items-center gap-1', className)}>
                <Badge
                    variant="outline"
                    className={cn(
                        'gap-1 text-[11px] font-normal tabular-nums',
                        isOk
                            ? 'border-border text-foreground'
                            : 'border-border text-muted-foreground',
                    )}
                >
                    {loading ? '…' : compactLabel(balance)}
                </Badge>
                {providerId && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-6 text-muted-foreground"
                        onClick={(e) => {
                            e.stopPropagation();
                            refresh();
                        }}
                        disabled={loading}
                        title="Atualizar saldo"
                    >
                        {loading ? <Loader2 className="size-3 animate-spin" /> : <RefreshCw className="size-3" />}
                    </Button>
                )}
            </div>
        );
    }

    const packages = balance.packages ?? [];
    const showPackages = packages.length > 0 && packages.length <= 4;
    const isOk = balance.status === 'ok';

    return (
        <div className={cn('flex items-center justify-between gap-4 rounded-lg border border-border/60 bg-muted/20 px-4 py-3', className)}>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">Saldo no provedor</p>
                {loading ? (
                    <p className="mt-0.5 text-sm text-muted-foreground">Consultando…</p>
                ) : isOk ? (
                    <>
                        <p className="mt-0.5 text-xl font-semibold tabular-nums">
                            {balance.total != null
                                ? formatCredits(balance.total)
                                : packages[0]?.balance != null
                                    ? formatCredits(packages[0].balance)
                                    : '—'}
                            <span className="ml-1.5 text-sm font-normal text-muted-foreground">créditos</span>
                        </p>
                        {showPackages && (
                            <div className="mt-2 flex flex-wrap gap-2">
                                {packages.map((pkg) => (
                                    <span
                                        key={pkg.id}
                                        className="rounded-md border border-border/50 bg-background/50 px-2 py-0.5 text-xs text-muted-foreground"
                                    >
                                        {pkg.name}
                                        {' '}
                                        <span className="font-medium tabular-nums text-foreground">
                                            {formatCredits(pkg.balance)}
                                        </span>
                                    </span>
                                ))}
                            </div>
                        )}
                        {!showPackages && packages.length > 4 && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {packages.length} pacotes · total {formatCredits(balance.total ?? 0)}
                            </p>
                        )}
                    </>
                ) : (
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {balance.status === 'unsupported'
                            ? 'Não aplicável'
                            : balance.status === 'no_token'
                                ? 'Token não configurado'
                                : balance.error ?? 'Indisponível'}
                    </p>
                )}
                {balance.fetched_at && !loading && (
                    <p className="mt-1 text-[10px] text-muted-foreground/70">
                        {new Date(balance.fetched_at).toLocaleString('pt-BR', {
                            day: '2-digit',
                            month: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                        })}
                    </p>
                )}
            </div>
            {providerId && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 text-muted-foreground"
                    onClick={refresh}
                    disabled={loading}
                    title="Atualizar saldo"
                >
                    {loading ? <Loader2 className="size-4 animate-spin" /> : <RefreshCw className="size-4" />}
                </Button>
            )}
        </div>
    );
}
