<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
