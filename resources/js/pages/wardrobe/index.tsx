import { Form, InfiniteScroll, router, useHttp } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CheckCircle2,
    Filter,
    ImageIcon,
    ImagePlus,
    Loader2,
    Pencil,
    Plus,
    Search,
    Shirt,
    Trash2,
    X,
} from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';
import { useState } from 'react';

import UploadController from '@/actions/App/Http/Controllers/UploadController';
import ItemController from '@/actions/App/Http/Controllers/Wardrobe/ItemController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { cn } from '@/lib/utils';

type WardrobeUpload = {
    id: string;
    name: string;
    url: string;
};

type WardrobeTagGroupTag = {
    id: string;
    tag_group_id: string;
    name: string;
    color: string | null;
};

type WardrobeTagGroup = {
    id: string;
    name: string;
    tags: WardrobeTagGroupTag[];
};

type WardrobeItemTag = {
    id: string;
    name: string;
    color: string | null;
    tag_group: {
        id: string;
        name: string;
    };
};

type WardrobeItem = {
    id: string;
    name: string;
    description: string | null;
    main_upload: WardrobeUpload[];
    tags: WardrobeItemTag[];
};

type PaginatedWardrobeItems = {
    data: WardrobeItem[];
};

type WardrobeFilters = {
    tag_ids: string[];
    search: string | null;
};

type WardrobeItemFormData = {
    name: string;
    description: string;
    main_upload: File | null;
    main_upload_id: string;
    tag_ids: string[];
};

type WardrobeUploadFormData = {
    file: File | null;
};

type StoredWardrobeUpload = WardrobeUpload & {
    size: number;
    mime_type: string | null;
};

type WardrobeItemFormErrors = Partial<
    Record<keyof WardrobeItemFormData | `tag_ids.${number}`, string>
>;

type UploadProgress = {
    percentage?: number;
};

const loadingSkeletonKeys = ['first', 'second', 'third', 'fourth'] as const;

export default function WardrobeIndex({
    items,
    tagGroups,
    filters,
}: {
    items: PaginatedWardrobeItems;
    tagGroups: WardrobeTagGroup[];
    filters: WardrobeFilters;
}) {
    const { t } = useLaravelReactI18n();
    const isFiltered = filters.tag_ids.length > 0 || Boolean(filters.search);
    const hasTagFilters = tagGroups.some(
        (tagGroup) => tagGroup.tags.length > 0,
    );
    const tagFilterKey = filters.tag_ids.join('|');

    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <h1 className="sr-only">{t('Wardrobe')}</h1>

            <div className="flex justify-end">
                <CreateWardrobeItemDialog tagGroups={tagGroups} />
            </div>

            <WardrobeSearchForm key={filters.search ?? ''} filters={filters} />

            <div
                className={cn(
                    'grid flex-1 gap-4',
                    hasTagFilters &&
                        'lg:grid-cols-[minmax(0,1fr)_18rem] xl:grid-cols-[minmax(0,1fr)_20rem]',
                )}
            >
                <main className="min-w-0">
                    {items.data.length === 0 ? (
                        <WardrobeEmptyState isFiltered={isFiltered} />
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
                                <WardrobeItemCard
                                    key={item.id}
                                    item={item}
                                    tagGroups={tagGroups}
                                />
                            ))}
                        </InfiniteScroll>
                    )}
                </main>

                {hasTagFilters && (
                    <WardrobeTagFilters
                        key={tagFilterKey}
                        tagGroups={tagGroups}
                        filters={filters}
                    />
                )}
            </div>
        </div>
    );
}

