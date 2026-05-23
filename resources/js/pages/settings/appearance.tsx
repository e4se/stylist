import { useLaravelReactI18n } from 'laravel-react-i18n';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';

export default function Appearance() {
    const { t } = useLaravelReactI18n();

    return (
        <>
            <h1 className="sr-only">{t('Appearance settings')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Appearance settings')}
                    description={t(
                        'Update the appearance settings for your account',
                    )}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}
