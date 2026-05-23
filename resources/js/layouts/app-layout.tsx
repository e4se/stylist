import { Head, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useEffect } from 'react';

import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { Auth, BreadcrumbItem } from '@/types';

type PageProps = {
    auth?: Auth;
    breadcrumbs?: BreadcrumbItem[];
};

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { auth, breadcrumbs: sharedBreadcrumbs } = usePage<PageProps>().props;
    const { currentLocale, setLocale } = useLaravelReactI18n();
    const resolvedBreadcrumbs = sharedBreadcrumbs ?? breadcrumbs;
    const currentBreadcrumb =
        resolvedBreadcrumbs.find((breadcrumb) => breadcrumb.current) ??
        resolvedBreadcrumbs.at(-1);

    useEffect(() => {
        const locale = auth?.user?.locale;

        if (locale && currentLocale() !== locale) {
            setLocale(locale);
        }
    }, [auth?.user?.locale, currentLocale, setLocale]);

    return (
        <>
            {currentBreadcrumb && <Head title={currentBreadcrumb.title} />}
            <AppLayoutTemplate breadcrumbs={resolvedBreadcrumbs}>
                {children}
            </AppLayoutTemplate>
        </>
    );
}
