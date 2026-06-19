import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, RefreshCw, Webhook } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageFlash } from '@/hooks/use-page-flash';

interface PageProps {
    webhook_url: string | null;
    webhook_configured: boolean;
    flash?: { plain_secret?: string };
    [key: string]: unknown;
}

export default function ClientWebhookIndex() {
    usePageFlash();
    const { webhook_url, webhook_configured, flash } = usePage<PageProps>().props;
    const form = useForm({ webhook_url: webhook_url ?? '' });

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

                {flash?.plain_secret && <SecretReveal secret={flash.plain_secret} />}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Webhook className="size-4" /> Configuração
                        </CardTitle>
                        <CardDescription>
                            Opcional. Sem URL configurada, nenhuma notificação é enviada e a API continua respondendo normalmente.
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
                                />
                                {form.errors.webhook_url && (
                                    <p className="text-sm text-destructive">{form.errors.webhook_url}</p>
                                )}
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={form.processing}>
                                    Salvar
                                </Button>
                                {webhook_configured && (
                                    <>
                                        <Button type="button" variant="outline" onClick={regenerate}>
                                            <RefreshCw /> Regenerar secret
                                        </Button>
                                        <Button type="button" variant="ghost" className="text-destructive" onClick={remove}>
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
                            O conteúdo assinado é <code className="text-xs">&#123;timestamp&#125;.&#123;corpo_json&#125;</code> com HMAC-SHA256.
                            Rejeite timestamps com mais de 5 minutos de diferença.
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

function SecretReveal({ secret }: { secret: string }) {
    const [copied, setCopied] = useState(false);

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
        <Card className="gap-0 border-brand-green/40 bg-brand-green/5 py-0">
            <CardContent className="space-y-3 p-5">
                <div className="flex items-center gap-2 text-sm font-medium text-brand-green">
                    <Webhook className="size-4" /> Secret gerado — copie agora, não exibiremos novamente.
                </div>
                <div className="flex items-center gap-2">
                    <code className="flex-1 overflow-x-auto rounded-md border border-border bg-background px-3 py-2 font-mono text-xs">
                        {secret}
                    </code>
                    <Button variant="outline" size="sm" onClick={copy}>
                        {copied ? <Check className="text-brand-green" /> : <Copy />}
                        {copied ? 'Copiado' : 'Copiar'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

ClientWebhookIndex.layout = {
    breadcrumbs: [{ title: 'Webhook', href: '/client/webhook' }],
};
