import { useLaravelReactI18n } from 'laravel-react-i18n';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type WardrobeTag = {
    id: string;
    tag_group_id: string;
    name: string;
};

type WardrobeTagGroup = {
    id: string;
    name: string;
    tags: WardrobeTag[];
};

export default function WardrobeTagsIndex({
    tagGroups,
}: {
    tagGroups: WardrobeTagGroup[];
}) {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <h1 className="sr-only">{t('Tags')}</h1>

            {tagGroups.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No tag groups yet')}
                </p>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {tagGroups.map((tagGroup) => (
                        <Card
                            key={tagGroup.id}
                            className="gap-4 rounded-md border-sidebar-border/70 py-4 shadow-xs dark:border-sidebar-border"
                        >
                            <CardHeader className="px-4">
                                <CardTitle className="truncate text-sm leading-5">
                                    {tagGroup.name}
                                </CardTitle>
                            </CardHeader>

                            <CardContent className="px-4">
                                {tagGroup.tags.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('No tags in this group yet')}
                                    </p>
                                ) : (
                                    <div className="flex flex-wrap gap-2">
                                        {tagGroup.tags.map((tag) => (
                                            <Badge
                                                key={tag.id}
                                                variant="outline"
                                                className="max-w-full truncate"
                                            >
                                                {tag.name}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
