<?php

namespace App\Http\Controllers\Wardrobe;

use App\Actions\Uploads\StoreUpload;
use App\Enums\ItemUploadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Items\IndexItemRequest;
use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Models\Item;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ItemController extends Controller
{
    public function __construct(private readonly StoreUpload $storeUpload) {}

    /**
     * Show the authenticated user's wardrobe items.
     */
    public function index(IndexItemRequest $request): Response
    {
        $this->authorize('viewAny', Item::class);
        $this->authorize('viewAny', TagGroup::class);
        $this->authorize('viewAny', Tag::class);

        /** @var User $user */
        $user = $request->user();
        $query = $user->items()->with(['mainUpload', 'tags.tagGroup']);
        $tagIds = $this->validatedTagIds($request);

        foreach ($this->validatedTagIdsByGroup($tagIds, $user) as $groupTagIds) {
            $query->whereHas('tags', fn (Builder $tagQuery): Builder => $tagQuery->whereKey($groupTagIds));
        }

        $items = $query
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Item $item): array => $this->itemData($item));

        return Inertia::render('wardrobe/index', [
            'items' => Inertia::scroll($items),
            'tagGroups' => $this->tagGroupsData($user),
            'filters' => [
                'tag_ids' => $tagIds,
            ],
        ]);
    }

    /**
     * Store a new wardrobe item.
     */
    public function store(StoreItemRequest $request): RedirectResponse
    {
        $this->authorize('create', Item::class);

        /** @var User $user */
        $user = $request->user();
        $storedUpload = null;

        try {
            DB::transaction(function () use ($request, $user, &$storedUpload): void {
                $item = $user->items()->create($this->itemAttributes($request));
                $this->syncTags($item, $request);

                $mainUpload = $this->mainUpload($request, $user, $storedUpload);

                if ($mainUpload instanceof Upload) {
                    $this->replaceMainUpload($item, $mainUpload);
                }
            });
        } catch (Throwable $exception) {
            $this->cleanupUpload($storedUpload);

            throw $exception;
        }

        return to_route('wardrobe.index');
    }

    /**
     * Update an existing wardrobe item.
     */
    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        /** @var User $user */
        $user = $request->user();
        $storedUpload = null;

        try {
            DB::transaction(function () use ($request, $item, $user, &$storedUpload): void {
                $item->update($this->itemAttributes($request));
                $this->syncTags($item, $request);

                $mainUpload = $this->mainUpload($request, $user, $storedUpload);

                if ($mainUpload instanceof Upload) {
                    $this->replaceMainUpload($item, $mainUpload);
                }
            });
        } catch (Throwable $exception) {
            $this->cleanupUpload($storedUpload);

            throw $exception;
        }

        return to_route('wardrobe.index');
    }

    /**
     * Soft delete a wardrobe item.
     */
    public function destroy(Item $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return to_route('wardrobe.index');
    }

    /**
     * Get validated item attributes.
     *
     * @return array{name: string, description: string|null}
     */
    private function itemAttributes(StoreItemRequest|UpdateItemRequest $request): array
    {
        /** @var array{name: string, description?: string|null} $validated */
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];
    }

    private function mainUpload(StoreItemRequest|UpdateItemRequest $request, User $user, ?Upload &$storedUpload): ?Upload
    {
        $selectedUpload = $this->selectedMainUpload($request, $user);

        if ($selectedUpload instanceof Upload) {
            return $selectedUpload;
        }

        $storedUpload = $this->storeMainUpload($request, $user);

        return $storedUpload;
    }

    private function selectedMainUpload(StoreItemRequest|UpdateItemRequest $request, User $user): ?Upload
    {
        /** @var array{main_upload_id?: string|null} $validated */
        $validated = $request->validated();
        $uploadId = $validated['main_upload_id'] ?? null;

        if (! is_string($uploadId) || $uploadId === '') {
            return null;
        }

        return Upload::query()
            ->whereBelongsTo($user)
            ->whereKey($uploadId)
            ->firstOrFail();
    }

    private function storeMainUpload(StoreItemRequest|UpdateItemRequest $request, User $user): ?Upload
    {
        $file = $request->file('main_upload');

        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $this->storeUpload->execute($user, $file);
    }

    private function replaceMainUpload(Item $item, Upload $upload): void
    {
        $item->mainUpload()->syncWithPivotValues(
            [(string) $upload->getKey()],
            ['type' => ItemUploadType::Main->value],
        );
    }

    private function syncTags(Item $item, StoreItemRequest|UpdateItemRequest $request): void
    {
        /** @var array{tag_ids?: array<int|string, string>|null} $validated */
        $validated = $request->validated();

        if (! array_key_exists('tag_ids', $validated)) {
            return;
        }

        $item->tags()->sync($this->validatedTagIds($request));
    }

    /**
     * @return list<string>
     */
    private function validatedTagIds(IndexItemRequest|StoreItemRequest|UpdateItemRequest $request): array
    {
        /** @var array{tag_ids?: array<int|string, string>|null} $validated */
        $validated = $request->validated();
        $tagIds = $validated['tag_ids'] ?? null;

        if (! is_array($tagIds)) {
            return [];
        }

        return array_values($tagIds);
    }

    /**
     * @param  list<string>  $tagIds
     * @return array<string, list<string>>
     */
    private function validatedTagIdsByGroup(array $tagIds, User $user): array
    {
        if ($tagIds === []) {
            return [];
        }

        $tags = Tag::query()
            ->select(['id', 'tag_group_id'])
            ->whereKey($tagIds)
            ->whereIn('tag_group_id', $user->tagGroups()->select('id'))
            ->get();

        /** @var EloquentCollection<int, Tag> $tags */
        $tagIdsByGroup = [];

        foreach ($tags as $tag) {
            $tagGroupId = (string) $tag->getAttribute('tag_group_id');

            $tagIdsByGroup[$tagGroupId][] = (string) $tag->getKey();
        }

        return $tagIdsByGroup;
    }

    /**
     * Format a wardrobe item for the Inertia index page.
     *
     * @return array{id: string, name: string, description: string|null, main_upload: list<array{id: string, name: string, url: string}>, tags: list<array{id: string, name: string, color: string|null, tag_group: array{id: string, name: string}}>}
     */
    private function itemData(Item $item): array
    {
        $mainUploads = $item->getRelationValue('mainUpload');
        $tags = $item->getRelationValue('tags');
        $description = $item->getAttribute('description');

        assert($mainUploads instanceof EloquentCollection);
        assert($tags instanceof EloquentCollection);
        assert(is_string($description) || $description === null);

        /** @var EloquentCollection<int, Upload> $mainUploads */
        /** @var EloquentCollection<int, Tag> $tags */
        return [
            'id' => (string) $item->getKey(),
            'name' => (string) $item->getAttribute('name'),
            'description' => $description,
            'main_upload' => $mainUploads
                ->map(fn (Upload $upload): array => [
                    'id' => (string) $upload->getKey(),
                    'name' => (string) $upload->getAttribute('name'),
                    'url' => Storage::disk((string) $upload->getAttribute('disk'))->url((string) $upload->getAttribute('path')),
                ])
                ->values()
                ->all(),
            'tags' => $tags
                ->sortBy(fn (Tag $tag): string => $this->tagSortKey($tag))
                ->map(fn (Tag $tag): array => $this->itemTagData($tag))
                ->values()
                ->all(),
        ];
    }

    /**
     * Format user tag groups for item forms and filters.
     *
     * @return list<array{id: string, name: string, tags: list<array{id: string, tag_group_id: string, name: string, color: string|null}>}>
     */
    private function tagGroupsData(User $user): array
    {
        $tagGroups = $user->tagGroups()
            ->with(['tags' => fn ($query) => $query->orderBy('name')->orderBy('id')])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        /** @var EloquentCollection<int, TagGroup> $tagGroups */
        return $tagGroups
            ->map(fn (TagGroup $tagGroup): array => $this->tagGroupData($tagGroup))
            ->values()
            ->all();
    }

    /**
     * Format a tag group for the Inertia wardrobe page.
     *
     * @return array{id: string, name: string, tags: list<array{id: string, tag_group_id: string, name: string, color: string|null}>}
     */
    private function tagGroupData(TagGroup $tagGroup): array
    {
        $tags = $tagGroup->getRelationValue('tags');

        assert($tags instanceof EloquentCollection);

        /** @var EloquentCollection<int, Tag> $tags */
        return [
            'id' => (string) $tagGroup->getKey(),
            'name' => (string) $tagGroup->getAttribute('name'),
            'tags' => $tags
                ->map(fn (Tag $tag): array => [
                    'id' => (string) $tag->getKey(),
                    'tag_group_id' => (string) $tag->getAttribute('tag_group_id'),
                    'name' => (string) $tag->getAttribute('name'),
                    'color' => $this->tagColor($tag),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Format an item tag for the Inertia wardrobe page.
     *
     * @return array{id: string, name: string, color: string|null, tag_group: array{id: string, name: string}}
     */
    private function itemTagData(Tag $tag): array
    {
        $tagGroup = $tag->getRelationValue('tagGroup');

        assert($tagGroup instanceof TagGroup);

        return [
            'id' => (string) $tag->getKey(),
            'name' => (string) $tag->getAttribute('name'),
            'color' => $this->tagColor($tag),
            'tag_group' => [
                'id' => (string) $tagGroup->getKey(),
                'name' => (string) $tagGroup->getAttribute('name'),
            ],
        ];
    }

    private function tagSortKey(Tag $tag): string
    {
        $tagGroup = $tag->getRelationValue('tagGroup');

        assert($tagGroup instanceof TagGroup);

        return sprintf(
            '%s|%s|%s',
            mb_strtolower((string) $tagGroup->getAttribute('name')),
            mb_strtolower((string) $tag->getAttribute('name')),
            (string) $tag->getKey(),
        );
    }

    private function tagColor(Tag $tag): ?string
    {
        $color = $tag->getAttribute('color');

        assert(is_string($color) || $color === null);

        return $color;
    }

    private function cleanupUpload(?Upload $upload): void
    {
        if (! $upload instanceof Upload) {
            return;
        }

        $disk = (string) $upload->getAttribute('disk');
        $path = (string) $upload->getAttribute('path');

        if ($disk !== '' && $path !== '') {
            Storage::disk($disk)->delete($path);

            Upload::query()
                ->whereKey($upload->getKey())
                ->delete();

            Upload::query()
                ->where('disk', $disk)
                ->where('path', $path)
                ->delete();
        }
    }
}
