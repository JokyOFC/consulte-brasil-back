import { Head, useForm } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    FileText,
    Mail,
    Phone,
    Shield,
    User,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import IconInput from '@/components/icon-input';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDocument, formatPhone } from '@/lib/format';
import { cn } from '@/lib/utils';
import { login } from '@/routes';

type Props = {
    passwordRules: string;
};

type FieldName =
    | 'name'
    | 'document'
    | 'email'
    | 'phone'
    | 'password'
    | 'password_confirmation'
    | 'terms';

const STEPS: {
    title: string;
    subtitle: string;
    icon: LucideIcon;
    fields: FieldName[];
}[] = [
    {
        title: 'Identificação',
        subtitle: 'Informe seu nome e documento (CPF ou CNPJ).',
        icon: User,
        fields: ['name', 'document'],
    },
    {
        title: 'Contato',
        subtitle: 'Como podemos falar com você.',
        icon: Mail,
        fields: ['email', 'phone'],
    },
    {
        title: 'Segurança',
        subtitle: 'Crie uma senha forte para proteger sua conta.',
        icon: Shield,
        fields: ['password', 'password_confirmation', 'terms'],
    },
];

export default function Register({ passwordRules }: Props) {
    const [step, setStep] = useState(0);
    const isLast = step === STEPS.length - 1;

    const form = useForm({
        name: '',
        document: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const passwordsMatch =
        form.data.password_confirmation !== '' &&
        form.data.password === form.data.password_confirmation;

    const canProceed = (() => {
        const d = form.data;

        if (step === 0) {
            return d.name.trim() !== '' && d.document.trim() !== '';
        }

        if (step === 1) {
            return d.email.trim() !== '' && d.phone.trim() !== '';
        }

        return (
            d.password !== '' &&
            d.password_confirmation !== '' &&
            passwordsMatch &&
            d.terms
        );
    })();

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!isLast) {
            if (canProceed) {
                setStep((s) => s + 1);
            }

            return;
        }

        form.transform((data) => ({ ...data, terms: data.terms ? 1 : 0 }));
        form.post('/register', {
            onError: (errors) => {
                const firstError = Object.keys(errors)[0] as FieldName | undefined;

                if (firstError) {
                    const target = STEPS.findIndex((s) => s.fields.includes(firstError));

                    if (target >= 0) {
                        setStep(target);
                    }
                }
            },
        });
    };

    return (
        <div className="space-y-6">
            <Head title="Criar conta" />

            <Stepper current={step} />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <div key={step} className="flex flex-col gap-5">
                    {step === 0 && (
                        <>
                            <Field label="Nome completo / Razão social" error={form.errors.name}>
                                <IconInput
                                    icon={User}
                                    autoFocus
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Seu nome ou empresa"
                                    autoComplete="name"
                                />
                            </Field>
                            <Field label="CPF ou CNPJ" error={form.errors.document}>
                                <IconInput
                                    icon={FileText}
                                    value={form.data.document}
                                    onChange={(e) =>
                                        form.setData('document', formatDocument(e.target.value))
                                    }
                                    placeholder="000.000.000-00"
                                    inputMode="numeric"
                                />
                            </Field>
                        </>
                    )}

                    {step === 1 && (
                        <>
                            <Field label="E-mail" error={form.errors.email}>
                                <IconInput
                                    icon={Mail}
                                    autoFocus
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    placeholder="voce@empresa.com.br"
                                    autoComplete="email"
                                />
                            </Field>
                            <Field label="Telefone" error={form.errors.phone}>
                                <IconInput
                                    icon={Phone}
                                    type="tel"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', formatPhone(e.target.value))
                                    }
                                    placeholder="(11) 99999-9999"
                                    autoComplete="tel"
                                />
                            </Field>
                        </>
                    )}

                    {step === 2 && (
                        <>
                            <Field label="Senha" error={form.errors.password}>
                                <PasswordInput
                                    autoFocus
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                    placeholder="Crie uma senha forte"
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                            </Field>
                            <Field label="Confirme a senha" error={form.errors.password_confirmation}>
                                <PasswordInput
                                    value={form.data.password_confirmation}
                                    onChange={(e) =>
                                        form.setData('password_confirmation', e.target.value)
                                    }
                                    placeholder="Repita a senha"
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                                {form.data.password_confirmation !== '' && (
                                    <p
                                        className={cn(
                                            'flex items-center gap-1.5 text-sm',
                                            passwordsMatch
                                                ? 'text-brand-green'
                                                : 'text-destructive',
                                        )}
                                    >
                                        {passwordsMatch ? (
                                            <>
                                                <Check className="size-3.5" /> Senhas coincidem
                                            </>
                                        ) : (
                                            'As senhas não coincidem.'
                                        )}
                                    </p>
                                )}
                            </Field>
                            <div className="flex items-start gap-3 rounded-lg border border-border/60 bg-muted/40 p-3">
                                <Checkbox
                                    id="terms"
                                    checked={form.data.terms}
                                    onCheckedChange={(checked) =>
                                        form.setData('terms', checked === true)
                                    }
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor="terms"
                                    className="cursor-pointer text-sm leading-snug font-normal text-muted-foreground"
                                >
                                    Li e aceito os{' '}
                                    <span className="text-foreground underline-offset-2 hover:underline">
                                        termos de uso
                                    </span>{' '}
                                    e a{' '}
                                    <span className="text-foreground underline-offset-2 hover:underline">
                                        política de privacidade
                                    </span>{' '}
                                    (LGPD).
                                </Label>
                            </div>
                            <InputError message={form.errors.terms} />
                        </>
                    )}
                </div>

                <div className="flex items-center gap-3">
                    {step > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep((s) => s - 1)}
                        >
                            <ChevronLeft /> Voltar
                        </Button>
                    )}
                    <Button
                        type="submit"
                        className="flex-1"
                        size="lg"
                        disabled={!canProceed || form.processing}
                    >
                        {form.processing && <Spinner />}
                        {isLast ? 'Criar conta' : 'Continuar'}
                        {!isLast && <ChevronRight />}
                    </Button>
                </div>
            </form>

            <p className="border-t border-border/60 pt-5 text-center text-sm text-muted-foreground">
                Já tem uma conta?{' '}
                <TextLink href={login()}>Entrar</TextLink>
            </p>
        </div>
    );
}

