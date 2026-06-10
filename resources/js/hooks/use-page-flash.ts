import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

interface FlashProps {
    flash?: { success?: string; error?: string };
    errors?: Record<string, string>;
    [key: string]: unknown;
}

/**
 * Notifica o usuário com toasts a partir de:
 *  - flash de sessão (back()->with('success'|'error'))
 *  - erros de validação (422) — mostra a primeira mensagem.
 *
 * Os erros de validação continuam aparecendo inline nos campos; o toast
 * é um reforço para o usuário não perder o feedback.
 */
export function usePageFlash(): void {
    const { flash, errors } = usePage<FlashProps>().props;
    const firstError = errors ? Object.values(errors)[0] : undefined;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        if (firstError) {
            toast.error(firstError);
        }
    }, [firstError]);
}
