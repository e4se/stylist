<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_are_persisted_with_uuid_owner_and_soft_delete_behavior(): void
    {
        $user = User::factory()->create();

        $item = Item::factory()->for($user)->create([
            'name' => 'Linen shirt',
            'description' => 'Lightweight summer layer.',
        ]);

        $this->assertModelExists($item);
        $this->assertTrue(Str::isUuid($item->id));
        $this->assertSame($user->id, $item->user_id);
        $this->assertTrue($item->user->is($user));
        $this->assertTrue($user->items()->whereKey($item->id)->exists());
        $this->assertSame('Linen shirt', $item->name);
        $this->assertSame('Lightweight summer layer.', $item->description);

        $item->delete();

        $this->assertSoftDeleted($item);
        $this->assertNull(Item::find($item->id));
        $this->assertTrue(Item::withTrashed()->whereKey($item->id)->exists());
    }

    public function test_item_embedding_columns_are_available_in_sqlite_test_migrations(): void
    {
        $columns = Schema::getColumnListing('items');

        $this->assertContains('embedding', $columns);
        $this->assertContains('embedding_source_hash', $columns);
        $this->assertContains('embedding_generated_at', $columns);
    }

    public function test_item_embedding_metadata_is_cast_without_being_user_fillable(): void
    {
        $generatedAt = now()->subMinute();
        $item = Item::factory()->create();

        $this->assertFalse($item->isFillable('embedding'));
        $this->assertFalse($item->isFillable('embedding_source_hash'));
        $this->assertFalse($item->isFillable('embedding_generated_at'));

        $item->forceFill([
            'embedding' => [0.1, -0.2, 0.3],
            'embedding_source_hash' => hash('sha256', $item->embeddingInput()),
            'embedding_generated_at' => $generatedAt,
        ])->save();

        $item->refresh();
        $embeddingGeneratedAt = $item->getAttribute('embedding_generated_at');

        $this->assertSame([0.1, -0.2, 0.3], $item->getAttribute('embedding'));
        $this->assertSame(hash('sha256', $item->embeddingInput()), $item->getAttribute('embedding_source_hash'));
        $this->assertInstanceOf(CarbonInterface::class, $embeddingGeneratedAt);
        $this->assertTrue($embeddingGeneratedAt->isSameSecond($generatedAt));
    }

    public function test_item_embedding_input_uses_name_and_description(): void
    {
        $item = new Item([
            'name' => ' Linen shirt ',
            'description' => ' Lightweight summer layer. ',
        ]);

        $this->assertSame("Linen shirt\n\nLightweight summer layer.", $item->embeddingInput());

        $itemWithoutDescription = new Item([
            'name' => 'Linen shirt',
            'description' => ' ',
        ]);

        $this->assertSame('Linen shirt', $itemWithoutDescription->embeddingInput());
    }
}
