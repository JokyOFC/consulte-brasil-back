import type { ComponentType } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    icon: Icon,
    hint,
    highlight = false,
}: {
    label: string;
    value: number | string;
    icon: ComponentType<{ className?: string }>;
    hint?: string;
    highlight?: boolean;
}) {
    return (
        <Card className={cn('gap-0 py-0', highlight && 'border-brand-green/40 bg-brand-green/5')}>
            <CardContent className="flex items-center justify-between p-5">
                <div className="min-w-0">
                    <p className="truncate text-sm text-muted-foreground">{label}</p>
                    <p className="mt-1 text-2xl font-bold tracking-tight">{value}</p>
                    {hint && <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p>}
                </div>
                <div
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-xl',
                        highlight ? 'bg-brand-green/15 text-brand-green' : 'bg-muted text-muted-foreground',
                    )}
                >
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}
