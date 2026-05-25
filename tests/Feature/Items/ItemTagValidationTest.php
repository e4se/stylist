<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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
                'tag_ids' => [],
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

    public function test_index_filters_by_owned_tags_with_or_semantics_inside_groups_and_and_semantics_between_groups(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $colorGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Color',
        ]);
        $seasonGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Season',
        ]);
        $foreignTagGroup = TagGroup::factory()->for($otherUser)->create();
        $blackTag = Tag::factory()->for($colorGroup)->create([
            'name' => 'Black',
        ]);
        $whiteTag = Tag::factory()->for($colorGroup)->create([
            'name' => 'White',
        ]);
        $redTag = Tag::factory()->for($colorGroup)->create([
            'name' => 'Red',
        ]);
        $summerTag = Tag::factory()->for($seasonGroup)->create([
            'name' => 'Summer',
        ]);
        $winterTag = Tag::factory()->for($seasonGroup)->create([
            'name' => 'Winter',
        ]);
        $foreignTag = Tag::factory()->for($foreignTagGroup)->create();
        $blackSummerItem = Item::factory()->for($user)->create([
            'name' => 'Black summer shirt',
            'created_at' => now()->subMinutes(5),
        ]);
        $whiteSummerItem = Item::factory()->for($user)->create([
            'name' => 'White summer shirt',
            'created_at' => now()->subMinutes(4),
        ]);
        $blackWinterItem = Item::factory()->for($user)->create([
            'name' => 'Black winter coat',
            'created_at' => now()->subMinutes(3),
        ]);
        $redSummerItem = Item::factory()->for($user)->create([
            'name' => 'Red summer shirt',
            'created_at' => now()->subMinutes(2),
        ]);
        Item::factory()->for($user)->create([
            'name' => 'Untagged jacket',
            'created_at' => now()->subMinute(),
        ]);
        Item::factory()->for($otherUser)->create([
            'name' => 'Hidden black summer shirt',
        ]);

        $blackSummerItem->tags()->attach([$blackTag->id, $summerTag->id]);
        $whiteSummerItem->tags()->attach([$whiteTag->id, $summerTag->id]);
        $blackWinterItem->tags()->attach([$blackTag->id, $winterTag->id]);
        $redSummerItem->tags()->attach([$redTag->id, $summerTag->id]);

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', ['tag_ids' => [$blackTag->id, $whiteTag->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->has('filters.tag_ids', 2)
                ->where('filters.tag_ids.0', $blackTag->id)
                ->where('filters.tag_ids.1', $whiteTag->id)
                ->where(
                    'items.data',
                    fn (Collection $items): bool => $items->pluck('id')->all() === [
                        $blackWinterItem->id,
                        $whiteSummerItem->id,
                        $blackSummerItem->id,
                    ],
                ),
            );

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', ['tag_ids' => [$blackTag->id, $whiteTag->id, $summerTag->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->has('filters.tag_ids', 3)
                ->where('filters.tag_ids.0', $blackTag->id)
                ->where('filters.tag_ids.1', $whiteTag->id)
                ->where('filters.tag_ids.2', $summerTag->id)
                ->where(
                    'items.data',
                    fn (Collection $items): bool => $items->pluck('id')->all() === [
                        $whiteSummerItem->id,
                        $blackSummerItem->id,
                    ],
                ),
            );

        $this
            ->actingAs($user)
            ->getJson(route('wardrobe.index', ['tag_ids' => [$foreignTag->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.0');
    }

    public function test_index_pagination_links_keep_active_tag_filters(): void
    {
        $user = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $tag = Tag::factory()->for($tagGroup)->create();

        foreach (range(1, 13) as $itemNumber) {
            $item = Item::factory()->for($user)->create([
                'name' => "Tagged item {$itemNumber}",
                'created_at' => now()->subMinutes($itemNumber),
            ]);

            $item->tags()->attach($tag);
        }

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', ['tag_ids' => [$tag->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->has('items.data', 12)
                ->where('filters.tag_ids.0', $tag->id)
                ->where(
                    'items.next_page_url',
                    fn (?string $url): bool => is_string($url)
                        && str_contains($url, 'page=2')
                        && str_contains($url, 'tag_ids%5B0%5D='.$tag->id),
                ),
            );
    }
}
