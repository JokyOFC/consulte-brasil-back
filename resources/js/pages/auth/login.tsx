import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, Lock, Mail } from 'lucide-react';
import IconInput from '@/components/icon-input';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const inputClass = 'h-10 shadow-none';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            {status && (
                <div className="mb-6 flex items-start gap-2.5 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                    <span>{status}</span>
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="email">E-mail</Label>
                            <IconInput
                                id="email"
                                icon={Mail}
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="voce@empresa.com.br"
                                inputClassName={inputClass}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="password">Senha</Label>
                            <div className="relative">
                                <Lock
                                    className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden
                                />
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Sua senha"
                                    className={`pl-9 ${inputClass}`}
                                />
                            </div>
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center justify-between gap-4">
                            <div className="flex items-center gap-2.5">
                                <Checkbox id="remember" name="remember" tabIndex={3} />
                                <Label htmlFor="remember" className="text-sm font-normal">
                                    Manter conectado
                                </Label>
                            </div>
                            {canResetPassword && (
                                <TextLink href={request()} className="shrink-0 text-sm" tabIndex={5}>
                                    Esqueceu a senha?
                                </TextLink>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="mt-1 h-10 w-full"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Entrar
                        </Button>
                    </>
                )}
            </Form>

            <PasskeyVerify
                label="Entrar com passkey"
                loadingLabel="Autenticando…"
                separator="ou"
                separatorPosition="before"
                variant="subtle"
            />

            <p className="mt-8 text-center text-sm text-muted-foreground">
                Ainda não tem conta?{' '}
                <TextLink href={register()} tabIndex={6}>
                    Criar conta grátis
                </TextLink>
            </p>
        </>
    );
}

Login.layout = {
    title: 'Acesse sua conta',
    description: 'Entre com seu e-mail e senha para continuar.',
};
