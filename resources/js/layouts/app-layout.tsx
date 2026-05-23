import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useEffect } from 'react';

import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { Auth, BreadcrumbItem } from '@/types';

type PageProps = {
    auth?: Auth;
};

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { auth } = usePage<PageProps>().props;
    const { currentLocale, setLocale } = useLaravelReactI18n();

    useEffect(() => {
        const locale = auth?.user?.locale;

        if (locale && currentLocale() !== locale) {
            setLocale(locale);
        }
    }, [auth?.user?.locale, currentLocale, setLocale]);

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
