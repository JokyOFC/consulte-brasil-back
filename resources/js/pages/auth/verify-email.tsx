import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verificação de e-mail" />

            {status === 'verification-link-sent' && (
                <div className="rounded-md border border-green-500/30 bg-green-500/10 px-4 py-2 text-center text-sm font-medium text-green-700 dark:text-green-300">
                    Enviamos um novo link de verificação para o e-mail informado no cadastro.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary" className="w-full">
                            {processing && <Spinner />}
                            Reenviar e-mail de verificação
                        </Button>

                        <TextLink href={logout()} className="mx-auto block text-sm">
                            Sair
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verifique seu e-mail',
    description: 'Confirme seu endereço clicando no link que acabamos de enviar.',
};
