import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, Car, KeyRound, ShieldCheck, User, Zap } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { dashboard, login, register } from '@/routes';

interface SharedAuth {
    auth: { user: { name?: string } | null };
    [key: string]: unknown;
}

export default function Welcome() {
    const { auth } = usePage<SharedAuth>().props;
    const authed = !!auth?.user;

    return (
        <>
            <Head title="Consulte Brasil — Consultas de dados via API">
                <meta
                    name="description"
                    content="Plataforma de consulta de dados oficiais do Brasil via API. CPF, CNPJ e mais, com saldo prepago."
                />
                <meta property="og:title" content="Consulte Brasil — Consultas de dados via API" />
                <meta
                    property="og:description"
                    content="Plataforma de consulta de dados oficiais do Brasil via API. CPF, CNPJ e mais, com saldo prepago."
                />
                <link rel="canonical" href="/" />
            </Head>

            <div className="min-h-svh bg-background text-foreground">
                <SiteHeader authed={authed} />
                <Hero authed={authed} />
                <Features />
                <HowItWorks />
                <Pricing />
                <CtaBanner />
                <SiteFooter />
            </div>
        </>
    );
}

function SiteHeader({ authed }: { authed: boolean }) {
    return (
        <header className="sticky top-0 z-30 border-b border-border/60 bg-background/80 backdrop-blur">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
                <Link href="/">
                    <BrandLogo />
                </Link>
                <nav className="hidden items-center gap-8 text-sm font-medium text-muted-foreground md:flex">
                    <a href="#recursos" className="hover:text-foreground">Recursos</a>
                    <a href="#como-funciona" className="hover:text-foreground">Como funciona</a>
                    <a href="#precos" className="hover:text-foreground">Preços</a>
                    <a href="/docs/api" className="hover:text-foreground">Documentação</a>
                </nav>
                <div className="flex items-center gap-3">
                    {authed ? (
                        <Link
                            href={dashboard()}
                            className="rounded-lg bg-brand-green px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                        >
                            Ir para o painel
                        </Link>
                    ) : (
                        <>
                            <Link href={login()} className="text-sm font-medium text-muted-foreground hover:text-foreground">
                                Entrar
                            </Link>
                            <Link
                                href={register()}
                                className="rounded-lg bg-brand-green px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                            >
                                Criar conta
                            </Link>
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}

function Hero({ authed }: { authed: boolean }) {
    return (
        <section className="relative overflow-hidden">
            <div
                className="pointer-events-none absolute inset-0 opacity-[0.08]"
                style={{
                    backgroundImage:
                        'radial-gradient(circle at 15% 20%, #009c3b 0, transparent 35%), radial-gradient(circle at 85% 15%, #ffdf00 0, transparent 35%), radial-gradient(circle at 60% 90%, #1b4db1 0, transparent 40%)',
                }}
            />
            <div className="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-20 md:grid-cols-2 md:py-28">
                <div className="space-y-6">
                    <span className="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
                        <span className="size-2 rounded-full bg-brand-green" /> API de dados do Brasil
                    </span>
                    <h1 className="text-4xl font-bold leading-tight tracking-tight md:text-5xl">
                        Consulte dados oficiais do{' '}
                        <span className="bg-gradient-to-r from-brand-green via-yellow-500 to-brand-blue bg-clip-text text-transparent">
                            Brasil
                        </span>{' '}
                        em segundos
                    </h1>
                    <p className="max-w-lg text-lg text-muted-foreground">
                        Integre nossa API ao seu sistema e consulte CPF, CNPJ e mais — pagando
                        apenas pelo que usar, com saldo prepago transparente.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Link
                            href={authed ? dashboard() : register()}
                            className="rounded-lg bg-brand-green px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                        >
                            {authed ? 'Acessar painel' : 'Criar conta grátis'}
                        </Link>
                        <a
                            href="/docs/api"
                            className="rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-muted"
                        >
                            Ver documentação
                        </a>
                    </div>
                </div>

                <div className="relative">
                    <div className="absolute -inset-4 rounded-3xl bg-gradient-to-br from-brand-green/20 via-brand-yellow/20 to-brand-blue/20 blur-2xl" />
                    <div className="relative rounded-2xl border border-border bg-card p-6 shadow-xl">
                        <div className="mb-3 flex items-center gap-2 text-xs text-muted-foreground">
                            <span className="size-3 rounded-full bg-red-400" />
                            <span className="size-3 rounded-full bg-yellow-400" />
                            <span className="size-3 rounded-full bg-green-400" />
                            <span className="ml-2">POST /api/v1/consult/cpf</span>
                        </div>
                        <pre className="overflow-x-auto rounded-lg bg-zinc-950 p-4 text-xs leading-relaxed text-zinc-100">
{`curl -X POST https://api.consultebrasil.com/api/v1/consult/cpf \\
  -H "Authorization: Bearer cb_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{"params": {"document": "111.444.777-35"}}'

# 200 OK
{
  "data": {
    "provider": "api_brasil",
    "credits_charged": 1,
    "data": { "name": "JOAO DA SILVA", "status": "REGULAR" }
  }
}`}
                        </pre>
                    </div>
                </div>
            </div>
        </section>
    );
}

function Features() {
    const items = [
        { icon: User, title: 'Consulta CPF', desc: 'Dados cadastrais de pessoa física com validação de dígito.' },
        { icon: Building2, title: 'Consulta CNPJ', desc: 'Situação cadastral, razão social e dados de empresas.' },
        { icon: Car, title: 'Veículos', desc: 'Em breve: consulta de dados veiculares por placa.' },
        { icon: Zap, title: 'Alta disponibilidade', desc: 'Roteamento com failover entre múltiplos provedores.' },
        { icon: KeyRound, title: 'Chaves de API', desc: 'Gere e revogue credenciais a qualquer momento.' },
        { icon: ShieldCheck, title: 'Conformidade LGPD', desc: 'Logs auditáveis e política de retenção de dados.' },
    ];

    return (
        <section id="recursos" className="border-t border-border/60 bg-muted/30 py-20">
            <div className="mx-auto max-w-6xl px-4">
                <SectionTitle eyebrow="Recursos" title="Tudo o que você precisa para consultar dados" />
                <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map(({ icon: Icon, title, desc }) => (
                        <div key={title} className="rounded-2xl border border-border bg-card p-6 transition hover:shadow-md">
                            <div className="mb-4 inline-flex size-11 items-center justify-center rounded-xl bg-brand-green/10 text-brand-green">
                                <Icon className="size-5" />
                            </div>
                            <h3 className="text-lg font-semibold">{title}</h3>
                            <p className="mt-1 text-sm text-muted-foreground">{desc}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function HowItWorks() {
    const steps = [
        { n: '01', title: 'Crie sua conta', desc: 'Cadastre seus dados e documento (CPF ou CNPJ) em minutos.' },
        { n: '02', title: 'Escolha um plano', desc: 'Receba saldo na carteira conforme o plano contratado.' },
        { n: '03', title: 'Integre via API', desc: 'Use sua chave Bearer e comece a consultar.' },
    ];

    return (
        <section id="como-funciona" className="py-20">
            <div className="mx-auto max-w-6xl px-4">
                <SectionTitle eyebrow="Como funciona" title="Comece a consultar em 3 passos" />
                <div className="mt-12 grid gap-6 md:grid-cols-3">
                    {steps.map((s) => (
                        <div key={s.n} className="relative rounded-2xl border border-border p-6">
                            <span className="text-4xl font-bold text-brand-green/30">{s.n}</span>
                            <h3 className="mt-2 text-lg font-semibold">{s.title}</h3>
                            <p className="mt-1 text-sm text-muted-foreground">{s.desc}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function Pricing() {
    const plans = [
        { name: 'Starter', price: 'R$ 49', credits: 'R$ 100 de saldo/mês', featured: false },
        { name: 'Growth', price: 'R$ 149', credits: 'R$ 500 de saldo/mês', featured: true },
        { name: 'Scale', price: 'R$ 499', credits: 'R$ 2.000 de saldo/mês', featured: false },
    ];

    return (
        <section id="precos" className="border-t border-border/60 bg-muted/30 py-20">
            <div className="mx-auto max-w-6xl px-4">
                <SectionTitle eyebrow="Preços" title="Planos para cada volume de consulta" />
                <div className="mt-12 grid gap-6 md:grid-cols-3">
                    {plans.map((p) => (
                        <div
                            key={p.name}
                            className={`relative rounded-2xl border bg-card p-8 ${
                                p.featured ? 'border-brand-green shadow-lg ring-1 ring-brand-green' : 'border-border'
                            }`}
                        >
                            {p.featured && (
                                <span className="absolute -top-3 left-8 rounded-full bg-brand-green px-3 py-1 text-xs font-semibold text-white">
                                    Mais popular
                                </span>
                            )}
                            <h3 className="text-lg font-semibold">{p.name}</h3>
                            <p className="mt-4 text-4xl font-bold">
                                {p.price}
                                <span className="text-base font-normal text-muted-foreground">/mês</span>
                            </p>
                            <p className="mt-2 text-sm text-muted-foreground">{p.credits}</p>
                            <Link
                                href={register()}
                                className={`mt-6 block rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition ${
                                    p.featured
                                        ? 'bg-brand-green text-white hover:opacity-90'
                                        : 'border border-border hover:bg-muted'
                                }`}
                            >
                                Começar agora
                            </Link>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function CtaBanner() {
    return (
        <section className="py-20">
            <div className="mx-auto max-w-6xl px-4">
                <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#007a2e] via-[#0a8f3c] to-[#012a7a] px-8 py-14 text-center text-white">
                    <h2 className="text-3xl font-bold">Pronto para integrar?</h2>
                    <p className="mx-auto mt-3 max-w-xl text-white/85">
                        Crie sua conta agora e receba sua chave de API para começar a consultar.
                    </p>
                    <Link
                        href={register()}
                        className="mt-6 inline-block rounded-lg bg-white px-6 py-3 text-sm font-semibold text-brand-green transition hover:bg-white/90"
                    >
                        Criar conta grátis
                    </Link>
                </div>
            </div>
        </section>
    );
}

function SiteFooter() {
    return (
        <footer className="border-t border-border/60 py-10">
            <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 text-sm text-muted-foreground md:flex-row">
                <BrandLogo />
                <p>© {new Date().getFullYear()} Consulte Brasil. Todos os direitos reservados.</p>
                <a href="/docs/api" className="hover:text-foreground">Documentação da API</a>
            </div>
        </footer>
    );
}

function SectionTitle({ eyebrow, title }: { eyebrow: string; title: string }) {
    return (
        <div className="mx-auto max-w-2xl text-center">
            <p className="text-sm font-semibold uppercase tracking-wider text-brand-green">{eyebrow}</p>
            <h2 className="mt-2 text-3xl font-bold tracking-tight">{title}</h2>
        </div>
    );
}
