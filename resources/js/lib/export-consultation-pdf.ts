import type { jsPDF } from 'jspdf';
import {
    formatResultPrimitive,
    getScoreDescription,
    getScoreValue,
    humanizeFieldKey,
    isPlainObject,
    isPrimitive,
    isScoreObject,
    sortResultEntries,
    splitConsultationResult,
    tableColumns,
} from '@/lib/consultation-result';
import { formatBRL, formatDocument } from '@/lib/format';

interface ExportConsultationPdfInput {
    consultationId: string;
    queryTypeName: string;
    queryTypeCode: string;
    amountCharged: number;
    fromCache: boolean;
    data: Record<string, unknown>;
}

type PdfBlock =
    | { kind: 'section'; title: string }
    | { kind: 'fields'; rows: [string, string][] }
    | { kind: 'table'; columns: string[]; rows: string[][] }
    | { kind: 'score'; value: number; description: string | null; extras: [string, string][] }
    | { kind: 'note'; text: string };

const SKIP_KEYS = new Set([
    'raw',
    'providerid',
    'prioridade',
    'provedorreserva',
    'statusretorno',
    'pdf',
    'pdfbase64',
    'linkgcs',
    'linkgcs',
]);

const PROFILE_KEYS = new Set(['nome', 'name', 'cpf', 'cnpj', 'documento', 'endereco']);

function shouldSkipKey(key: string): boolean {
    return SKIP_KEYS.has(key.toLowerCase().replace(/_/g, ''));
}

function isEmptyValue(value: unknown): boolean {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    if (Array.isArray(value) && value.length === 0) {
        return true;
    }

    const formatted = formatResultPrimitive(value);

    return formatted === '—';
}

function formatFieldValue(key: string, value: unknown): string {
    if (typeof value === 'string') {
        const digits = value.replace(/\D/g, '');

        if (['cpf', 'documento', 'document'].includes(key.toLowerCase()) && digits.length === 11) {
            return formatDocument(digits);
        }

        if (key.toLowerCase() === 'cnpj' && digits.length === 14) {
            return formatDocument(digits);
        }
    }

    return formatResultPrimitive(value);
}

function collectFieldRows(data: Record<string, unknown>): [string, string][] {
    const rows: [string, string][] = [];

    for (const [key, value] of sortResultEntries(Object.entries(data))) {
        if (shouldSkipKey(key) || isEmptyValue(value)) {
            continue;
        }

        if (isPrimitive(value)) {
            rows.push([humanizeFieldKey(key), formatFieldValue(key, value)]);
        }
    }

    return rows;
}

function unwrapRoot(data: Record<string, unknown>): Record<string, unknown> {
    let current = data;

    for (let depth = 0; depth < 3; depth++) {
        const entries = Object.entries(current).filter(([key]) => !shouldSkipKey(key));

        if (entries.length !== 1 || !isPlainObject(entries[0][1])) {
            break;
        }

        current = entries[0][1];
    }

    return current;
}

function buildBlocks(data: Record<string, unknown>): PdfBlock[] {
    const blocks: PdfBlock[] = [];
    const topEntries = sortResultEntries(Object.entries(data).filter(([key]) => !shouldSkipKey(key)));

    for (const [sectionKey, sectionValue] of topEntries) {
        if (isEmptyValue(sectionValue)) {
            continue;
        }

        const sectionTitle = humanizeFieldKey(sectionKey);

        if (isPrimitive(sectionValue)) {
            blocks.push({ kind: 'section', title: 'Informações gerais' });
            blocks.push({ kind: 'fields', rows: [[sectionTitle, formatFieldValue(sectionKey, sectionValue)]] });
            continue;
        }

        if (Array.isArray(sectionValue)) {
            blocks.push(...buildArrayBlock(sectionTitle, sectionValue));
            continue;
        }

        if (!isPlainObject(sectionValue)) {
            continue;
        }

        blocks.push(...buildObjectSection(sectionTitle, sectionValue));
    }

    return blocks;
}

