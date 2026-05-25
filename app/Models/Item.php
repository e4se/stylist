<?php

namespace App\Models;

use App\Enums\ItemUploadType;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'description'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Get the text that should be embedded for semantic item search.
     */
    public function embeddingInput(): string
    {
        $parts = array_filter([
            trim((string) $this->name),
            trim((string) $this->description),
        ], static fn (string $part): bool => $part !== '');

        return implode("\n\n", $parts);
    }

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
     * Get the tags assigned to the item.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'item_tag')
            ->withTimestamps();
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedding_generated_at' => 'datetime',
        ];
    }
}
