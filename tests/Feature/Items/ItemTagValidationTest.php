<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\TestCase;

class ItemTagValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeEmbeddings();
    }

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

    public function test_index_accepts_search_filter_and_exposes_it_in_filters_prop(): void
    {
        $user = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $tag = Tag::factory()->for($tagGroup)->create();
        $embedding = $this->searchEmbedding(1.0);
        $item = Item::factory()->for($user)->create([
            'name' => 'Linen blazer',
        ]);

        Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => [$embedding])->preventStrayEmbeddings();
        $this->withEmbedding($item, $embedding);
        $item->tags()->attach($tag);

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', [
                'tag_ids' => [$tag->id],
                'search' => 'linen blazer',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->where('filters.search', 'linen blazer')
                ->has('filters.tag_ids', 1)
                ->where('filters.tag_ids.0', $tag->id)
                ->where('items.data.0.id', $item->id),
            );

        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('linen blazer')
            && $prompt->dimensions === 1536);
    }

    public function test_index_rejects_invalid_search_filters(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson(route('wardrobe.index', ['search' => ['linen']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');

        $this
            ->actingAs($user)
            ->getJson(route('wardrobe.index', ['search' => str_repeat('a', 201)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function test_index_keeps_latest_ordering_and_skips_embedding_generation_for_blank_search(): void
    {
        $user = User::factory()->create();
        $olderItem = Item::factory()->for($user)->create([
            'name' => 'Older item',
            'created_at' => now()->subMinutes(2),
        ]);
        $newerItem = Item::factory()->for($user)->create([
            'name' => 'Newer item',
            'created_at' => now()->subMinute(),
        ]);

        Embeddings::fake()->preventStrayEmbeddings();

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', ['search' => '   ']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->where('filters.search', null)
                ->where(
                    'items.data',
                    fn (Collection $items): bool => $items->pluck('id')->all() === [
                        $newerItem->id,
                        $olderItem->id,
                    ],
                ),
            );

        Embeddings::assertNothingGenerated();
    }

    public function test_index_orders_embedded_items_by_search_embedding_distance(): void
    {
        $user = User::factory()->create();
        $queryEmbedding = $this->searchEmbedding(1.0);
        $generatedCount = 0;
        $exactItem = $this->withEmbedding(Item::factory()->for($user)->create([
            'id' => '00000000-0000-4000-8000-000000000001',
            'name' => 'Exact semantic match',
            'created_at' => now()->subDays(4),
        ]), $this->searchEmbedding(1.0));
        $secondTieItem = $this->withEmbedding(Item::factory()->for($user)->create([
            'id' => '00000000-0000-4000-8000-000000000003',
            'name' => 'Second equal match',
            'created_at' => now(),
        ]), $this->searchEmbedding(0.8, 0.2));
        $firstTieItem = $this->withEmbedding(Item::factory()->for($user)->create([
            'id' => '00000000-0000-4000-8000-000000000002',
            'name' => 'First equal match',
            'created_at' => now()->subDay(),
        ]), $this->searchEmbedding(0.8, 0.2));
        $farItem = $this->withEmbedding(Item::factory()->for($user)->create([
            'id' => '00000000-0000-4000-8000-000000000004',
            'name' => 'Far semantic match',
            'created_at' => now()->subDays(2),
        ]), $this->searchEmbedding(0.0, 1.0));

        Item::factory()->for($user)->create([
            'name' => 'Unembedded match',
            'created_at' => now()->addDay(),
        ]);

        Embeddings::fake(function (EmbeddingsPrompt $prompt) use ($queryEmbedding, &$generatedCount): array {
            $generatedCount++;

            return [$queryEmbedding];
        })->preventStrayEmbeddings();

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', ['search' => 'black layers']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->where(
                    'items.data',
                    fn (Collection $items): bool => $items->pluck('id')->all() === [
                        $exactItem->id,
                        $firstTieItem->id,
                        $secondTieItem->id,
                        $farItem->id,
                    ],
                ),
            );

        $this->assertSame(1, $generatedCount);
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('black layers')
            && $prompt->dimensions === 1536);
    }

    public function test_index_combines_tag_filters_with_search_ranking(): void
    {
        $user = User::factory()->create();
        $colorGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Color',
        ]);
        $seasonGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Season',
        ]);
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
        $queryEmbedding = $this->searchEmbedding(1.0);
        $whiteSummerItem = $this->withEmbedding(Item::factory()->for($user)->create([
            'name' => 'White summer shirt',
        ]), $this->searchEmbedding(1.0));
        $blackSummerItem = $this->withEmbedding(Item::factory()->for($user)->create([
            'name' => 'Black summer shirt',
        ]), $this->searchEmbedding(0.8, 0.2));
        $blackWinterItem = $this->withEmbedding(Item::factory()->for($user)->create([
            'name' => 'Black winter coat',
        ]), $this->searchEmbedding(1.0));
        $redSummerItem = $this->withEmbedding(Item::factory()->for($user)->create([
            'name' => 'Red summer shirt',
        ]), $this->searchEmbedding(1.0));

        $whiteSummerItem->tags()->attach([$whiteTag->id, $summerTag->id]);
        $blackSummerItem->tags()->attach([$blackTag->id, $summerTag->id]);
        $blackWinterItem->tags()->attach([$blackTag->id, $winterTag->id]);
        $redSummerItem->tags()->attach([$redTag->id, $summerTag->id]);

        Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => [$queryEmbedding])->preventStrayEmbeddings();

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', [
                'tag_ids' => [$blackTag->id, $whiteTag->id, $summerTag->id],
                'search' => 'summer neutral',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->where(
                    'items.data',
                    fn (Collection $items): bool => $items->pluck('id')->all() === [
                        $whiteSummerItem->id,
                        $blackSummerItem->id,
                    ],
                ),
            );
    }

    public function test_index_pagination_links_keep_active_search_and_tag_filters(): void
    {
        $user = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $tag = Tag::factory()->for($tagGroup)->create();
        $embedding = $this->searchEmbedding(1.0);

        Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => [$embedding])->preventStrayEmbeddings();

        foreach (range(1, 13) as $itemNumber) {
            $item = Item::factory()->for($user)->create([
                'name' => "Tagged item {$itemNumber}",
                'created_at' => now()->subMinutes($itemNumber),
            ]);

            $this->withEmbedding($item, $embedding);
            $item->tags()->attach($tag);
        }

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index', [
                'tag_ids' => [$tag->id],
                'search' => 'tagged item',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->has('items.data', 12)
                ->where('filters.tag_ids.0', $tag->id)
                ->where('filters.search', 'tagged item')
                ->where(
                    'items.next_page_url',
                    fn (?string $url): bool => $this->paginationUrlKeepsFilters($url, $tag->id, 'tagged item'),
                ),
            );
    }

    private function fakeEmbeddings(): void
    {
        $embedding = array_fill(0, 1536, 0.125);

        Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => [$embedding])->preventStrayEmbeddings();
    }

    /**
     * @param  list<float>  $embedding
     */
    private function withEmbedding(Item $item, array $embedding): Item
    {
        $item->forceFill([
            'embedding' => $embedding,
            'embedding_source_hash' => hash('sha256', $item->embeddingInput()),
            'embedding_generated_at' => now(),
        ])->save();

        return $item;
    }

    /**
     * @return list<float>
     */
    private function searchEmbedding(float $first, float $second = 0.0): array
    {
        $embedding = array_fill(0, 1536, 0.0);
        $embedding[0] = $first;
        $embedding[1] = $second;

        return $embedding;
    }

    private function paginationUrlKeepsFilters(?string $url, string $tagId, string $search): bool
    {
        if (! is_string($url)) {
            return false;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return ($query['page'] ?? null) === '2'
            && ($query['search'] ?? null) === $search
            && ($query['tag_ids'] ?? null) === [$tagId];
    }
}
