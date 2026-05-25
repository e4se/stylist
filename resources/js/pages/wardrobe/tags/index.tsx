import { Form } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Pencil, Plus, Tags, Trash2, X } from 'lucide-react';
import { useState } from 'react';

import TagController from '@/actions/App/Http/Controllers/Wardrobe/TagController';
import TagGroupController from '@/actions/App/Http/Controllers/Wardrobe/TagGroupController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import type { RouteFormDefinition } from '@/wayfinder';

type WardrobeTag = {
    id: string;
    tag_group_id: string;
    name: string;
    color: string | null;
};

type WardrobeTagGroup = {
    id: string;
    name: string;
    tags: WardrobeTag[];
};

type NameFormData = {
    name: string;
};

type TagFormData = {
    name: string;
    color: string;
};

const pickerFallbackColor = '#6b7280';

export default function WardrobeTagsIndex({
    tagGroups,
}: {
    tagGroups: WardrobeTagGroup[];
}) {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 className="text-base font-semibold">{t('Tags')}</h1>

                <CreateTagGroupDialog />
            </div>

            {tagGroups.length === 0 ? (
                <TagGroupsEmptyState />
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {tagGroups.map((tagGroup) => (
                        <TagGroupCard key={tagGroup.id} tagGroup={tagGroup} />
                    ))}
                </div>
            )}
        </div>
    );
}

function TagGroupsEmptyState() {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex min-h-80 flex-1 items-center justify-center rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-6 text-center dark:border-sidebar-border dark:bg-muted/10">
            <div className="flex max-w-sm flex-col items-center gap-3">
                <div className="flex size-12 items-center justify-center rounded-full border border-border bg-background text-muted-foreground shadow-xs">
                    <Tags className="size-5" aria-hidden="true" />
                </div>

                <div className="space-y-1">
                    <h2 className="text-base font-medium">
                        {t('No tag groups yet')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Create tag groups for colors, seasons, styles, or any wardrobe detail.',
                        )}
                    </p>
                </div>
            </div>
        </div>
    );
}

function TagGroupCard({ tagGroup }: { tagGroup: WardrobeTagGroup }) {
    const { t } = useLaravelReactI18n();

    return (
        <Card className="gap-0 rounded-md border-sidebar-border/70 py-0 shadow-xs dark:border-sidebar-border">
            <CardHeader className="gap-3 px-4 py-4">
                <div className="flex min-w-0 items-start justify-between gap-3">
                    <CardTitle
                        className="min-w-0 truncate text-sm leading-5"
                        title={tagGroup.name}
                    >
                        {tagGroup.name}
                    </CardTitle>

                    <div className="flex shrink-0 items-center gap-1">
                        <EditTagGroupDialog tagGroup={tagGroup} />
                        <DeleteTagGroupDialog tagGroup={tagGroup} />
                    </div>
                </div>
            </CardHeader>

            <CardContent className="px-4 pb-4">
                {tagGroup.tags.length === 0 ? (
                    <div className="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-4 text-sm text-muted-foreground dark:border-sidebar-border dark:bg-muted/10">
                        {t('No tags in this group yet')}
                    </div>
                ) : (
                    <ul className="space-y-2">
                        {tagGroup.tags.map((tag) => (
                            <TagRow
                                key={tag.id}
                                tagGroup={tagGroup}
                                tag={tag}
                            />
                        ))}
                    </ul>
                )}
            </CardContent>

            <CardFooter className="border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <CreateTagDialog tagGroup={tagGroup} />
            </CardFooter>
        </Card>
    );
}

function TagRow({
    tagGroup,
    tag,
}: {
    tagGroup: WardrobeTagGroup;
    tag: WardrobeTag;
}) {
    return (
        <li className="flex min-w-0 items-center justify-between gap-2 rounded-md border border-sidebar-border/70 bg-background px-2 py-2 dark:border-sidebar-border">
            <div className="min-w-0 flex-1">
                <Badge
                    variant="secondary"
                    className="max-w-full shrink gap-1.5 rounded-sm px-1.5 py-0 text-xs"
                    title={tag.name}
                >
                    {tag.color && <TagColorDot color={tag.color} />}
                    <span className="min-w-0 truncate">{tag.name}</span>
                </Badge>
            </div>

            <div className="flex shrink-0 items-center gap-1">
                <EditTagDialog tagGroup={tagGroup} tag={tag} />
                <DeleteTagDialog tagGroup={tagGroup} tag={tag} />
            </div>
        </li>
    );
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

function CreateTagGroupDialog() {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="size-4" aria-hidden="true" />
                    {t('Add tag group')}
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Create tag group')}</DialogTitle>
                    <DialogDescription>
                        {t('Name this group so related tags stay together.')}
                    </DialogDescription>
                </DialogHeader>

                <NameForm
                    form={TagGroupController.store.form.post()}
                    errorBag="createTagGroup"
                    fieldId="tag-group-name"
                    label={t('Tag group name')}
                    placeholder={t('e.g., Color')}
                    submitLabel={t('Create group')}
                    processingLabel={t('Creating...')}
                    onSuccess={() => setOpen(false)}
                    resetOnSuccess
                />
            </DialogContent>
        </Dialog>
    );
}

