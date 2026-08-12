import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
    interface Window {
        Pusher: typeof Pusher
        Echo: Echo<'reverb'>
    }
}

const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http'

/**
 * pusher-js upgrades to `wss` whenever the page itself is secure, whatever
 * `forceTLS` says. A plain-`ws` Reverb — such as Herd's shared service on 8080 —
 * is therefore unreachable from an HTTPS page, and every attempt fills the
 * console with failed connections. Skip rather than retry the impossible.
 */
const reachable = (): boolean =>
    scheme === 'https' || window.location.protocol !== 'https:'

/**
 * Guarded because this module is imported from app.ts, which Inertia's SSR
 * renderer also evaluates — and there is no `window` on the server. Without the
 * guard, Vite fails with "Failed to warm up Inertia SSR module graph: window is
 * not defined" and the dev server never starts.
 */
if (typeof window !== 'undefined') {
    window.Pusher = Pusher

    if (reachable()) {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        })
    } else {
        console.info(
            `[echo] Not connecting: this page is HTTPS but REVERB_SCHEME is "${scheme}", ` +
                'and pusher-js will not open a plain ws connection from a secure page. ' +
                'Serve Reverb over TLS, or browse over http, to enable broadcasting.',
        )
    }
}
