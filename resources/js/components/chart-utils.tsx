import type { ComponentType, ReactNode } from 'react';
import { formatBRL } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useChartSize } from '@/hooks/use-chart-size';

interface ChartTooltipEntry {
    name?: string;
    value?: number;
    color?: string;
}

interface MoneyChartTooltipProps {
    active?: boolean;
    payload?: ChartTooltipEntry[];
    label?: string | number;
}

export const CHART_COLORS = {
    revenue: '#22c55e',
    recharges: '#3b82f6',
    consumption: '#f59e0b',
    margin: '#2563eb',
    cost: '#ef4444',
    volume: '#8b5cf6',
} as const;

export function formatChartDayLabel(value: string): string {
    const [month, day] = value.split('-');

    if (!month || !day) {
        return value;
    }

    return `${day}/${month}`;
}

export function truncateLabel(value: string, max = 16): string {
    return value.length > max ? `${value.slice(0, max)}…` : value;
}

export function MoneyChartTooltip({
    active,
    payload,
    label,
}: MoneyChartTooltipProps) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="min-w-[10rem] rounded-lg border border-border bg-popover px-3 py-2.5 text-popover-foreground shadow-lg">
            {label !== undefined && label !== null && label !== '' && (
                <p className="mb-2 text-xs font-medium text-muted-foreground">{label}</p>
            )}
            <div className="space-y-1.5">
                {payload.map((entry) => (
                    <div key={String(entry.name)} className="flex items-center justify-between gap-4 text-sm">
                        <span className="flex items-center gap-2 text-muted-foreground">
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: entry.color }}
                            />
                            {entry.name}
                        </span>
                        <span className="font-semibold tabular-nums text-foreground">
                            {formatBRL(Number(entry.value ?? 0))}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function ChartEmptyState({
    title,
    description,
    icon: Icon,
}: {
    title: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
}) {
    return (
        <div className="flex h-[280px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-muted/20 px-6 text-center">
            <Icon className="size-8 text-muted-foreground/40" />
            <p className="text-sm font-medium text-muted-foreground">{title}</p>
            <p className="max-w-xs text-xs text-muted-foreground/80">{description}</p>
        </div>
    );
}

export function chartYDomainMax(max: number): number {
    if (max <= 0) {
        return 100;
    }

    const padded = Math.ceil(max * 1.2);

    if (padded < 100) {
        return 100;
    }

    return padded;
}

export function ChartResponsiveShell({
    height,
    className,
    children,
}: {
    height: number;
    className?: string;
    children: (size: { width: number; height: number }) => ReactNode;
}) {
    const { ref, width, height: chartHeight } = useChartSize(height);

    return (
        <div ref={ref} className={cn('w-full min-w-0', className)} style={{ height }}>
            {width > 0 ? children({ width, height: chartHeight }) : null}
        </div>
    );
}

export function ChartLegend({
    items,
    className,
}: {
    items: Array<{ label: string; color: string }>;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground', className)}>
            {items.map((item) => (
                <span key={item.label} className="inline-flex items-center gap-2">
                    <span
                        className="size-2.5 shrink-0 rounded-full"
                        style={{ backgroundColor: item.color }}
                    />
                    {item.label}
                </span>
            ))}
        </div>
    );
}

export function moneyChartLayout(isMobile: boolean) {
    return {
        height: isMobile ? 260 : 280,
        margin: {
            top: isMobile ? 8 : 8,
            right: isMobile ? 8 : 20,
            left: isMobile ? 0 : 4,
            bottom: isMobile ? 8 : 4,
        },
        yAxisWidth: isMobile ? 52 : 76,
        xAxisHeight: isMobile ? 56 : 30,
        xAxisAngle: isMobile ? -35 : 0,
        xAxisTextAnchor: (isMobile ? 'end' : 'middle') as 'end' | 'middle',
        minTickGap: isMobile ? 10 : 24,
        tickFontSize: isMobile ? 10 : 11,
        legend: {
            verticalAlign: (isMobile ? 'bottom' : 'top') as 'bottom' | 'top',
            align: (isMobile ? 'left' : 'right') as 'left' | 'right',
            wrapperStyle: {
                fontSize: isMobile ? 11 : 12,
                paddingBottom: isMobile ? 4 : 8,
                paddingTop: isMobile ? 10 : 0,
            },
        },
    };
}

export function chartMoneyDomain(min: number, max: number): [number, number] {
    if (max <= 0 && min >= 0) {
        return [0, 100];
    }

    const span = max - min;
    const pad = Math.max(Math.ceil(span * 0.15), 50);
    const low = min < 0 ? Math.floor((min - pad) / 50) * 50 : 0;
    let high = Math.ceil((max + pad) / 50) * 50;

    if (high <= low) {
        high = low + 100;
    }

    return [low, high];
}

export function formatQueryTypeLabel(value: string): string {
    return value
        .split('_')
        .map((part, index) => (index === 0 ? part.toUpperCase() : part.charAt(0).toUpperCase() + part.slice(1)))
        .join(' ');
}

export function CountChartTooltip({
    active,
    payload,
    label,
}: MoneyChartTooltipProps) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="min-w-[8rem] rounded-lg border border-border bg-popover px-3 py-2.5 text-popover-foreground shadow-lg">
            {label !== undefined && label !== null && label !== '' && (
                <p className="mb-2 text-xs font-medium text-muted-foreground">{label}</p>
            )}
            <div className="space-y-1.5">
                {payload.map((entry) => (
                    <div key={String(entry.name)} className="flex items-center justify-between gap-4 text-sm">
                        <span className="flex items-center gap-2 text-muted-foreground">
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: entry.color }}
                            />
                            {entry.name}
                        </span>
                        <span className="font-semibold tabular-nums text-foreground">
                            {Number(entry.value ?? 0).toLocaleString('pt-BR')}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

interface TypeBarTooltipEntry {
    payload?: {
        label?: string;
        total?: number;
        revenue?: number;
        cost?: number;
    };
}

export function TypeBarChartTooltip({
    active,
    payload,
}: {
    active?: boolean;
    payload?: TypeBarTooltipEntry[];
}) {
    const row = payload?.[0]?.payload;

    if (!active || !row) {
        return null;
    }

    const margin = (row.revenue ?? 0) - (row.cost ?? 0);

    return (
        <div className="min-w-[11rem] rounded-lg border border-border bg-popover px-3 py-2.5 text-popover-foreground shadow-lg">
            <p className="mb-2 text-xs font-medium text-muted-foreground">{row.label}</p>
            <div className="space-y-1.5 text-sm">
                <div className="flex justify-between gap-4">
                    <span className="text-muted-foreground">Consultas</span>
                    <span className="font-semibold tabular-nums">{(row.total ?? 0).toLocaleString('pt-BR')}</span>
                </div>
                <div className="flex justify-between gap-4">
                    <span className="text-muted-foreground">Receita</span>
                    <span className="font-semibold tabular-nums">{formatBRL(row.revenue ?? 0)}</span>
                </div>
                <div className="flex justify-between gap-4">
                    <span className="text-muted-foreground">Custo</span>
                    <span className="font-semibold tabular-nums">{formatBRL(row.cost ?? 0)}</span>
                </div>
                <div className="flex justify-between gap-4 border-t border-border pt-1.5">
                    <span className="text-muted-foreground">Margem</span>
                    <span className="font-semibold tabular-nums">{formatBRL(margin)}</span>
                </div>
            </div>
        </div>
    );
}