function buildObjectSection(title: string, data: Record<string, unknown>): PdfBlock[] {
    const unwrapped = unwrapRoot(data);
    const entries = sortResultEntries(Object.entries(unwrapped).filter(([key]) => !shouldSkipKey(key)));
    const blocks: PdfBlock[] = [];

    const directFields = collectFieldRows(unwrapped);
    const nestedBlocks: PdfBlock[] = [];

    for (const [key, value] of entries) {
        if (isEmptyValue(value) || isPrimitive(value)) {
            continue;
        }

        if (key.toLowerCase() === 'dadoscadastrais' || (isPlainObject(value) && isProfileObject(value))) {
            nestedBlocks.push(...buildProfileBlock(humanizeFieldKey(key), value as Record<string, unknown>));
            continue;
        }

        if (isScoreObject(value)) {
            nestedBlocks.push(...buildScoreBlock(value));
            continue;
        }

        if (Array.isArray(value)) {
            nestedBlocks.push(...buildArrayBlock(humanizeFieldKey(key), value));
            continue;
        }

        if (isPlainObject(value)) {
            if (isFlatDisplayObject(value)) {
                const rows = collectFieldRows(value);
                if (rows.length > 0) {
                    nestedBlocks.push({ kind: 'section', title: humanizeFieldKey(key) });
                    nestedBlocks.push({ kind: 'fields', rows });
                }
            } else {
                nestedBlocks.push(...buildObjectSection(humanizeFieldKey(key), value));
            }
        }
    }

    if (nestedBlocks.length === 0 && directFields.length > 0) {
        blocks.push({ kind: 'section', title });
        blocks.push({ kind: 'fields', rows: directFields });
        return blocks;
    }

    if (nestedBlocks.length > 0) {
        if (title !== humanizeFieldKey(Object.keys(data)[0] ?? title)) {
            blocks.push({ kind: 'section', title });
        }
        blocks.push(...nestedBlocks);
    } else if (directFields.length > 0) {
        blocks.push({ kind: 'section', title });
        blocks.push({ kind: 'fields', rows: directFields });
    }

    return blocks;
}

function isProfileObject(data: Record<string, unknown>): boolean {
    return typeof data.nome === 'string' || typeof data.name === 'string';
}

function isFlatDisplayObject(data: Record<string, unknown>): boolean {
    return Object.values(data).every((value) => isPrimitive(value) || isEmptyValue(value));
}

function buildProfileBlock(title: string, data: Record<string, unknown>): PdfBlock[] {
    const name = [data.nome, data.name].find((value) => typeof value === 'string') as string | undefined;
    const document = [data.cpf, data.cnpj, data.documento].find((value) => typeof value === 'string') as string | undefined;

    const addressParts = isPlainObject(data.endereco)
        ? [data.endereco.logradouro, data.endereco.numero, data.endereco.bairro, data.endereco.cidade, data.endereco.uf, data.endereco.cep]
              .filter((part) => !isEmptyValue(part))
              .map(String)
        : [];

    const rows: [string, string][] = [];

    if (name) {
        rows.push(['Nome', name]);
    }

    if (document) {
        const docKey = typeof data.cpf === 'string' ? 'cpf' : typeof data.cnpj === 'string' ? 'cnpj' : 'documento';
        rows.push([humanizeFieldKey(docKey), formatFieldValue(docKey, document)]);
    }

    if (addressParts.length > 0) {
        rows.push(['Endereço', addressParts.join(', ')]);
    }

    for (const [key, value] of sortResultEntries(Object.entries(data))) {
        if (PROFILE_KEYS.has(key.toLowerCase()) || shouldSkipKey(key) || isEmptyValue(value) || !isPrimitive(value)) {
            continue;
        }

        rows.push([humanizeFieldKey(key), formatFieldValue(key, value)]);
    }

    return [
        { kind: 'section', title },
        { kind: 'fields', rows },
    ];
}

