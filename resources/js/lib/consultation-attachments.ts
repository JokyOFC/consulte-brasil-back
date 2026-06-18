import { isPlainObject, isPrimitive } from '@/lib/consultation-result';

export interface PdfAttachment {
    /** Caminho legível, ex.: "cac.comprovantePdfBase64" */
    path: string;
    label: string;
    base64: string;
    filename: string;
}

const PDF_BASE64_PREFIX = 'JVBERi';

const ATTACHMENT_KEY_PATTERN = /pdf|base64|comprovante|certificado|documento/i;

export function isBase64PdfValue(value: unknown): value is string {
    if (typeof value !== 'string') {
        return false;
    }

    const trimmed = value.trim();

    if (trimmed.length < 100) {
        return false;
    }

    const payload = trimmed.startsWith('data:') ? trimmed.split(',')[1] ?? '' : trimmed;

    return payload.startsWith(PDF_BASE64_PREFIX);
}

export function isAttachmentFieldKey(key: string): boolean {
    return ATTACHMENT_KEY_PATTERN.test(key);
}

export function findPdfAttachments(data: unknown, path: string[] = []): PdfAttachment[] {
    if (isBase64PdfValue(data)) {
        const field = path.at(-1) ?? 'documento';

        return [
            {
                path: path.join('.'),
                label: attachmentLabel(path),
                base64: data,
                filename: `${slugify(path.join('-')) || 'certificado'}.pdf`,
            },
        ];
    }

    if (Array.isArray(data)) {
        return data.flatMap((item, index) => findPdfAttachments(item, [...path, String(index)]));
    }

    if (!isPlainObject(data)) {
        return [];
    }

    return Object.entries(data).flatMap(([key, value]) => findPdfAttachments(value, [...path, key]));
}

export function sanitizeResultForDisplay(data: Record<string, unknown>): Record<string, unknown> {
    const out: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(data)) {
        if (isBase64PdfValue(value)) {
            continue;
        }

        if (Array.isArray(value)) {
            const sanitized = value
                .map((item) => (isPlainObject(item) ? sanitizeResultForDisplay(item) : item))
                .filter((item) => !isBase64PdfValue(item));

            if (sanitized.length > 0) {
                out[key] = sanitized;
            }
            continue;
        }

        if (isPlainObject(value)) {
            const nested = sanitizeResultForDisplay(value);
            if (Object.keys(nested).length > 0) {
                out[key] = nested;
            }
            continue;
        }

        if (!isEmptyDisplayValue(value)) {
            out[key] = value;
        }
    }

    return out;
}

export function decodeBase64Pdf(base64: string): Uint8Array {
    const payload = base64.trim().replace(/^data:application\/pdf;base64,/, '');
    const binary = atob(payload);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index++) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes;
}

export function downloadBase64Pdf(base64: string, filename: string): void {
    const bytes = decodeBase64Pdf(base64);
    const blob = new Blob([bytes], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = filename.endsWith('.pdf') ? filename : `${filename}.pdf`;
    anchor.click();

    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function openBase64PdfInNewTab(base64: string): void {
    const bytes = decodeBase64Pdf(base64);
    const blob = new Blob([bytes], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);

    window.open(url, '_blank', 'noopener,noreferrer');

    window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

function attachmentLabel(path: string[]): string {
    const joined = path.join(' ').toLowerCase();

    if (joined.includes('cac') || joined.includes('anteced')) {
        return 'Certificado de antecedentes criminais (PDF)';
    }

    if (joined.includes('comprovante')) {
        return 'Comprovante em PDF';
    }

    if (joined.includes('certidao') || joined.includes('certificado')) {
        return 'Certidão em PDF';
    }

    return 'Documento em PDF';
}

function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase();
}

function isEmptyDisplayValue(value: unknown): boolean {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    return isPrimitive(value) && String(value).trim() === '';
}
