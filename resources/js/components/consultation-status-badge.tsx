import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STYLES: Record<string, string> = {
    success: 'border-transparent bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
    refunded: 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    failed: 'border-transparent bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

const LABELS: Record<string, string> = {
    success: 'Sucesso',
    refunded: 'Estornada',
    failed: 'Falhou',
};

export function ConsultationStatusBadge({ status }: { status: string }) {
    return (
        <Badge variant="outline" className={cn(STYLES[status])}>
            {LABELS[status] ?? status}
        </Badge>
    );
}