function WardrobeSearchForm({ filters }: { filters: WardrobeFilters }) {
    const { t } = useLaravelReactI18n();
    const [search, setSearch] = useState(filters.search ?? '');
    const activeSearch = filters.search ?? null;
    const canClearSearch = search.length > 0 || activeSearch !== null;

    const visitSearch = (nextSearch: string | null) => {
        const tag_ids = filters.tag_ids;
        const search = nextSearch;

        router.visit(
            ItemController.index.get({
                query: { search, tag_ids },
            }),
            {
                only: ['items', 'filters'],
                preserveScroll: true,
                replace: true,
                reset: ['items'],
            },
        );
    };
    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const nextSearch = search.trim();
        setSearch(nextSearch);

        visitSearch(nextSearch === '' ? null : nextSearch);
    };
    const handleClearSearch = () => {
        setSearch('');

        if (activeSearch !== null) {
            visitSearch(null);
        }
    };

    return (
        <form
            role="search"
            onSubmit={handleSubmit}
            className="rounded-md border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
        >
            <div className="grid gap-2">
                <Label htmlFor="wardrobe-search">{t('Search wardrobe')}</Label>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <div className="relative min-w-0 flex-1">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            id="wardrobe-search"
                            type="search"
                            name="search"
                            value={search}
                            onChange={(event) =>
                                setSearch(event.currentTarget.value)
                            }
                            autoComplete="off"
                            placeholder={t('Item name or description')}
                            className="pl-9"
                        />
                    </div>

                    <div className="flex gap-2 sm:shrink-0">
                        <Button type="submit" className="flex-1 sm:flex-none">
                            <Search className="size-4" aria-hidden="true" />
                            {t('Search items')}
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1 sm:flex-none"
                            disabled={!canClearSearch}
                            onClick={handleClearSearch}
                        >
                            <X className="size-4" aria-hidden="true" />
                            {t('Clear search')}
                        </Button>
                    </div>
                </div>
            </div>
        </form>
    );
}

