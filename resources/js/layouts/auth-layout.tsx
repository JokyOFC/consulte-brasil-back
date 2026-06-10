import { Link } from '@inertiajs/react';
import { ShieldCheck, Zap } from 'lucide-react';
import type { ReactNode } from 'react';
import { BrandLogo, BrandMark } from '@/components/brand-logo';
import { home } from '@/routes';

/**
 * Layout branded de autenticação — painel esquerdo com o gradiente da
 * bandeira (verde → amarelo → azul) e a marca; à direita, o formulário.
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            {/* Painel da marca */}
            <div className="relative hidden flex-col justify-between overflow-hidden p-10 text-white lg:flex">
                <div className="absolute inset-0 bg-gradient-to-br from-[#007a2e] via-[#0a8f3c] to-[#012a7a]" />
                <div
                    className="absolute inset-0 opacity-20"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 30% 20%, #ffdf00 0, transparent 40%), radial-gradient(circle at 80% 70%, #1b4db1 0, transparent 45%)',
                    }}
                />

                <Link href={home()} className="relative z-10 flex items-center gap-3">
                    <BrandMark className="size-10 drop-shadow" />
                    <span className="text-xl font-semibold tracking-tight">Consulte Brasil</span>
                </Link>

                <div className="relative z-10 space-y-8">
                    <div className="space-y-4">
                        <h2 className="max-w-md text-3xl font-semibold leading-tight">
                            Dados oficiais do Brasil, via API, sob demanda.
                        </h2>
                        <p className="max-w-sm text-sm leading-relaxed text-white/80">
                            Integre consultas de CPF, CNPJ e mais ao seu sistema com créditos
                            transparentes e chaves de API seguras.
                        </p>
                    </div>

                    <ul className="space-y-3 text-sm text-white/90">
                        <li className="flex items-center gap-2">
                            <Dot /> Consultas de CPF, CNPJ e mais
                        </li>
                        <li className="flex items-center gap-2">
                            <Dot /> Pague por uso com sistema de créditos
                        </li>
                        <li className="flex items-center gap-2">
                            <Dot /> Integração simples com chave de API
                        </li>
                    </ul>

                    <div className="flex flex-wrap gap-3">
                        <Badge icon={ShieldCheck} label="LGPD" />
                        <Badge icon={Zap} label="Alta disponibilidade" />
                    </div>
                </div>

                <p className="relative z-10 text-xs text-white/70">
                    © {new Date().getFullYear()} Consulte Brasil. Todos os direitos reservados.
                </p>
            </div>

            {/* Formulário */}
            <div className="flex flex-col justify-center bg-background px-6 py-12 sm:px-12 lg:px-20">
                <div className="mx-auto w-full max-w-[340px]">
                    <Link href={home()} className="mb-10 flex lg:hidden">
                        <BrandLogo />
                    </Link>

                    {(title || description) && (
                        <header className="mb-9 space-y-1.5">
                            {title && (
                                <h1 className="text-[1.65rem] font-semibold tracking-tight text-foreground">
                                    {title}
                                </h1>
                            )}
                            {description && (
                                <p className="text-[0.9rem] leading-relaxed text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </header>
                    )}

                    {children}
                </div>
            </div>
        </div>
    );
}

function Dot() {
    return <span className="inline-block size-1.5 rounded-full bg-brand-yellow" />;
}

function Badge({ icon: Icon, label }: { icon: typeof ShieldCheck; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-white/90 backdrop-blur-sm">
            <Icon className="size-3.5" />
            {label}
        </span>
    );
}
