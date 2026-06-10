import { cn } from '@/lib/utils';

const MARK = '/images/consulte-brasil-globo-logo.png'; // só o globo
const LOCKUP = '/images/consulte-brasil-logo-closer.png'; // globo + "CONSULTE BRASIL"

/**
 * Marca (somente o globo). Dimensione via className (ex.: size-8).
 */
export function BrandMark({ className }: { className?: string }) {
    return (
        <img
            src={MARK}
            alt="Consulte Brasil"
            className={cn('select-none object-contain', className)}
            draggable={false}
        />
    );
}

/**
 * Logo completo (globo + texto). Use em cabeçalhos/rodapés sobre fundo
 * claro. Em fundo escuro, prefira <BrandMark /> + texto branco.
 * showName=false renderiza apenas o globo.
 */
export function BrandLogo({
    className,
    showName = true,
}: {
    className?: string;
    showName?: boolean;
}) {
    if (!showName) {
        return <BrandMark className={cn('size-8', className)} />;
    }

    return (
        <img
            src={LOCKUP}
            alt="Consulte Brasil"
            className={cn('h-8 w-auto select-none object-contain', className)}
            draggable={false}
        />
    );
}