function WardrobeTagFilters({
    tagGroups,
    filters,
}: {
    tagGroups: WardrobeTagGroup[];
    filters: WardrobeFilters;
}) {
    const { t } = useLaravelReactI18n();
    const selectableTagGroups = tagGroups.filter(
        (tagGroup) => tagGroup.tags.length > 0,
    );
    const selectedTagIds = filters.tag_ids;
    const [pendingTagIds, setPendingTagIds] =
        useState<string[]>(selectedTagIds);
    const pendingTagIdSet = new Set(pendingTagIds);

    if (selectableTagGroups.length === 0) {
        return null;
    }

    const visitTagFilters = (tagIds: string[]) => {
        const search = filters.search;
        const tag_ids = tagIds;

        router.visit(
            ItemController.index.get({
                query: { search, tag_ids },
            }),
            {
                only: ['items', 'filters'],
                preserveScroll: true,
                replace: true,
                reset: ['items'],
            },
        );
    };
    const handleTagCheckedChange = (tagId: string, checked: boolean) => {
        setPendingTagIds((currentTagIds) => {
            if (checked) {
                return currentTagIds.includes(tagId)
                    ? currentTagIds
                    : [...currentTagIds, tagId];
            }

            return currentTagIds.filter(
                (currentTagId) => currentTagId !== tagId,
            );
        });
    };
    const handleApplyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        visitTagFilters(pendingTagIds);
    };
    const handleClearFilters = () => {
        setPendingTagIds([]);

        if (selectedTagIds.length > 0) {
            visitTagFilters([]);
        }
    };
    const hasSelectedFilters = pendingTagIds.length > 0;
    const hasActiveFilters = selectedTagIds.length > 0;
    const hasPendingChanges = !tagIdListsAreEqual(
        selectedTagIds,
        pendingTagIds,
    );

    return (
        <aside
            aria-labelledby="wardrobe-tag-filters-title"
            className="order-first min-w-0 lg:order-last"
        >
            <form
                onSubmit={handleApplyFilters}
                className="space-y-4 rounded-md border border-sidebar-border/70 bg-background p-4 lg:sticky lg:top-4 dark:border-sidebar-border"
            >
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-muted text-muted-foreground">
                        <Filter className="size-4" aria-hidden="true" />
                    </span>
                    <h2
                        id="wardrobe-tag-filters-title"
                        className="text-sm font-medium"
                    >
                        {t('Filter by tags')}
                    </h2>

                    {hasSelectedFilters && (
                        <Badge
                            variant="secondary"
                            className="rounded-sm px-1.5 py-0 text-xs"
                        >
                            {t(':count selected', {
                                count: pendingTagIds.length,
                            })}
                        </Badge>
                    )}
                </div>

                <div className="grid gap-4">
                    {selectableTagGroups.map((tagGroup) => (
                        <fieldset
                            key={tagGroup.id}
                            className="min-w-0 space-y-2"
                        >
                            <legend className="text-sm font-medium text-muted-foreground">
                                {tagGroup.name}
                            </legend>

                            <div className="grid gap-2">
                                {tagGroup.tags.map((tag) => {
                                    const checkboxId = `wardrobe-filter-tag-${tag.id}`;

                                    return (
                                        <div
                                            key={tag.id}
                                            className="flex min-w-0 items-center gap-2"
                                        >
                                            <Checkbox
                                                id={checkboxId}
                                                checked={pendingTagIdSet.has(
                                                    tag.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    handleTagCheckedChange(
                                                        tag.id,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={checkboxId}
                                                className="flex min-w-0 cursor-pointer items-center gap-1.5 text-sm font-normal"
                                            >
                                                {tag.color && (
                                                    <TagColorDot
                                                        color={tag.color}
                                                    />
                                                )}
                                                <span className="min-w-0 truncate">
                                                    {tag.name}
                                                </span>
                                            </Label>
                                        </div>
                                    );
                                })}
                            </div>
                        </fieldset>
                    ))}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row lg:flex-col">
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={!hasPendingChanges}
                    >
                        <Filter className="size-4" aria-hidden="true" />
                        {t('Apply filters')}
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        className="w-full"
                        disabled={!hasSelectedFilters && !hasActiveFilters}
                        onClick={handleClearFilters}
                    >
                        <X className="size-4" aria-hidden="true" />
                        {t('Clear filters')}
                    </Button>
                </div>
            </form>
        </aside>
    );
}

function tagIdListsAreEqual(
    firstTagIds: string[],
    secondTagIds: string[],
): boolean {
    if (firstTagIds.length !== secondTagIds.length) {
        return false;
    }

    const secondTagIdSet = new Set(secondTagIds);

    return firstTagIds.every((tagId) => secondTagIdSet.has(tagId));
}

function TagColorDot({ color }: { color: string }) {
    return (
        <span
            className="size-2.5 shrink-0 rounded-full border border-black/10 dark:border-white/20"
            style={{ backgroundColor: color }}
            aria-hidden="true"
        />
    );
}

function WardrobeItemCard({
    item,
    tagGroups,
}: {
    item: WardrobeItem;
    tagGroups: WardrobeTagGroup[];
}) {
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

                {item.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {item.tags.map((tag) => (
                            <Badge
                                key={tag.id}
                                variant="secondary"
                                title={`${tag.tag_group.name}: ${tag.name}`}
                                className="max-w-full gap-1.5 rounded-sm px-1.5 py-0 text-xs"
                            >
                                {tag.color && <TagColorDot color={tag.color} />}
                                <span className="min-w-0 truncate">
                                    {tag.name}
                                </span>
                            </Badge>
                        ))}
                    </div>
                )}
            </CardContent>

            <CardFooter className="justify-end gap-1 border-t border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border">
                <EditWardrobeItemDialog item={item} tagGroups={tagGroups} />
                <DeleteWardrobeItemDialog item={item} />
            </CardFooter>
        </Card>
    );
}

