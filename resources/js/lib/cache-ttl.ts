export type CacheTtlSource = 'database' | 'config' | 'default' | 'disabled';

export function formatCacheTtl(seconds: number): string {
    if (seconds === 0) {
        return 'Desabilitado';
    }

    if (seconds < 3600) {
        return seconds === 1 ? '1 segundo' : `${seconds} segundos`;
    }

    if (seconds < 86400) {
        const hours = Math.round(seconds / 3600);

        return hours === 1 ? '1 hora' : `${hours} horas`;
    }

    const days = Math.round(seconds / 86400);

    if (days === 7) {
        return '7 dias';
    }

    if (days === 30) {
        return '30 dias';
    }

    return days === 1 ? '1 dia' : `${days} dias`;
}

export function cacheTtlSourceLabel(source: CacheTtlSource): string {
    switch (source) {
        case 'database':
            return 'Definido no banco';
        case 'config':
            return 'Configuração do sistema';
        case 'default':
            return 'Padrão global';
        case 'disabled':
            return 'Desabilitado';
    }
}

export function cacheTtlSourceShortLabel(source: CacheTtlSource): string {
    switch (source) {
        case 'database':
            return 'Banco';
        case 'config':
            return 'Config';
        case 'default':
            return 'Padrão';
        case 'disabled':
            return 'Off';
    }
}

export function resolveCacheTtlMode(
    cacheTtlSeconds: number | null,
    presets: Array<{ seconds: number }>,
): 'disabled' | 'default' | 'preset' | 'custom' {
    if (cacheTtlSeconds === 0) {
        return 'disabled';
    }

    if (cacheTtlSeconds === null) {
        return 'default';
    }

    if (presets.some((preset) => preset.seconds === cacheTtlSeconds)) {
        return 'preset';
    }

    return 'custom';
}

export function secondsToDays(seconds: number): string {
    return String(Math.round(seconds / 86400));
}

export function daysToSeconds(days: string): number {
    const parsed = Number.parseInt(days, 10);

    if (!Number.isFinite(parsed) || parsed < 1) {
        return 86400;
    }

    return parsed * 86400;
}
