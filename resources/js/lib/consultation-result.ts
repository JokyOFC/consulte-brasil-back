const FIELD_LABELS: Record<string, string> = {
    name: 'Nome',
    birth_date: 'Data de nascimento',
    status: 'Situação',
    mother_name: 'Nome da mãe',
    corporate_name: 'Razão social',
    trade_name: 'Nome fantasia',
    opened_at: 'Data de abertura',
    dadosCadastrais: 'Dados cadastrais',
    participacaoEmEmpresas: 'Participação em empresas',
    historicoConsultas: 'Histórico de consultas',
    resumoConsultasPerfilFinanceiro: 'Resumo do perfil financeiro',
    resumoRetorno: 'Resumo da consulta',
    faixaDeRendaPresumida: 'Faixa de renda presumida',
    pontuacaoCompromissos: 'Pontuação de compromissos',
    pendenciasInternas: 'Pendências internas',
    titulosProtestos: 'Títulos protestados',
    acoesCiveis: 'Ações cíveis',
    chequesSemFundo: 'Cheques sem fundo',
    contratosRecentes: 'Contratos recentes',
    score: 'Score de crédito',
    nome: 'Nome',
    cpf: 'CPF',
    cnpj: 'CNPJ',
    dataNascimento: 'Data de nascimento',
    nomeMae: 'Nome da mãe',
    sexo: 'Sexo',
    estadoCivil: 'Estado civil',
    grauInstrucao: 'Grau de instrução',
    situacaoCadastral: 'Situação cadastral',
    endereco: 'Endereço',
    logradouro: 'Logradouro',
    numero: 'Número',
    bairro: 'Bairro',
    cidade: 'Cidade',
    uf: 'UF',
    cep: 'CEP',
    documento: 'Documento',
    dataConsulta: 'Data da consulta',
    protocolo: 'Protocolo',
    tempoExecucao: 'Tempo de execução',
    codigoConsulta: 'Código da consulta',
    cliente: 'Cliente',
    razaoSocial: 'Razão social',
    nomeFantasia: 'Nome fantasia',
    cargo: 'Cargo',
    percentualParticipacao: 'Participação (%)',
    dataEntrada: 'Data de entrada',
    nota: 'Nota',
    origem: 'Origem',
    texto: 'Descrição',
    mensagem: 'Mensagem',
    probabilidade: 'Probabilidade',
    quantidade: 'Quantidade',
    mesAno: 'Mês/Ano',
    total: 'Total',
};

const SECTION_PRIORITY: Record<string, number> = {
    resumoretono: 0,
    resumoretorno: 0,
    resumoRetorno: 0,
    dadoscadastrais: 1,
    dadosCadastrais: 1,
    score: 2,
};

export function splitConsultationResult(data: Record<string, unknown>): {
    display: Record<string, unknown>;
    raw: unknown;
} {
    const { raw, ...rest } = data;

    return { display: rest, raw };
}

export function humanizeFieldKey(key: string): string {
    if (FIELD_LABELS[key]) {
        return FIELD_LABELS[key];
    }

    const spaced = key
        .replace(/_/g, ' ')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
        .trim();

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

export function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function isPrimitive(value: unknown): value is string | number | boolean | null {
    return value === null || ['string', 'number', 'boolean'].includes(typeof value);
}

export function formatResultPrimitive(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Sim' : 'Não';
    }

    if (typeof value === 'number') {
        return Number.isInteger(value) ? String(value) : value.toLocaleString('pt-BR');
    }

    const text = String(value).trim();

    if (text.length > 400) {
        return text;
    }

    return text;
}

export function sortResultEntries(entries: [string, unknown][]): [string, unknown][] {
    return [...entries].sort(([leftKey], [rightKey]) => {
        const leftPriority = SECTION_PRIORITY[leftKey] ?? SECTION_PRIORITY[leftKey.toLowerCase()] ?? 50;
        const rightPriority = SECTION_PRIORITY[rightKey] ?? SECTION_PRIORITY[rightKey.toLowerCase()] ?? 50;

        if (leftPriority !== rightPriority) {
            return leftPriority - rightPriority;
        }

        return humanizeFieldKey(leftKey).localeCompare(humanizeFieldKey(rightKey), 'pt-BR');
    });
}

export function isScoreObject(value: unknown): value is Record<string, unknown> {
    if (!isPlainObject(value)) {
        return false;
    }

    return typeof value.score === 'number' || typeof value.pontuacao === 'number';
}

export function getScoreValue(value: Record<string, unknown>): number | null {
    if (typeof value.score === 'number') {
        return value.score;
    }

    if (typeof value.pontuacao === 'number') {
        return value.pontuacao;
    }

    return null;
}

export function getScoreDescription(value: Record<string, unknown>): string | null {
    for (const key of ['texto', 'mensagem', 'descricao', 'message']) {
        const candidate = value[key];
        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate.trim();
        }
    }

    return null;
}

export function tableColumns(rows: Record<string, unknown>[]): string[] {
    const keys = new Set<string>();

    for (const row of rows.slice(0, 20)) {
        for (const key of Object.keys(row)) {
            if (!isPlainObject(row[key]) && !Array.isArray(row[key])) {
                keys.add(key);
            }
        }
    }

    return Array.from(keys).slice(0, 6);
}

export function isFlatPrimitiveRecord(value: Record<string, unknown>): boolean {
    return Object.values(value).every((entry) => isPrimitive(entry));
}

export function shouldRenderInlineObject(value: Record<string, unknown>): boolean {
    return isFlatPrimitiveRecord(value) && Object.keys(value).length <= 8;
}
