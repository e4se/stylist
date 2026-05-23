import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth, Locale } from '@/types';

type PageProps = {
    auth: Auth;
};

const localeOptions = [
    { value: 'en', label: 'English' },
    { value: 'ru', label: 'Русский' },
] satisfies Array<{ value: Locale; label: string }>;

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useLaravelReactI18n();

    return (
        <>
            <Head title={t('Profile settings')} />

            <h1 className="sr-only">{t('Profile settings')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Profile')}
                    description={t(
                        'Update your name, email address, and language',
                    )}
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t('Full name')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder={t('Email address')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="locale">{t('Language')}</Label>

                                <Select
                                    name="locale"
                                    defaultValue={auth.user.locale}
                                    required
                                >
                                    <SelectTrigger
                                        id="locale"
                                        className="mt-1 w-full"
                                        aria-invalid={Boolean(errors.locale)}
                                    >
                                        <SelectValue
                                            placeholder={t('Language')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {localeOptions.map((locale) => (
                                            <SelectItem
                                                key={locale.value}
                                                value={locale.value}
                                            >
                                                {locale.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Choose the language used for this account',
                                    )}
                                </p>

                                <InputError
                                    className="mt-2"
                                    message={errors.locale}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            {t(
                                                'Your email address is unverified.',
                                            )}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                {t(
                                                    'Click here to re-send the verification email.',
                                                )}
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                {t(
                                                    'A new verification link has been sent to your email address.',
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {t('Save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
