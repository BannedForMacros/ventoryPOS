import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Conexión de tiempo real (Laravel Reverb), una por pestaña y perezosa: se
 * abre recién cuando una pantalla la pide. Sin VITE_REVERB_APP_KEY (entornos
 * sin Reverb) devuelve null y todo funciona como antes, sin tiempo real.
 */
let echo: Echo<'reverb'> | null | undefined;

export function getEcho(): Echo<'reverb'> | null {
    if (echo !== undefined) return echo;

    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;
    if (!key || typeof window === 'undefined') return (echo = null);

    (window as any).Pusher = Pusher;
    const scheme = (import.meta.env.VITE_REVERB_SCHEME as string) ?? 'https';
    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: (import.meta.env.VITE_REVERB_HOST as string) || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return echo;
}
