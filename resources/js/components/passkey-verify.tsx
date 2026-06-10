import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
    /** 'before' coloca o separador acima do botão (uso secundário, ex.: após o formulário). */
    separatorPosition?: 'before' | 'after';
    variant?: 'default' | 'subtle';
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
    separatorPosition = 'after',
    variant = 'default',
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/dashboard');
        },
    });

    if (!isSupported) {
        return null;
    }

    const separatorEl = (
        <div className="relative my-5">
            <div className="absolute inset-0 flex items-center">
                <Separator className="w-full" />
            </div>
            <div className="relative flex justify-center text-[11px] tracking-wide text-muted-foreground uppercase">
                <span className="bg-background px-3">
                    {separator ?? 'Or continue with email'}
                </span>
            </div>
        </div>
    );

    const buttonEl = (
        <div className="grid gap-2">
            <Button
                type="button"
                variant={variant === 'subtle' ? 'ghost' : 'outline'}
                className={
                    variant === 'subtle'
                        ? 'h-9 w-full text-sm text-muted-foreground hover:text-foreground'
                        : 'w-full'
                }
                onClick={verify}
                disabled={isLoading}
            >
                {isLoading ? <Spinner /> : <KeyRound className="size-4" />}
                {isLoading
                    ? (loadingLabel ?? 'Authenticating...')
                    : (label ?? 'Sign in with a passkey')}
            </Button>
            {error && <InputError message={error} className="text-center" />}
        </div>
    );

    return (
        <>
            {separatorPosition === 'before' && separatorEl}
            {buttonEl}
            {separatorPosition === 'after' && separatorEl}
        </>
    );
}
