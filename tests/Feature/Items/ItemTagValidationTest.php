<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItemTagValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_can_be_created_updated_and_cleared_with_owned_tags(): void
    {
        $user = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $firstTag = Tag::factory()->for($tagGroup)->create();
        $secondTag = Tag::factory()->for($tagGroup)->create();

        $this
            ->actingAs($user)
            ->post(route('wardrobe.items.store'), [
                'name' => 'Black jacket',
                'description' => null,
                'tag_ids' => [$firstTag->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $item = $user->items()->sole();

        $this->assertTrue($item->tags()->whereKey($firstTag->id)->exists());
        $this->assertFalse($item->tags()->whereKey($secondTag->id)->exists());

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Updated jacket',
                'description' => 'Updated description.',
                'tag_ids' => [$firstTag->id, $secondTag->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $this->assertTrue($item->tags()->whereKey($firstTag->id)->exists());
        $this->assertTrue($item->tags()->whereKey($secondTag->id)->exists());

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Updated jacket',
                'description' => 'Updated description.',
                'tag_ids' => [$secondTag->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $this->assertFalse($item->tags()->whereKey($firstTag->id)->exists());
        $this->assertTrue($item->tags()->whereKey($secondTag->id)->exists());
        $this->assertDatabaseMissing('item_tag', [
            'item_id' => $item->id,
            'tag_id' => $firstTag->id,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Updated jacket',
                'description' => 'Updated description.',
                'tag_ids' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $this->assertFalse($item->tags()->exists());
        $this->assertDatabaseMissing('item_tag', [
            'item_id' => $item->id,
            'tag_id' => $secondTag->id,
        ]);
    }

    public function test_item_store_and_update_requests_reject_invalid_duplicate_and_foreign_tag_ids(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $foreignTagGroup = TagGroup::factory()->for($otherUser)->create();
        $ownedTag = Tag::factory()->for($tagGroup)->create();
        $foreignTag = Tag::factory()->for($foreignTagGroup)->create();
        $item = Item::factory()->for($user)->create();

        $this
            ->actingAs($user)
            ->post(route('wardrobe.items.store'), [
                'name' => 'Invalid tag item',
                'description' => null,
                'tag_ids' => ['not-a-uuid'],
            ])
            ->assertSessionHasErrors('tag_ids.0');

        $this
            ->actingAs($user)
            ->post(route('wardrobe.items.store'), [
                'name' => 'Duplicate tag item',
                'description' => null,
                'tag_ids' => [$ownedTag->id, $ownedTag->id],
            ])
            ->assertSessionHasErrors('tag_ids.0');

        $this
            ->actingAs($user)
            ->post(route('wardrobe.items.store'), [
                'name' => 'Foreign tag item',
                'description' => null,
                'tag_ids' => [$foreignTag->id],
            ])
            ->assertSessionHasErrors('tag_ids.0');

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Invalid update',
                'description' => null,
                'tag_ids' => ['not-a-uuid'],
            ])
            ->assertSessionHasErrors('tag_ids.0');

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Duplicate update',
                'description' => null,
                'tag_ids' => [$ownedTag->id, $ownedTag->id],
            ])
            ->assertSessionHasErrors('tag_ids.0');

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Foreign update',
                'description' => null,
                'tag_ids' => [$foreignTag->id],
            ])
            ->assertSessionHasErrors('tag_ids.0');

        $this->assertFalse($item->tags()->exists());
        $this->assertSame(0, $user->items()->where('name', 'Foreign tag item')->count());
    }

    public function test_index_filters_by_owned_tags_and_rejects_foreign_tag_filters(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $foreignTagGroup = TagGroup::factory()->for($otherUser)->create();
        $matchingTag = Tag::factory()->for($tagGroup)->create();
        $otherTag = Tag::factory()->for($tagGroup)->create();
        $foreignTag = Tag::factory()->for($foreignTagGroup)->create();
        $matchingItem = Item::factory()->for($user)->create([
            'name' => 'Tagged jacket',
        ]);
        $otherItem = Item::factory()->for($user)->create([
            'name' => 'Other jacket',
        ]);

        $matchingItem->tags()->attach($matchingTag);
        $otherItem->tags()->attach($otherTag);

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', ['tag_ids' => [$matchingTag->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $matchingItem->id)
                ->where('items.data.0.name', 'Tagged jacket'),
            );

        $this
            ->actingAs($user)
            ->getJson(route('wardrobe.index', ['tag_ids' => [$foreignTag->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.0');
    }
}
