/** Fuso horário padrão da aplicação (Brasil). */
export const APP_TIMEZONE = 'America/Sao_Paulo';

const BR_DATETIME: Intl.DateTimeFormatOptions = {
    timeZone: APP_TIMEZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
};

const BR_DATE: Intl.DateTimeFormatOptions = {
    timeZone: APP_TIMEZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
};

/**
 * Formata data/hora no fuso de Brasília.
 * Aceita ISO 8601 ou datetime MySQL (`YYYY-MM-DD HH:mm:ss`) vindo do backend.
 */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = parseAppDateTime(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('pt-BR', BR_DATETIME);
}

/** Apenas a data (dd/mm/aaaa) no fuso de Brasília. */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = parseAppDateTime(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('pt-BR', BR_DATE);
}

function parseAppDateTime(value: string): Date {
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)) {
        // Strings MySQL cruas (DB::table) vêm em UTC no Laravel.
        return new Date(value.replace(' ', 'T') + 'Z');
    }

    return new Date(value);
}
