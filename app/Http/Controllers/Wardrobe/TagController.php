<?php

namespace App\Http\Controllers\Wardrobe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tags\StoreTagRequest;
use App\Http\Requests\Tags\UpdateTagRequest;
use App\Models\Tag;
use App\Models\TagGroup;
use Illuminate\Http\RedirectResponse;

class TagController extends Controller
{
    /**
     * Store a new tag in a wardrobe tag group.
     */
    public function store(StoreTagRequest $request, TagGroup $tagGroup): RedirectResponse
    {
        $this->authorize('create', [Tag::class, $tagGroup]);

        $tagGroup->tags()->create($this->tagAttributes($request));

        return to_route('wardrobe.tags.index');
    }

    /**
     * Update an existing wardrobe tag.
     */
    public function update(UpdateTagRequest $request, TagGroup $tagGroup, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $tag->update($this->tagAttributes($request));

        return to_route('wardrobe.tags.index');
    }

    /**
     * Delete a wardrobe tag and cascade item assignments from the database.
     */
    public function destroy(TagGroup $tagGroup, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        $tag->forceDelete();

        return to_route('wardrobe.tags.index');
    }

    /**
     * Get validated tag attributes.
     *
     * @return array{name: string}
     */
    private function tagAttributes(StoreTagRequest|UpdateTagRequest $request): array
    {
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
        ];
    }
}
