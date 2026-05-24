import { Form, InfiniteScroll } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ImageIcon, Pencil, Plus, Shirt, Trash2 } from 'lucide-react';
import { useState } from 'react';

import ItemController from '@/actions/App/Http/Controllers/Wardrobe/ItemController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';

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

type WardrobeItemFormData = {
    name: string;
    description: string;
    main_upload: File | null;
};

type WardrobeItemFormErrors = Partial<
    Record<keyof WardrobeItemFormData, string>
>;

type UploadProgress = {
    percentage?: number;
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
            <div className="flex justify-end">
                <h1 className="sr-only">{t('Wardrobe')}</h1>
                <CreateWardrobeItemDialog />
            </div>

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
                <EditWardrobeItemDialog item={item} />
                <DeleteWardrobeItemDialog item={item} />
            </CardFooter>
        </Card>
    );
}

function CreateWardrobeItemDialog() {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="size-4" />
                    {t('Add item')}
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('Create wardrobe item')}</DialogTitle>
                    <DialogDescription>
                        {t('Add item details and an optional main image.')}
                    </DialogDescription>
                </DialogHeader>

                <WardrobeItemForm onSuccess={() => setOpen(false)} />
            </DialogContent>
        </Dialog>
    );
}

function EditWardrobeItemDialog({ item }: { item: WardrobeItem }) {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={t('Edit :name', { name: item.name })}
                >
                    <Pencil className="size-4" />
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('Edit wardrobe item')}</DialogTitle>
                    <DialogDescription>
                        {t('Update item details or replace the main image.')}
                    </DialogDescription>
                </DialogHeader>

                <WardrobeItemForm
                    item={item}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function WardrobeItemForm({
    item,
    onSuccess,
}: {
    item?: WardrobeItem;
    onSuccess: () => void;
}) {
    const { t } = useLaravelReactI18n();
    const isEditing = item !== undefined;
    const form = isEditing
        ? ItemController.update.form.put(item.id)
        : ItemController.store.form.post();

    return (
        <Form<WardrobeItemFormData>
            {...form}
            options={{
                preserveScroll: true,
            }}
            onSuccess={onSuccess}
            resetOnSuccess={isEditing ? ['main_upload'] : true}
            disableWhileProcessing
            className="space-y-5"
        >
            {({ errors, processing, progress, resetAndClearErrors }) => (
                <>
                    <WardrobeItemFormFields
                        item={item}
                        errors={errors}
                        disabled={processing}
                    />

                    <WardrobeUploadProgress progress={progress} />

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={processing}
                                onClick={() => resetAndClearErrors()}
                            >
                                {t('Cancel')}
                            </Button>
                        </DialogClose>

                        <Button type="submit" disabled={processing}>
                            {processing
                                ? isEditing
                                    ? t('Saving...')
                                    : t('Creating...')
                                : isEditing
                                  ? t('Save')
                                  : t('Create item')}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

function WardrobeItemFormFields({
    item,
    errors,
    disabled,
}: {
    item?: WardrobeItem;
    errors: WardrobeItemFormErrors;
    disabled: boolean;
}) {
    const { t } = useLaravelReactI18n();
    const fieldIdPrefix = item ? `wardrobe-item-${item.id}` : 'wardrobe-item';
    const nameErrorId = `${fieldIdPrefix}-name-error`;
    const descriptionErrorId = `${fieldIdPrefix}-description-error`;
    const mainUploadHelpId = `${fieldIdPrefix}-main-upload-help`;
    const mainUploadErrorId = `${fieldIdPrefix}-main-upload-error`;

    return (
        <div className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor={`${fieldIdPrefix}-name`}>{t('Name')}</Label>

                <Input
                    id={`${fieldIdPrefix}-name`}
                    type="text"
                    name="name"
                    defaultValue={item?.name ?? ''}
                    required
                    autoFocus
                    autoComplete="off"
                    placeholder={t('e.g., Black linen jacket')}
                    disabled={disabled}
                    aria-invalid={Boolean(errors.name)}
                    aria-describedby={errors.name ? nameErrorId : undefined}
                />

                <InputError id={nameErrorId} message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${fieldIdPrefix}-description`}>
                    {t('Description')}
                </Label>

                <Textarea
                    id={`${fieldIdPrefix}-description`}
                    name="description"
                    defaultValue={item?.description ?? ''}
                    rows={4}
                    placeholder={t('Notes about fit, material, or styling')}
                    disabled={disabled}
                    aria-invalid={Boolean(errors.description)}
                    aria-describedby={
                        errors.description ? descriptionErrorId : undefined
                    }
                />

                <InputError
                    id={descriptionErrorId}
                    message={errors.description}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${fieldIdPrefix}-main-upload`}>
                    {t('Main image')}
                </Label>

                <Input
                    id={`${fieldIdPrefix}-main-upload`}
                    type="file"
                    name="main_upload"
                    accept="image/*"
                    disabled={disabled}
                    aria-invalid={Boolean(errors.main_upload)}
                    aria-describedby={
                        errors.main_upload
                            ? `${mainUploadHelpId} ${mainUploadErrorId}`
                            : mainUploadHelpId
                    }
                />

                <p
                    id={mainUploadHelpId}
                    className="text-sm text-muted-foreground"
                >
                    {t('Optional image up to 10 MB.')}
                </p>

                <InputError
                    id={mainUploadErrorId}
                    message={errors.main_upload}
                />
            </div>
        </div>
    );
}

function WardrobeUploadProgress({
    progress,
}: {
    progress: UploadProgress | null;
}) {
    const { t } = useLaravelReactI18n();

    if (progress === null) {
        return null;
    }

    const percentage = Math.round(progress.percentage ?? 0);
    const progressText = `${percentage}%`;

    return (
        <div className="space-y-2" role="status" aria-live="polite">
            <div className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                <span>{t('Uploading image')}</span>
                <span>{progressText}</span>
            </div>
            <progress
                value={percentage}
                max="100"
                className="h-2 w-full overflow-hidden rounded-full"
                aria-label={t('Main image upload progress')}
            >
                {progressText}
            </progress>
        </div>
    );
}

function DeleteWardrobeItemDialog({ item }: { item: WardrobeItem }) {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
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
                <DialogHeader>
                    <DialogTitle>{t('Delete wardrobe item')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Are you sure you want to delete ":name" from your wardrobe?',
                            { name: item.name },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...ItemController.destroy.form.delete(item.id)}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => setOpen(false)}
                    disableWhileProcessing
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    {t('Cancel')}
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing
                                    ? t('Deleting...')
                                    : t('Delete item')}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
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
