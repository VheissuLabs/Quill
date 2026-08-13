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
 * pusher-js upgrades to `wss` whenever the page is secure, whatever `forceTLS` says,
 * so a plain-`ws` Reverb is unreachable from HTTPS. Skip rather than retry it.
 */
const reachable = (): boolean =>
    scheme === 'https' || window.location.protocol !== 'https:'

/**
 * Guarded because app.ts imports this and Inertia's SSR renderer evaluates it, where
 * there is no `window`. Unguarded, Vite fails to warm the SSR graph and never starts.
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
