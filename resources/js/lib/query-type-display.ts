import { formatQueryTypeLabel } from '@/components/chart-utils';

export function queryTypeCategory(name: string, code: string): string {
    const separator = name.indexOf(' — ');

    if (separator > 0) {
        return name.slice(0, separator);
    }

    const parts = code.split('_');

    if (parts[0] === 'ab' && parts[1]) {
        return parts[1].toUpperCase();
    }

    return parts[0].toUpperCase();
}

export function queryTypeDisplayName(name: string | null | undefined, code: string): string {
    if (name) {
        return name;
    }

    return formatQueryTypeLabel(code);
}

export function queryTypeShortName(name: string, category: string): string {
    const prefix = `${category} — `;

    if (name.startsWith(prefix)) {
        return name.slice(prefix.length);
    }

    return name;
}

export interface QueryTypeGroup<T> {
    category: string;
    items: T[];
}

export function groupByQueryTypeCategory<T>(
    items: T[],
    getName: (item: T) => string,
    getCode: (item: T) => string,
): QueryTypeGroup<T>[] {
    const groups = new Map<string, T[]>();

    for (const item of items) {
        const name = getName(item);
        const code = getCode(item);
        const category = queryTypeCategory(name, code);
        const bucket = groups.get(category) ?? [];
        bucket.push(item);
        groups.set(category, bucket);
    }

    return Array.from(groups.entries())
        .sort(([left], [right]) => left.localeCompare(right, 'pt-BR'))
        .map(([category, groupItems]) => ({
            category,
            items: groupItems.sort((left, right) =>
                getName(left).localeCompare(getName(right), 'pt-BR'),
            ),
        }));
}