function CreateWardrobeItemDialog({
    tagGroups,
}: {
    tagGroups: WardrobeTagGroup[];
}) {
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

                <WardrobeItemForm
                    tagGroups={tagGroups}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function EditWardrobeItemDialog({
    item,
    tagGroups,
}: {
    item: WardrobeItem;
    tagGroups: WardrobeTagGroup[];
}) {
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
                    tagGroups={tagGroups}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function WardrobeItemForm({
    item,
    tagGroups,
    onSuccess,
}: {
    item?: WardrobeItem;
    tagGroups: WardrobeTagGroup[];
    onSuccess: () => void;
}) {
    const { t } = useLaravelReactI18n();
    const isEditing = item !== undefined;
    const [mainUpload, setMainUpload] = useState<WardrobeUpload | null>(
        item?.main_upload[0] ?? null,
    );
    const [selectedTagIds, setSelectedTagIds] = useState<string[]>(
        () => item?.tags.map((tag) => tag.id) ?? [],
    );
    const upload = useHttp<WardrobeUploadFormData, StoredWardrobeUpload>({
        file: null,
    });
    const form = isEditing
        ? ItemController.update.form.put(item.id)
        : ItemController.store.form.post();

    const uploadMainImage = async (file: File | null) => {
        if (!file) {
            return;
        }

        upload.clearErrors('file');
        upload.setData('file', file);
        upload.transform(() => ({ file }));

        try {
            const storedUpload = await upload.post(
                UploadController.store.url(),
            );

            setMainUpload({
                id: storedUpload.id,
                name: storedUpload.name,
                url: storedUpload.url,
            });
            upload.reset('file');
        } catch {
            // useHttp exposes validation errors through upload.errors.
        }
    };
    const handleTagCheckedChange = (tagId: string, checked: boolean) => {
        setSelectedTagIds((currentTagIds) => {
            if (checked) {
                return currentTagIds.includes(tagId)
                    ? currentTagIds
                    : [...currentTagIds, tagId];
            }

            return currentTagIds.filter(
                (currentTagId) => currentTagId !== tagId,
            );
        });
    };

    return (
        <Form<WardrobeItemFormData>
            {...form}
            transform={(data) => ({
                ...data,
                tag_ids: selectedTagIds,
            })}
            options={{
                preserveScroll: true,
            }}
            onSuccess={() => {
                if (!isEditing) {
                    setMainUpload(null);
                    setSelectedTagIds([]);
                }

                onSuccess();
            }}
            resetOnSuccess={
                isEditing ? ['main_upload', 'main_upload_id'] : true
            }
            disableWhileProcessing
            className="space-y-5"
        >
            {({ errors, processing, resetAndClearErrors }) => (
                <>
                    <WardrobeItemFormFields
                        item={item}
                        tagGroups={tagGroups}
                        mainUpload={mainUpload}
                        selectedTagIds={selectedTagIds}
                        errors={errors}
                        uploadError={
                            upload.errors.file ??
                            errors.main_upload_id ??
                            errors.main_upload
                        }
                        uploadProgress={upload.progress}
                        uploadProcessing={upload.processing}
                        disabled={processing || upload.processing}
                        onMainUploadChange={uploadMainImage}
                        onTagCheckedChange={handleTagCheckedChange}
                    />

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={processing || upload.processing}
                                onClick={() => {
                                    upload.cancel();
                                    upload.resetAndClearErrors();
                                    resetAndClearErrors();
                                }}
                            >
                                {t('Cancel')}
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            disabled={processing || upload.processing}
                        >
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
    tagGroups,
    mainUpload,
    selectedTagIds,
    errors,
    uploadError,
    uploadProgress,
    uploadProcessing,
    disabled,
    onMainUploadChange,
    onTagCheckedChange,
}: {
    item?: WardrobeItem;
    tagGroups: WardrobeTagGroup[];
    mainUpload: WardrobeUpload | null;
    selectedTagIds: string[];
    errors: WardrobeItemFormErrors;
    uploadError?: string;
    uploadProgress: UploadProgress | null;
    uploadProcessing: boolean;
    disabled: boolean;
    onMainUploadChange: (file: File | null) => void;
    onTagCheckedChange: (tagId: string, checked: boolean) => void;
}) {
    const { t } = useLaravelReactI18n();
    const fieldIdPrefix = item ? `wardrobe-item-${item.id}` : 'wardrobe-item';
    const nameErrorId = `${fieldIdPrefix}-name-error`;
    const descriptionErrorId = `${fieldIdPrefix}-description-error`;
    const mainUploadHelpId = `${fieldIdPrefix}-main-upload-help`;
    const mainUploadErrorId = `${fieldIdPrefix}-main-upload-error`;
    const tagsErrorId = `${fieldIdPrefix}-tags-error`;
    const tagError = errors.tag_ids ?? errors['tag_ids.0'];

    return (
        <div className="space-y-4">
            <input
                type="hidden"
                name="main_upload_id"
                value={mainUpload?.id ?? ''}
            />

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

                <WardrobeMainImageUpload
                    id={`${fieldIdPrefix}-main-upload`}
                    upload={mainUpload}
                    disabled={disabled}
                    error={uploadError}
                    progress={uploadProgress}
                    processing={uploadProcessing}
                    helpId={mainUploadHelpId}
                    errorId={mainUploadErrorId}
                    onChange={onMainUploadChange}
                />

                <p
                    id={mainUploadHelpId}
                    className="text-sm text-muted-foreground"
                >
                    {t('Optional image up to 10 MB.')}
                </p>

                <InputError id={mainUploadErrorId} message={uploadError} />
            </div>

            <WardrobeItemTagsField
                idPrefix={fieldIdPrefix}
                tagGroups={tagGroups}
                selectedTagIds={selectedTagIds}
                disabled={disabled}
                error={tagError}
                errorId={tagsErrorId}
                onTagCheckedChange={onTagCheckedChange}
            />
        </div>
    );
}

function WardrobeItemTagsField({
    idPrefix,
    tagGroups,
    selectedTagIds,
    disabled,
    error,
    errorId,
    onTagCheckedChange,
}: {
    idPrefix: string;
    tagGroups: WardrobeTagGroup[];
    selectedTagIds: string[];
    disabled: boolean;
    error?: string;
    errorId: string;
    onTagCheckedChange: (tagId: string, checked: boolean) => void;
}) {
    const { t } = useLaravelReactI18n();
    const selectableTagGroups = tagGroups.filter(
        (tagGroup) => tagGroup.tags.length > 0,
    );
    const selectedTagIdSet = new Set(selectedTagIds);

    if (selectableTagGroups.length === 0) {
        return null;
    }

    return (
        <fieldset
            className="grid gap-3"
            aria-invalid={Boolean(error)}
            aria-describedby={error ? errorId : undefined}
        >
            <legend className="text-sm font-medium">{t('Tags')}</legend>

            <div className="space-y-3 rounded-md border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                {selectableTagGroups.map((tagGroup) => (
                    <div key={tagGroup.id} className="space-y-2">
                        <p className="text-sm font-medium text-muted-foreground">
                            {tagGroup.name}
                        </p>

                        <div className="grid gap-2 sm:grid-cols-2">
                            {tagGroup.tags.map((tag) => {
                                const checkboxId = `${idPrefix}-tag-${tag.id}`;

                                return (
                                    <div
                                        key={tag.id}
                                        className="flex min-w-0 items-center gap-2"
                                    >
                                        <Checkbox
                                            id={checkboxId}
                                            checked={selectedTagIdSet.has(
                                                tag.id,
                                            )}
                                            disabled={disabled}
                                            aria-invalid={Boolean(error)}
                                            onCheckedChange={(checked) =>
                                                onTagCheckedChange(
                                                    tag.id,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={checkboxId}
                                            className="flex min-w-0 cursor-pointer items-center gap-1.5 text-sm font-normal"
                                        >
                                            {tag.color && (
                                                <TagColorDot
                                                    color={tag.color}
                                                />
                                            )}
                                            <span className="min-w-0 truncate">
                                                {tag.name}
                                            </span>
                                        </Label>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>

            <InputError id={errorId} message={error} />
        </fieldset>
    );
}

function WardrobeMainImageUpload({
    id,
    upload,
    disabled,
    error,
    progress,
    processing,
    helpId,
    errorId,
    onChange,
}: {
    id: string;
    upload: WardrobeUpload | null;
    disabled: boolean;
    error?: string;
    progress: UploadProgress | null;
    processing: boolean;
    helpId: string;
    errorId: string;
    onChange: (file: File | null) => void;
}) {
    const { t } = useLaravelReactI18n();
    const describedBy = error ? `${helpId} ${errorId}` : helpId;
    const isDisabled = disabled || processing;

    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.currentTarget.files?.[0] ?? null;

        onChange(file);
        event.currentTarget.value = '';
    };

    return (
        <label
            htmlFor={id}
            className={cn(
                'group flex min-h-40 cursor-pointer overflow-hidden rounded-md border border-dashed border-sidebar-border/80 bg-muted/20 text-left transition focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 hover:border-primary/60 hover:bg-muted/30 dark:border-sidebar-border dark:bg-muted/10 dark:hover:bg-muted/20',
                isDisabled && 'pointer-events-none opacity-60',
                error &&
                    'border-destructive focus-within:border-destructive focus-within:ring-destructive/20',
            )}
        >
            <input
                id={id}
                type="file"
                name="main_upload"
                accept="image/*"
                className="sr-only"
                disabled={isDisabled}
                aria-invalid={Boolean(error)}
                aria-describedby={describedBy}
                onChange={handleChange}
            />

            {upload ? (
                <div className="grid w-full gap-4 p-3 sm:grid-cols-[8rem_1fr]">
                    <div className="aspect-square overflow-hidden rounded-md bg-muted">
                        <img
                            src={upload.url}
                            alt={upload.name}
                            className="size-full object-cover"
                        />
                    </div>

                    <div className="flex min-w-0 flex-col justify-center gap-3">
                        <div className="min-w-0 space-y-1">
                            <p className="truncate text-sm font-medium">
                                {upload.name}
                            </p>
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                {processing ? (
                                    <Loader2
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <CheckCircle2
                                        className="size-4 text-emerald-600 dark:text-emerald-400"
                                        aria-hidden="true"
                                    />
                                )}
                                <span>
                                    {processing
                                        ? t('Uploading image')
                                        : t('Image ready')}
                                </span>
                            </div>
                        </div>

                        <span className="inline-flex w-fit items-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium shadow-xs transition-colors group-hover:bg-accent group-hover:text-accent-foreground">
                            <ImagePlus className="size-4" aria-hidden="true" />
                            {t('Replace image')}
                        </span>

                        <WardrobeUploadProgress progress={progress} />
                    </div>
                </div>
            ) : (
                <div className="flex w-full flex-col items-center justify-center gap-3 p-6 text-center">
                    <div className="flex size-12 items-center justify-center rounded-full border border-border bg-background text-muted-foreground shadow-xs transition-colors group-hover:text-foreground">
                        {processing ? (
                            <Loader2
                                className="size-5 animate-spin"
                                aria-hidden="true"
                            />
                        ) : (
                            <ImagePlus className="size-5" aria-hidden="true" />
                        )}
                    </div>

                    <div className="space-y-1">
                        <p className="text-sm font-medium">
                            {processing
                                ? t('Uploading image')
                                : t('Choose image')}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('Image will be attached after upload.')}
                        </p>
                    </div>

                    <WardrobeUploadProgress progress={progress} />
                </div>
            )}
        </label>
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

function WardrobeEmptyState({ isFiltered }: { isFiltered: boolean }) {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex min-h-96 flex-1 items-center justify-center rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-6 text-center dark:border-sidebar-border dark:bg-muted/10">
            <div className="flex max-w-sm flex-col items-center gap-3">
                <div className="flex size-12 items-center justify-center rounded-full border border-border bg-background text-muted-foreground shadow-xs">
                    <Shirt className="size-5" />
                </div>
                <div className="space-y-1">
                    <h2 className="text-base font-medium">
                        {isFiltered
                            ? t(
                                  'No wardrobe items match your search or filters',
                              )
                            : t('No wardrobe items yet')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {isFiltered
                            ? t(
                                  'Try adjusting your search or clearing one or more filters.',
                              )
                            : t(
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
