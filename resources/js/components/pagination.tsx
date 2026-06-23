import { Link } from '@inertiajs/react';
import { formatDateTime as formatDateTimeBr } from '@/lib/datetime';

export interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

/** Rótulos do paginator Laravel → texto legível (fallback se tradução faltar). */
function formatLinkLabel(label: string): string {
    const normalized = label.trim();

    if (normalized === 'pagination.previous') {
        return '« Anterior';
    }

    if (normalized === 'pagination.next') {
        return 'Próximo »';
    }

    return label;
}

export function Pagination({
    paginator,
}: {
    paginator: Pick<Paginator<unknown>, 'links' | 'from' | 'to' | 'total'>;
}) {
    if (paginator.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-xs text-muted-foreground">
                Mostrando {paginator.from ?? 0}–{paginator.to ?? 0} de {paginator.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {paginator.links.map((link, i) => {
                    const label = formatLinkLabel(link.label);
                    const className = `rounded-md px-3 py-1.5 text-sm ${
                        link.active ? 'bg-foreground text-background' : 'text-muted-foreground hover:bg-muted'
                    }`;

                    return link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            preserveState
                            replace
                            className={className}
                            dangerouslySetInnerHTML={{ __html: label }}
                        />
                    ) : (
                        <span
                            key={i}
                            className="rounded-md px-3 py-1.5 text-sm text-muted-foreground/40"
                            dangerouslySetInnerHTML={{ __html: label }}
                        />
                    );
                })}
            </div>
        </div>
    );
}

export function formatDateTime(iso: string | null): string {
    return formatDateTimeBr(iso);
}
