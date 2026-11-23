import { createInertiaApp, http } from '@inertiajs/vue3';
import { echo as liveTransport } from '@inertiajs/vue3/echo';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PortalLayout from '@/layouts/portal/PortalLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { broadcastingEnabled } from '@/lib/broadcasting';
import { initializeFlashToast } from '@/lib/flashToast';
import { configureEcho, echo } from '@laravel/echo-vue';

if (broadcastingEnabled) {
    configureEcho({
        broadcaster: 'reverb',
    });

    /**
     * Inertia v3 dropped axios, so nothing stamps the socket id on a request and
     * broadcast()->toOthers() would exclude nobody. One interceptor covers the router,
     * useForm, Form and useHttp.
     */
    http.onRequest((config) => {
        try {
            const socketId = echo().socketId();

            if (socketId) {
                config.headers = {
                    ...config.headers,
                    'X-Socket-Id': socketId,
                };
            }
        } catch {
            // Echo has not finished connecting; the actor hears their own event and reloads once.
        }

        return config;
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Quill';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'invoices/Public':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('portal/'):
                return PortalLayout;
            case name.startsWith('settings/'):
            case name.startsWith('organizations/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
    live: broadcastingEnabled ? liveTransport() : undefined,
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