function buildScoreBlock(data: Record<string, unknown>): PdfBlock[] {
    const value = getScoreValue(data);
    const description = getScoreDescription(data);
    const extras: [string, string][] = [];

    for (const [key, entry] of Object.entries(data)) {
        if (['score', 'pontuacao', 'texto', 'mensagem', 'descricao', 'message'].includes(key)) {
            continue;
        }

        if (!isEmptyValue(entry) && isPrimitive(entry)) {
            extras.push([humanizeFieldKey(key), formatFieldValue(key, entry)]);
        }
    }

    if (value === null && !description && extras.length === 0) {
        return [];
    }

    return [
        { kind: 'section', title: 'Score de crédito' },
        { kind: 'score', value: value ?? 0, description, extras },
    ];
}

function buildArrayBlock(title: string, value: unknown[]): PdfBlock[] {
    if (value.length === 0) {
        return [];
    }

    if (value.every((item) => isPlainObject(item))) {
        const objects = value as Record<string, unknown>[];
        const columns = tableColumns(objects);

        if (columns.length === 0) {
            return [];
        }

        const rows = objects.map((row) =>
            columns.map((column) => formatFieldValue(column, row[column])),
        );

        return [
            { kind: 'section', title },
            { kind: 'table', columns: columns.map((column) => humanizeFieldKey(column)), rows },
        ];
    }

    if (value.every((item) => isPrimitive(item))) {
        return [
            { kind: 'section', title },
            { kind: 'fields', rows: [[title, value.map((item) => formatResultPrimitive(item)).join(', ')]] },
        ];
    }

    return [
        { kind: 'section', title },
        { kind: 'note', text: 'Registros disponíveis apenas em formato estruturado no painel web.' },
    ];
}

function drawHeader(doc: jsPDF, input: ExportConsultationPdfInput, generatedAt: string): number {
    const pageWidth = doc.internal.pageSize.getWidth();

    doc.setFillColor(0, 156, 59);
    doc.rect(0, 0, pageWidth, 32, 'F');

    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(18);
    doc.text('Consulte Brasil', 14, 14);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text('Comprovante de consulta', 14, 21);

    doc.setTextColor(40, 40, 40);
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text('Detalhes da consulta', 14, 42);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);

    const metaRows: [string, string][] = [
        ['Serviço', input.queryTypeName],
        ['Código', input.queryTypeCode],
        ['ID', input.consultationId],
        ['Valor debitado', formatBRL(input.amountCharged)],
        ['Gerado em', generatedAt],
    ];

    if (input.fromCache) {
        metaRows.push(['Origem', 'Resposta do cache']);
    }

    let y = 48;
    for (const [label, value] of metaRows) {
        doc.setFont('helvetica', 'bold');
        doc.text(`${label}:`, 14, y);
        doc.setFont('helvetica', 'normal');
        doc.text(doc.splitTextToSize(value, pageWidth - 58), 42, y);
        y += value.length > 70 ? 10 : 6;
    }

    doc.setDrawColor(220, 220, 220);
    doc.line(14, y + 2, pageWidth - 14, y + 2);

    return y + 10;
}

function ensureSpace(doc: jsPDF, y: number, needed: number): number {
    const pageHeight = doc.internal.pageSize.getHeight();

    if (y + needed > pageHeight - 16) {
        doc.addPage();
        return 18;
    }

    return y;
}

