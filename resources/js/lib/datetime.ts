/** Fuso de exibição para o usuário (Brasil). Deve coincidir com APP_DISPLAY_TIMEZONE no backend. */
export const APP_TIMEZONE = 'America/Sao_Paulo';

const BR_DATETIME: Intl.DateTimeFormatOptions = {
    timeZone: APP_TIMEZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
};

const BR_DATE: Intl.DateTimeFormatOptions = {
    timeZone: APP_TIMEZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
};

/**
 * Formata data/hora no fuso de Brasília.
 * Aceita ISO 8601 (com ou sem offset) ou datetime MySQL UTC.
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
    // MySQL UTC: "2026-06-23 02:37:28"
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)) {
        return new Date(value.replace(' ', 'T') + 'Z');
    }

    // ISO sem offset (ex.: "2026-06-23T02:37:28") → tratar como UTC, não como local do browser
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?$/.test(value)) {
        return new Date(`${value}Z`);
    }

    return new Date(value);
}
