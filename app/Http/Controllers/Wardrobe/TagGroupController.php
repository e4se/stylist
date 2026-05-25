<?php

namespace App\Http\Controllers\Wardrobe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tags\StoreTagGroupRequest;
use App\Http\Requests\Tags\UpdateTagGroupRequest;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagGroupController extends Controller
{
    /**
     * Show the authenticated user's wardrobe tag groups and tags.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TagGroup::class);
        $this->authorize('viewAny', Tag::class);

        /** @var User $user */
        $user = $request->user();

        $tagGroups = $user->tagGroups()
            ->with(['tags' => fn ($query) => $query->orderBy('name')->orderBy('id')])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (TagGroup $tagGroup): array => $this->tagGroupData($tagGroup))
            ->values()
            ->all();

        return Inertia::render('wardrobe/tags/index', [
            'tagGroups' => $tagGroups,
        ]);
    }

    /**
     * Store a new wardrobe tag group.
     */
    public function store(StoreTagGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', TagGroup::class);

        /** @var User $user */
        $user = $request->user();
        $user->tagGroups()->create($this->tagGroupAttributes($request));

        return to_route('wardrobe.tags.index');
    }

    /**
     * Update an existing wardrobe tag group.
     */
    public function update(UpdateTagGroupRequest $request, TagGroup $tagGroup): RedirectResponse
    {
        $this->authorize('update', $tagGroup);

        $tagGroup->update($this->tagGroupAttributes($request));

        return to_route('wardrobe.tags.index');
    }

    /**
     * Delete a wardrobe tag group and cascade its tags from the database.
     */
    public function destroy(TagGroup $tagGroup): RedirectResponse
    {
        $this->authorize('delete', $tagGroup);

        $tagGroup->forceDelete();

        return to_route('wardrobe.tags.index');
    }

    /**
     * Get validated tag group attributes.
     *
     * @return array{name: string}
     */
    private function tagGroupAttributes(StoreTagGroupRequest|UpdateTagGroupRequest $request): array
    {
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
        ];
    }

    /**
     * Format a tag group for the Inertia tag management page.
     *
     * @return array{id: string, name: string, tags: list<array{id: string, tag_group_id: string, name: string}>}
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
                ])
                ->values()
                ->all(),
        ];
    }
}