function renderBlocks(
    doc: jsPDF,
    autoTable: (doc: jsPDF, options: Record<string, unknown>) => void,
    blocks: PdfBlock[],
    startY: number,
): number {
    let y = startY;
    const pageWidth = doc.internal.pageSize.getWidth();

    for (const block of blocks) {
        if (block.kind === 'section') {
            y = ensureSpace(doc, y, 12);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            doc.setTextColor(0, 120, 50);
            doc.text(block.title, 14, y);
            doc.setTextColor(40, 40, 40);
            y += 6;
            continue;
        }

        if (block.kind === 'score') {
            y = ensureSpace(doc, y, 28);
            doc.setFillColor(240, 250, 244);
            doc.roundedRect(14, y - 4, pageWidth - 28, 24, 2, 2, 'F');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(22);
            doc.setTextColor(0, 140, 60);
            doc.text(String(block.value), 18, y + 10);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(80, 80, 80);
            if (block.description) {
                doc.text(doc.splitTextToSize(block.description, pageWidth - 52), 40, y + 8);
            }
            y += 28;

            if (block.extras.length > 0) {
                autoTable(doc, {
                    startY: y,
                    body: block.extras,
                    theme: 'plain',
                    styles: { fontSize: 9, cellPadding: 2 },
                    columnStyles: {
                        0: { cellWidth: 48, fontStyle: 'bold', textColor: [100, 100, 100] },
                        1: { cellWidth: pageWidth - 28 - 48 },
                    },
                    margin: { left: 14, right: 14 },
                });
                y = (doc as { lastAutoTable?: { finalY: number } }).lastAutoTable?.finalY ?? y + 8;
                y += 4;
            }
            continue;
        }

        if (block.kind === 'fields' && block.rows.length > 0) {
            autoTable(doc, {
                startY: y,
                body: block.rows,
                theme: 'striped',
                styles: {
                    fontSize: 9,
                    cellPadding: 3,
                    overflow: 'linebreak',
                    lineColor: [230, 230, 230],
                    lineWidth: 0.1,
                },
                alternateRowStyles: { fillColor: [248, 250, 252] },
                columnStyles: {
                    0: { cellWidth: 52, fontStyle: 'bold', textColor: [60, 60, 60] },
                    1: { cellWidth: pageWidth - 28 - 52 },
                },
                margin: { left: 14, right: 14 },
            });
            y = (doc as { lastAutoTable?: { finalY: number } }).lastAutoTable?.finalY ?? y + 8;
            y += 6;
            continue;
        }

        if (block.kind === 'table' && block.rows.length > 0) {
            autoTable(doc, {
                startY: y,
                head: [block.columns],
                body: block.rows,
                styles: {
                    fontSize: 8,
                    cellPadding: 2.5,
                    overflow: 'linebreak',
                },
                headStyles: {
                    fillColor: [0, 156, 59],
                    textColor: 255,
                    fontStyle: 'bold',
                },
                margin: { left: 14, right: 14 },
            });
            y = (doc as { lastAutoTable?: { finalY: number } }).lastAutoTable?.finalY ?? y + 8;
            y += 6;
            continue;
        }

        if (block.kind === 'note') {
            y = ensureSpace(doc, y, 10);
            doc.setFont('helvetica', 'italic');
            doc.setFontSize(9);
            doc.setTextColor(120, 120, 120);
            doc.text(doc.splitTextToSize(block.text, pageWidth - 28), 14, y);
            doc.setTextColor(40, 40, 40);
            y += 8;
        }
    }

    return y;
}

export async function exportConsultationPdf(input: ExportConsultationPdfInput): Promise<void> {
    const [{ jsPDF }, autoTableModule] = await Promise.all([
        import('jspdf'),
        import('jspdf-autotable'),
    ]);

    const autoTable = autoTableModule.autoTable ?? autoTableModule.default;
    autoTableModule.applyPlugin(jsPDF);

    const { display } = splitConsultationResult(input.data);
    const blocks = buildBlocks(display);
    const generatedAt = new Date().toLocaleString('pt-BR');

    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    const startY = drawHeader(doc, input, generatedAt);
    const finalY = renderBlocks(doc, autoTable, blocks, startY);

    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const footerY = Math.min(finalY + 8, pageHeight - 12);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(130, 130, 130);
    doc.text(
        'Documento gerado automaticamente pelo painel Consulte Brasil. Uso restrito ao titular da conta.',
        pageWidth / 2,
        footerY,
        { align: 'center', maxWidth: pageWidth - 28 },
    );

    const safeName = input.queryTypeCode.replace(/[^a-z0-9_-]+/gi, '-').toLowerCase();
    doc.save(`consulta-${safeName}-${input.consultationId.slice(0, 8)}.pdf`);
}