function Stepper({ current }: { current: number }) {
    const trackFill =
        STEPS.length > 1 ? (current / (STEPS.length - 1)) * 100 : 0;

    return (
        <div className="space-y-4">
            <div className="relative">
                {/* Trilha entre o 1º e o 3º passo (centros das colunas do grid) */}
                <div className="absolute top-4 left-1/6 right-1/6 h-0.5 bg-border" />
                <div
                    className="absolute top-4 left-1/6 h-0.5 bg-brand-green transition-all duration-300"
                    style={{ width: `calc(66.666% * ${trackFill / 100})` }}
                />

                <div className="relative grid grid-cols-3">
                    {STEPS.map((s, i) => (
                        <div key={s.title} className="flex flex-col items-center gap-2">
                            <div
                                className={cn(
                                    'relative z-10 flex size-8 items-center justify-center rounded-full transition-all duration-300',
                                    i < current && 'bg-brand-green text-white',
                                    i === current &&
                                        'bg-brand-green text-white ring-4 ring-brand-green/20',
                                    i > current && 'bg-muted text-muted-foreground',
                                )}
                            >
                                {i < current ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    <s.icon className="size-3.5" />
                                )}
                            </div>
                            <span
                                className={cn(
                                    'max-w-[5.5rem] text-center text-[10px] leading-tight font-medium sm:max-w-none sm:text-[11px]',
                                    i === current ? 'text-foreground' : 'text-muted-foreground',
                                )}
                            >
                                {s.title}
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            <div className="space-y-1 text-center">
                <p className="text-xs font-medium text-muted-foreground">
                    Passo {current + 1} de {STEPS.length}
                </p>
                <p className="text-sm text-muted-foreground">{STEPS[current].subtitle}</p>
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

Register.layout = {
    title: 'Crie sua conta',
    description: 'Leva menos de um minuto.',
};
