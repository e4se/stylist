<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tag_group_id', 'name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Get the group that owns the tag.
     *
     * @return BelongsTo<TagGroup, $this>
     */
    public function tagGroup(): BelongsTo
    {
        return $this->belongsTo(TagGroup::class, 'tag_group_id');
    }

    /**
     * Get the items assigned to the tag.
     *
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_tag')
            ->withTimestamps();
    }
}
