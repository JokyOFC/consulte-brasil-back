/** Remove tudo que não for dígito. */
export function digitsOnly(value: string): string {
    return value.replace(/\D/g, '');
}

/** Formata um valor em centavos (inteiro) como moeda BRL: 12345 -> "R$ 123,45". */
export function formatBRL(cents: number | null | undefined): string {
    const value = (cents ?? 0) / 100;

    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

/** Converte um texto digitado pelo usuário ("123,45" ou "R$ 1.234,56") em centavos. */
export function parseBRLToCents(value: string): number {
    const digits = digitsOnly(value);

    return digits === '' ? 0 : parseInt(digits, 10);
}

/** Máscara dinâmica CPF (11) ou CNPJ (14). */
export function formatDocument(value: string): string {
    const digits = digitsOnly(value).slice(0, 14);

    if (digits.length <= 11) {
        return digits
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

/**
 * Máscara de placa veicular (ABC1234 antiga / ABC1D23 Mercosul) ou chassi:
 * mantém apenas alfanumérico em maiúsculas, limitado aos 17 chars do chassi.
 */
export function formatPlate(value: string): string {
    return value.replace(/[^0-9a-zA-Z]/g, '').toUpperCase().slice(0, 17);
}

/** Placa no padrão antigo (ABC1234), Mercosul (ABC1D23) ou chassi (17 alfanuméricos). */
export function isValidPlateOrChassis(value: string): boolean {
    const clean = formatPlate(value);

    return /^[A-Z]{3}\d{4}$/.test(clean) || /^[A-Z]{3}\d[A-Z]\d{2}$/.test(clean) || /^[A-Z0-9]{17}$/.test(clean);
}

/** Máscara de CEP — 01310-100. */
export function formatCep(value: string): string {
    return digitsOnly(value).slice(0, 8).replace(/^(\d{5})(\d)/, '$1-$2');
}

/** Máscara de telefone brasileiro — (11) 99999-9999. */
export function formatPhone(value: string): string {
    const digits = digitsOnly(value).slice(0, 11);

    if (digits.length <= 10) {
        return digits
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    return digits
        .replace(/^(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
}
