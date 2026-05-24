<?php

namespace App\Models;

use App\Enums\ItemUploadType;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'description'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Get the user that owns the item.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the uploads attached to the item.
     *
     * @return MorphToMany<Upload, $this>
     */
    public function uploads(): MorphToMany
    {
        return $this->morphToMany(Upload::class, 'uploadable', 'uploadables')
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Get the item's main upload.
     *
     * @return MorphToMany<Upload, $this>
     */
    public function mainUpload(): MorphToMany
    {
        return $this->uploads()
            ->withPivotValue('type', ItemUploadType::Main->value);
    }
}
