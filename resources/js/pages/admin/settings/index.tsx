import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageFlash } from '@/hooks/use-page-flash';

interface PageProps {
    settings: {
        session_timeout_minutes: number;
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
            </div>
        </>
    );
}

AdminSettingsIndex.layout = {
    breadcrumbs: [
        { title: 'Administração', href: '/admin' },
        { title: 'Configurações', href: '/admin/settings' },
    ],
};
