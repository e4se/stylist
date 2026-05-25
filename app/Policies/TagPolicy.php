<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;

class TagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tag $tag): bool
    {
        return $this->ownsTag($user, $tag);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?TagGroup $tagGroup = null): bool
    {
        return $tagGroup === null || $this->ownsTagGroup($user, $tagGroup);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tag $tag): bool
    {
        return $this->ownsTag($user, $tag);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tag $tag): bool
    {
        return $this->ownsTag($user, $tag);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Tag $tag): bool
    {
        return $this->ownsTag($user, $tag);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Tag $tag): bool
    {
        return $this->ownsTag($user, $tag);
    }

    /**
     * Determine whether the user owns the tag.
     */
    private function ownsTag(User $user, Tag $tag): bool
    {
        return TagGroup::query()
            ->whereKey($tag->getAttribute('tag_group_id'))
            ->where('user_id', $user->getKey())
            ->exists();
    }

    /**
     * Determine whether the user owns the tag group.
     */
    private function ownsTagGroup(User $user, TagGroup $tagGroup): bool
    {
        return $user->id === $tagGroup->user_id;
    }
}
