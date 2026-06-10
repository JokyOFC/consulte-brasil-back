import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { logout } from '@/routes';

interface PageProps {
    auth?: {
        user?: unknown;
        sessionTimeoutMinutes?: number | null;
    };
    [key: string]: unknown;
}

const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'] as const;

/**
 * Desloga o usuário no cliente após um período de inatividade configurável pelo
 * admin (auth.sessionTimeoutMinutes). É um complemento de UX ao middleware do
 * servidor, que é a fonte autoritativa. Atualiza o timer a cada interação.
 */
export function useSessionTimeout(): void {
    const { auth } = usePage<PageProps>().props;
    const minutes = auth?.sessionTimeoutMinutes ?? null;
    const isAuthenticated = Boolean(auth?.user);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const lastResetRef = useRef<number>(0);

    useEffect(() => {
        if (!isAuthenticated || !minutes || minutes <= 0) {
            return;
        }

        const timeoutMs = minutes * 60_000;

        const expire = () => {
            router.post(
                logout().url,
                {},
                { onFinish: () => router.visit('/login') },
            );
        };

        const arm = () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
            timerRef.current = setTimeout(expire, timeoutMs);
        };

        // Throttle: só re-arma o timer no máximo uma vez por segundo.
        const onActivity = () => {
            const now = Date.now();
            if (now - lastResetRef.current < 1_000) {
                return;
            }
            lastResetRef.current = now;
            arm();
        };

        arm();
        ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, onActivity, { passive: true }));

        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
            ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, onActivity));
        };
    }, [isAuthenticated, minutes]);
}
