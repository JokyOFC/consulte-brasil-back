import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageFlash } from '@/hooks/use-page-flash';

interface PageProps {
    settings: {
        session_timeout_minutes: number;
        client_email_verification_enabled: boolean;
        client_two_factor_enabled: boolean;
    };
    limits: {
        session_timeout_min: number;
        session_timeout_max: number;
    };
    [key: string]: unknown;
}

export default function AdminSettingsIndex() {
    usePageFlash();
    const { settings, limits } = usePage<PageProps>().props;

    const form = useForm({
        session_timeout_minutes: String(settings.session_timeout_minutes),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            session_timeout_minutes: Number(d.session_timeout_minutes),
        }));
        form.put('/admin/settings', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Configurações" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Configurações"
                    description="Parâmetros gerais do sistema."
                />

                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle>Tempo de sessão online</CardTitle>
                        <CardDescription>
                            Tempo de inatividade (em minutos) até o logout automático dos usuários
                            logados. Entre {limits.session_timeout_min} e {limits.session_timeout_max} minutos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="session_timeout_minutes">Minutos de inatividade</Label>
                                <Input
                                    id="session_timeout_minutes"
                                    type="number"
                                    min={limits.session_timeout_min}
                                    max={limits.session_timeout_max}
                                    value={form.data.session_timeout_minutes}
                                    onChange={(e) => form.setData('session_timeout_minutes', e.target.value)}
                                    required
                                />
                                {form.errors.session_timeout_minutes && (
                                    <p className="text-sm text-destructive">{form.errors.session_timeout_minutes}</p>
                                )}
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                Salvar
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <ClientAccessCard
                    emailVerificationEnabled={settings.client_email_verification_enabled}
                    twoFactorEnabled={settings.client_two_factor_enabled}
                />
            </div>
        </>
    );
}

function ClientAccessCard({
    emailVerificationEnabled,
    twoFactorEnabled,
}: {
    emailVerificationEnabled: boolean;
    twoFactorEnabled: boolean;
}) {
    const form = useForm({
        client_email_verification_enabled: emailVerificationEnabled,
        client_two_factor_enabled: twoFactorEnabled,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/admin/settings', { preserveScroll: true });
    };

    return (
        <Card className="max-w-xl">
            <CardHeader>
                <CardTitle>Acesso dos clientes</CardTitle>
                <CardDescription>
                    Regras para o cliente criar conta e entrar no painel. Administradores
                    continuam sempre com confirmação de email e 2FA.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-5">
                    <SettingToggle
                        id="client_email_verification_enabled"
                        label="Exigir confirmação de email"
                        description="Ligado: o cliente recebe o email de confirmação ao se cadastrar e só acessa o painel após confirmar. Desligado: o acesso é liberado direto, sem email de confirmação."
                        checked={form.data.client_email_verification_enabled}
                        onChange={(value) => form.setData('client_email_verification_enabled', value)}
                        error={form.errors.client_email_verification_enabled}
                    />
                    <SettingToggle
                        id="client_two_factor_enabled"
                        label="Autenticação em dois fatores (2FA)"
                        description="Ligado: o cliente pode ativar o 2FA e o código é pedido no login. Desligado: nenhum cliente passa pelo desafio de 2FA (mesmo quem já tinha ativado) e a opção some das configurações de segurança."
                        checked={form.data.client_two_factor_enabled}
                        onChange={(value) => form.setData('client_two_factor_enabled', value)}
                        error={form.errors.client_two_factor_enabled}
                    />
                    <Button type="submit" disabled={form.processing}>
                        Salvar
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function SettingToggle({
    id,
    label,
    description,
    checked,
    onChange,
    error,
}: {
    id: string;
    label: string;
    description: string;
    checked: boolean;
    onChange: (value: boolean) => void;
    error?: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
                className="mt-0.5"
            />
            <div className="space-y-1">
                <Label htmlFor={id}>{label}</Label>
                <p className="text-sm text-muted-foreground">{description}</p>
                {error && <p className="text-sm text-destructive">{error}</p>}
            </div>
        </div>
    );
}

AdminSettingsIndex.layout = {
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Configurações', href: '/admin/settings' },
    ],
};
