import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, Eye, EyeOff, RefreshCw, Webhook } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageFlash } from '@/hooks/use-page-flash';

interface PageProps {
    webhook_url: string | null;
    webhook_secret: string | null;
    webhook_configured: boolean;
    account_missing?: boolean;
    [key: string]: unknown;
}

export default function ClientWebhookIndex() {
    usePageFlash();
    const { webhook_url, webhook_secret, webhook_configured, account_missing } = usePage<PageProps>().props;
    const form = useForm({ webhook_url: webhook_url ?? '' });

    useEffect(() => {
        form.setData('webhook_url', webhook_url ?? '');
    }, [webhook_url]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/client/webhook', { preserveScroll: true });
    };

    const remove = () => {
        if (!confirm('Remover a configuração do webhook? Consultas continuarão funcionando normalmente.')) {
            return;
        }

        router.put('/client/webhook', { webhook_url: null }, { preserveScroll: true });
    };

    const regenerate = () => {
        if (!confirm('Gerar um novo secret? Atualize seu endpoint para validar com o novo valor.')) {
            return;
        }

        router.post('/client/webhook/regenerate-secret', {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Webhook" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Webhook de consultas"
                    description="Receba notificações POST quando uma consulta for concluída (sucesso ou falha com reembolso)."
                />

                {account_missing && (
                    <Card className="gap-0 border-destructive/40 bg-destructive/5 py-0">
                        <CardContent className="p-5 text-sm text-muted-foreground">
                            Sua conta de usuário não está vinculada a um cliente. Entre com um usuário de cliente ou
                            cadastre uma conta pelo fluxo de registro.
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Webhook className="size-4" /> Configuração
                        </CardTitle>
                        <CardDescription>
                            Opcional. Sem URL configurada, nenhuma notificação é enviada e a API continua respondendo
                            normalmente. Ao salvar uma URL pela primeira vez, o secret é gerado automaticamente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="webhook-url">URL de destino</Label>
                                <Input
                                    id="webhook-url"
                                    type="url"
                                    value={form.data.webhook_url}
                                    onChange={(e) => form.setData('webhook_url', e.target.value)}
                                    placeholder="https://seu-sistema.com/webhooks/consulte"
                                    disabled={account_missing}
                                />
                                {form.errors.webhook_url && (
                                    <p className="text-sm text-destructive">{form.errors.webhook_url}</p>
                                )}
                            </div>

                            {webhook_secret && (
                                <div className="space-y-2">
                                    <Label htmlFor="webhook-secret">Secret (HMAC)</Label>
                                    <SecretField secret={webhook_secret} />
                                    <p className="text-xs text-muted-foreground">
                                        Use este valor para validar o header <code>X-Consulte-Signature</code> nos POSTs
                                        recebidos.
                                    </p>
                                </div>
                            )}

                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={form.processing || account_missing}>
                                    Salvar
                                </Button>
                                {webhook_configured && (
                                    <>
                                        <Button type="button" variant="outline" onClick={regenerate}>
                                            <RefreshCw /> Regenerar secret
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={remove}
                                        >
                                            Remover
                                        </Button>
                                    </>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Validação da assinatura</CardTitle>
                        <CardDescription>
                            Cada POST inclui o header <code className="text-xs">X-Consulte-Signature</code> no formato{' '}
                            <code className="text-xs">t=&#123;unix&#125;,v1=&#123;hmac&#125;</code>.
                            O conteúdo assinado é <code className="text-xs">&#123;timestamp&#125;.&#123;corpo_json&#125;</code>{' '}
                            com HMAC-SHA256. Rejeite timestamps com mais de 5 minutos de diferença.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        Evento: <code className="text-xs">consultation.completed</code> — campos{' '}
                        <code className="text-xs">consultation_id</code>, <code className="text-xs">status</code>,{' '}
                        <code className="text-xs">query_type</code>, <code className="text-xs">amount_charged</code>,{' '}
                        <code className="text-xs">from_cache</code>, <code className="text-xs">provider</code> e{' '}
                        <code className="text-xs">data</code> (sucesso) ou <code className="text-xs">error</code> (falha).
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function SecretField({ secret }: { secret: string }) {
    const [copied, setCopied] = useState(false);
    const [visible, setVisible] = useState(true);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(secret);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            /* clipboard indisponível */
        }
    };

    return (
        <div className="flex items-center gap-2">
            <Input
                id="webhook-secret"
                readOnly
                type={visible ? 'text' : 'password'}
                value={secret}
                className="font-mono text-xs"
            />
            <Button type="button" variant="outline" size="icon" onClick={() => setVisible((v) => !v)}>
                {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={copy}>
                {copied ? <Check className="text-brand-green" /> : <Copy className="size-4" />}
                {copied ? 'Copiado' : 'Copiar'}
            </Button>
        </div>
    );
}

ClientWebhookIndex.layout = {
    breadcrumbs: [{ title: 'Webhook', href: '/client/webhook' }],
};
