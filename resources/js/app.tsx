import { createInertiaApp } from '@inertiajs/react';
import { LaravelReactI18nProvider } from 'laravel-react-i18n';
import { StrictMode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';

import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { Auth } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const fallbackLocale = import.meta.env.VITE_APP_FALLBACK_LOCALE || 'en';
const translationFiles = import.meta.glob('/lang/*.json', { eager: true });

type SharedPageProps = {
    auth?: Auth;
    [key: string]: unknown;
};

createInertiaApp<SharedPageProps>({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    setup({ el, App, props }) {
        const initialLocale =
            props.initialPage.props.auth?.user?.locale ?? fallbackLocale;
        const app = (
            <StrictMode>
                <TooltipProvider delayDuration={0}>
                    <LaravelReactI18nProvider
                        locale={initialLocale}
                        fallbackLocale={fallbackLocale}
                        files={translationFiles}
                    >
                        <App {...props} />
                    </LaravelReactI18nProvider>
                    <Toaster />
                </TooltipProvider>
            </StrictMode>
        );

        if (!el) {
            return app;
        }

        if (el.hasAttribute('data-server-rendered')) {
            hydrateRoot(el, app);

            return;
        }

        createRoot(el).render(app);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
