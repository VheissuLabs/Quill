import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
    interface Window {
        Pusher: typeof Pusher
        Echo: Echo<'reverb'>
    }
}

/**
 * Guarded because this module is imported from app.ts, which Inertia's SSR
 * renderer also evaluates — and there is no `window` on the server. Without the
 * guard, Vite fails with "Failed to warm up Inertia SSR module graph: window is
 * not defined" and the dev server never starts.
 */
if (typeof window !== 'undefined') {
    window.Pusher = Pusher

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
    })
}