function EditTagGroupDialog({ tagGroup }: { tagGroup: WardrobeTagGroup }) {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={t('Edit :name', { name: tagGroup.name })}
                >
                    <Pencil className="size-4" aria-hidden="true" />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Rename tag group')}</DialogTitle>
                    <DialogDescription>
                        {t('Update the group name.')}
                    </DialogDescription>
                </DialogHeader>

                <NameForm
                    form={TagGroupController.update.form.put(tagGroup.id)}
                    errorBag={`updateTagGroup-${tagGroup.id}`}
                    fieldId={`tag-group-${tagGroup.id}-name`}
                    label={t('Tag group name')}
                    placeholder={t('e.g., Color')}
                    defaultValue={tagGroup.name}
                    submitLabel={t('Save')}
                    processingLabel={t('Saving...')}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function DeleteTagGroupDialog({ tagGroup }: { tagGroup: WardrobeTagGroup }) {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    aria-label={t('Delete :name', { name: tagGroup.name })}
                >
                    <Trash2 className="size-4" aria-hidden="true" />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Delete tag group')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Are you sure you want to delete the ":name" tag group? Its tags will be removed from wardrobe items.',
                            { name: tagGroup.name },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DeleteForm
                    form={TagGroupController.destroy.form.delete(tagGroup.id)}
                    submitLabel={t('Delete group')}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function CreateTagDialog({ tagGroup }: { tagGroup: WardrobeTagGroup }) {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <Plus className="size-4" aria-hidden="true" />
                    {t('Add tag')}
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Create tag')}</DialogTitle>
                    <DialogDescription>
                        {t('Name this tag within the selected group.')}
                    </DialogDescription>
                </DialogHeader>

                <TagForm
                    form={TagController.store.form.post(tagGroup.id)}
                    errorBag={`createTag-${tagGroup.id}`}
                    fieldId={`tag-group-${tagGroup.id}-tag-name`}
                    placeholder={t('e.g., Linen')}
                    submitLabel={t('Create tag')}
                    processingLabel={t('Creating...')}
                    onSuccess={() => setOpen(false)}
                    resetOnSuccess
                />
            </DialogContent>
        </Dialog>
    );
}

function EditTagDialog({
    tagGroup,
    tag,
}: {
    tagGroup: WardrobeTagGroup;
    tag: WardrobeTag;
}) {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={t('Edit :name', { name: tag.name })}
                >
                    <Pencil className="size-4" aria-hidden="true" />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Rename tag')}</DialogTitle>
                    <DialogDescription>
                        {t('Update the tag name.')}
                    </DialogDescription>
                </DialogHeader>

                <TagForm
                    form={TagController.update.form.put({
                        tagGroup: tagGroup.id,
                        tag: tag.id,
                    })}
                    errorBag={`updateTag-${tag.id}`}
                    fieldId={`tag-${tag.id}-name`}
                    placeholder={t('e.g., Linen')}
                    defaultValue={tag.name}
                    defaultColor={tag.color}
                    submitLabel={t('Save')}
                    processingLabel={t('Saving...')}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function DeleteTagDialog({
    tagGroup,
    tag,
}: {
    tagGroup: WardrobeTagGroup;
    tag: WardrobeTag;
}) {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    aria-label={t('Delete :name', { name: tag.name })}
                >
                    <Trash2 className="size-4" aria-hidden="true" />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Delete tag')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Are you sure you want to delete the ":name" tag? It will be removed from wardrobe items.',
                            { name: tag.name },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DeleteForm
                    form={TagController.destroy.form.delete({
                        tagGroup: tagGroup.id,
                        tag: tag.id,
                    })}
                    submitLabel={t('Delete tag')}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function NameForm({
    form,
    errorBag,
    fieldId,
    label,
    placeholder,
    defaultValue = '',
    submitLabel,
    processingLabel,
    onSuccess,
    resetOnSuccess = false,
}: {
    form: RouteFormDefinition<'post'>;
    errorBag: string;
    fieldId: string;
    label: string;
    placeholder: string;
    defaultValue?: string;
    submitLabel: string;
    processingLabel: string;
    onSuccess: () => void;
    resetOnSuccess?: boolean;
}) {
    const { t } = useLaravelReactI18n();
    const errorId = `${fieldId}-error`;

    return (
        <Form<NameFormData>
            {...form}
            errorBag={errorBag}
            options={{
                preserveScroll: true,
            }}
            onSuccess={onSuccess}
            resetOnSuccess={resetOnSuccess}
            disableWhileProcessing
            className="space-y-5"
        >
            {({ errors, processing, resetAndClearErrors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={fieldId}>{label}</Label>

                        <Input
                            id={fieldId}
                            type="text"
                            name="name"
                            defaultValue={defaultValue}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder={placeholder}
                            disabled={processing}
                            aria-invalid={Boolean(errors.name)}
                            aria-describedby={errors.name ? errorId : undefined}
                        />

                        <InputError id={errorId} message={errors.name} />
                    </div>

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
                            {processing ? processingLabel : submitLabel}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

function TagForm({
    form,
    errorBag,
    fieldId,
    placeholder,
    defaultValue = '',
    defaultColor = null,
    submitLabel,
    processingLabel,
    onSuccess,
    resetOnSuccess = false,
}: {
    form: RouteFormDefinition<'post'>;
    errorBag: string;
    fieldId: string;
    placeholder: string;
    defaultValue?: string;
    defaultColor?: string | null;
    submitLabel: string;
    processingLabel: string;
    onSuccess: () => void;
    resetOnSuccess?: boolean;
}) {
    const { t } = useLaravelReactI18n();
    const nameErrorId = `${fieldId}-error`;
    const colorFieldId = `${fieldId}-color`;
    const colorErrorId = `${colorFieldId}-error`;
    const [selectedColor, setSelectedColor] = useState<string | null>(
        defaultColor,
    );
    const [pickerColor, setPickerColor] = useState(
        defaultColor ?? pickerFallbackColor,
    );
    const clearColor = () => {
        setSelectedColor(null);
        setPickerColor(pickerFallbackColor);
    };

    return (
        <Form<TagFormData>
            {...form}
            errorBag={errorBag}
            options={{
                preserveScroll: true,
            }}
            onSuccess={() => {
                if (resetOnSuccess) {
                    setSelectedColor(null);
                    setPickerColor(pickerFallbackColor);
                }

                onSuccess();
            }}
            resetOnSuccess={resetOnSuccess}
            disableWhileProcessing
            className="space-y-5"
        >
            {({ errors, processing, resetAndClearErrors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={fieldId}>{t('Tag name')}</Label>

                        <Input
                            id={fieldId}
                            type="text"
                            name="name"
                            defaultValue={defaultValue}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder={placeholder}
                            disabled={processing}
                            aria-invalid={Boolean(errors.name)}
                            aria-describedby={
                                errors.name ? nameErrorId : undefined
                            }
                        />

                        <InputError id={nameErrorId} message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={colorFieldId}>{t('Tag color')}</Label>

                        <input
                            name="color"
                            type="hidden"
                            value={selectedColor ?? ''}
                            readOnly
                        />

                        <div className="flex items-center gap-2">
                            <Input
                                id={colorFieldId}
                                type="color"
                                value={pickerColor}
                                className="h-10 w-14 p-1"
                                disabled={processing}
                                aria-invalid={Boolean(errors.color)}
                                aria-describedby={
                                    errors.color ? colorErrorId : undefined
                                }
                                onChange={(event) => {
                                    setPickerColor(event.currentTarget.value);
                                    setSelectedColor(event.currentTarget.value);
                                }}
                            />

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-9"
                                disabled={processing || selectedColor === null}
                                aria-label={t('Clear tag color')}
                                onClick={clearColor}
                            >
                                <X className="size-4" aria-hidden="true" />
                            </Button>
                        </div>

                        <InputError id={colorErrorId} message={errors.color} />
                    </div>

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
                            {processing ? processingLabel : submitLabel}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

function DeleteForm({
    form,
    submitLabel,
    onSuccess,
}: {
    form: RouteFormDefinition<'post'>;
    submitLabel: string;
    onSuccess: () => void;
}) {
    const { t } = useLaravelReactI18n();

    return (
        <Form
            {...form}
            options={{
                preserveScroll: true,
            }}
            onSuccess={onSuccess}
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
                        {processing ? t('Deleting...') : submitLabel}
                    </Button>
                </DialogFooter>
            )}
        </Form>
    );
}
