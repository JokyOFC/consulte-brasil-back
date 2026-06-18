import { Download, ExternalLink, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { PdfAttachment } from '@/lib/consultation-attachments';
import { downloadBase64Pdf, openBase64PdfInNewTab } from '@/lib/consultation-attachments';

export function ConsultationAttachmentsPanel({ attachments }: { attachments: PdfAttachment[] }) {
    if (attachments.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3 border-b border-border/60 px-5 py-5 md:px-6">
            {attachments.map((attachment) => (
                <div
                    key={attachment.path}
                    className="flex flex-col gap-4 rounded-xl border border-brand-green/25 bg-brand-green/5 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="flex items-start gap-3">
                        <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-green/15 text-brand-green">
                            <FileText className="size-5" />
                        </span>
                        <div className="space-y-1">
                            <p className="font-semibold">{attachment.label}</p>
                            <p className="text-sm text-muted-foreground">
                                Documento oficial retornado pelo provedor. Use os botões ao lado para abrir ou salvar.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => openBase64PdfInNewTab(attachment.base64)}
                        >
                            <ExternalLink className="size-4" />
                            Visualizar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => downloadBase64Pdf(attachment.base64, attachment.filename)}
                        >
                            <Download className="size-4" />
                            Baixar PDF
                        </Button>
                    </div>
                </div>
            ))}
        </div>
    );
}
