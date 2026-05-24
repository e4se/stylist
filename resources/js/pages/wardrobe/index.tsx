import { Form, InfiniteScroll, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ImageIcon, Pencil, Shirt, Trash2 } from 'lucide-react';

import ItemController from '@/actions/App/Http/Controllers/Wardrobe/ItemController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Skeleton } from '@/components/ui/skeleton';
import { index as wardrobeIndex } from '@/routes/wardrobe';

type WardrobeUpload = {
    id: string;
    name: string;
    url: string;
};

type WardrobeItem = {
    id: string;
    name: string;
    description: string | null;
    main_upload: WardrobeUpload[];
};

type PaginatedWardrobeItems = {
    data: WardrobeItem[];
};

const loadingSkeletonKeys = ['first', 'second', 'third', 'fourth'] as const;

export default function WardrobeIndex({
    items,
}: {
    items: PaginatedWardrobeItems;
}) {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <h1 className="sr-only">{t('Wardrobe')}</h1>

            {items.data.length === 0 ? (
                <WardrobeEmptyState />
            ) : (
                <InfiniteScroll
                    data="items"
                    onlyNext
                    className="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
                    loading={() => (
                        <WardrobeGridLoading
                            label={t('Loading wardrobe items')}
                        />
                    )}
                >
                    {items.data.map((item) => (
                        <WardrobeItemCard key={item.id} item={item} />
                    ))}
                </InfiniteScroll>
            )}
        </div>
    );
}

function WardrobeItemCard({ item }: { item: WardrobeItem }) {
    const { t } = useLaravelReactI18n();
    const mainUpload = item.main_upload[0];

    return (
        <Card className="gap-0 overflow-hidden rounded-md border-sidebar-border/70 p-0 shadow-xs transition-shadow hover:shadow-sm dark:border-sidebar-border">
            <div className="relative aspect-[4/5] overflow-hidden bg-muted">
                {mainUpload ? (
                    <img
                        src={mainUpload.url}
                        alt={item.name}
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <WardrobeImagePlaceholder
                        label={t('No image for :name', { name: item.name })}
                    />
                )}
            </div>

            <CardContent className="flex flex-1 flex-col gap-2 p-4">
                <CardTitle className="truncate text-sm leading-5">
                    {item.name}
                </CardTitle>

                {item.description && (
                    <p className="line-clamp-2 min-h-10 text-sm leading-5 text-muted-foreground">
                        {item.description}
                    </p>
                )}
            </CardContent>

            <CardFooter className="justify-end gap-1 border-t border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border">
                <Button variant="ghost" size="icon" className="size-8" asChild>
                    <Link
                        href={wardrobeIndex({
                            query: {
                                edit: item.id,
                            },
                        })}
                        preserveScroll
                        aria-label={t('Edit :name', { name: item.name })}
                    >
                        <Pencil className="size-4" />
                    </Link>
                </Button>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            aria-label={t('Delete :name', {
                                name: item.name,
                            })}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </DialogTrigger>

                    <DialogContent>
                        <DialogTitle>{t('Delete wardrobe item')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Are you sure you want to delete ":name" from your wardrobe?',
                                { name: item.name },
                            )}
                        </DialogDescription>

                        <Form
                            {...ItemController.destroy.form.delete(item.id)}
                            options={{
                                preserveScroll: true,
                            }}
                        >
                            {({ processing }) => (
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            {t('Cancel')}
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                        asChild
                                    >
                                        <button type="submit">
                                            {processing
                                                ? t('Deleting...')
                                                : t('Delete item')}
                                        </button>
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardFooter>
        </Card>
    );
}

function WardrobeImagePlaceholder({ label }: { label: string }) {
    return (
        <div
            role="img"
            aria-label={label}
            className="absolute inset-0 flex items-center justify-center"
        >
            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/15 dark:stroke-neutral-100/15" />
            <div className="relative flex size-14 items-center justify-center rounded-full border border-border/70 bg-background/85 text-muted-foreground shadow-xs backdrop-blur-sm dark:bg-background/70">
                <ImageIcon className="size-6" />
            </div>
        </div>
    );
}

function WardrobeEmptyState() {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex min-h-96 flex-1 items-center justify-center rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-6 text-center dark:border-sidebar-border dark:bg-muted/10">
            <div className="flex max-w-sm flex-col items-center gap-3">
                <div className="flex size-12 items-center justify-center rounded-full border border-border bg-background text-muted-foreground shadow-xs">
                    <Shirt className="size-5" />
                </div>
                <div className="space-y-1">
                    <h2 className="text-base font-medium">
                        {t('No wardrobe items yet')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Add clothing items to start building your wardrobe.',
                        )}
                    </p>
                </div>
            </div>
        </div>
    );
}

function WardrobeGridLoading({ label }: { label: string }) {
    return (
        <div role="status" aria-live="polite" className="mt-4 space-y-3">
            <p className="text-center text-sm text-muted-foreground">{label}</p>
            <div className="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                {loadingSkeletonKeys.map((skeletonKey) => (
                    <Card
                        key={skeletonKey}
                        className="gap-0 overflow-hidden rounded-md border-sidebar-border/70 p-0 shadow-xs dark:border-sidebar-border"
                    >
                        <Skeleton className="aspect-[4/5] rounded-none" />
                        <div className="space-y-2 p-4">
                            <Skeleton className="h-4 w-2/3" />
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-3/4" />
                        </div>
                    </Card>
                ))}
            </div>
        </div>
    );
}
