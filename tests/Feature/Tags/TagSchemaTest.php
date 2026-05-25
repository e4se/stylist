<?php

namespace Tests\Feature\Tags;

use App\Models\Item;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_groups_tags_and_item_assignments_are_persisted_with_relations(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();
        $tagGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Style',
        ]);
        $tag = Tag::factory()->for($tagGroup)->create([
            'name' => 'Formal',
        ]);

        $item->tags()->attach($tag);

        $this->assertModelExists($tagGroup);
        $this->assertModelExists($tag);
        $this->assertTrue(Str::isUuid($tagGroup->id));
        $this->assertTrue(Str::isUuid($tag->id));
        $this->assertSame($user->id, $tagGroup->user_id);
        $this->assertSame($tagGroup->id, $tag->tag_group_id);
        $this->assertTrue($tagGroup->user->is($user));
        $this->assertTrue($tag->tagGroup->is($tagGroup));
        $this->assertTrue($user->tagGroups()->whereKey($tagGroup->id)->exists());
        $this->assertTrue($tagGroup->tags()->whereKey($tag->id)->exists());
        $this->assertTrue($item->tags()->whereKey($tag->id)->exists());
        $this->assertTrue($tag->items()->whereKey($item->id)->exists());
        $this->assertNotNull($item->tags()->firstOrFail()->pivot->getAttribute('created_at'));
        $this->assertNotNull($tag->items()->firstOrFail()->pivot->getAttribute('updated_at'));
    }

    public function test_tag_group_names_must_be_unique_for_each_user(): void
    {
        $user = User::factory()->create();
        TagGroup::factory()->for($user)->create([
            'name' => 'Season',
        ]);

        $this->expectException(QueryException::class);

        TagGroup::factory()->for($user)->create([
            'name' => 'Season',
        ]);
    }

    public function test_tag_group_names_may_repeat_for_different_users(): void
    {
        $otherUser = User::factory()->create();
        TagGroup::factory()->create([
            'name' => 'Season',
        ]);
        TagGroup::factory()->for($otherUser)->create([
            'name' => 'Season',
        ]);

        $this->assertSame(2, TagGroup::where('name', 'Season')->count());
    }

    public function test_tag_names_must_be_unique_for_each_group(): void
    {
        $tagGroup = TagGroup::factory()->create();
        Tag::factory()->for($tagGroup)->create([
            'name' => 'Summer',
        ]);

        $this->expectException(QueryException::class);

        Tag::factory()->for($tagGroup)->create([
            'name' => 'Summer',
        ]);
    }

    public function test_tag_names_may_repeat_for_different_groups(): void
    {
        $tagGroup = TagGroup::factory()->create();
        $otherTagGroup = TagGroup::factory()->create([
            'name' => 'Color',
        ]);

        Tag::factory()->for($tagGroup)->create([
            'name' => 'Summer',
        ]);
        Tag::factory()->for($otherTagGroup)->create([
            'name' => 'Summer',
        ]);

        $this->assertSame(2, Tag::where('name', 'Summer')->count());
    }

    public function test_item_tag_pairs_must_be_unique(): void
    {
        $item = Item::factory()->create();
        $tag = Tag::factory()->create();

        $item->tags()->attach($tag);

        $this->expectException(QueryException::class);

        $item->tags()->attach($tag);
    }

    public function test_tag_entities_soft_delete_and_cascade_when_owner_or_group_is_deleted(): void
    {
        $user = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $tag = Tag::factory()->for($tagGroup)->create();

        $tag->delete();
        $tagGroup->delete();

        $this->assertSoftDeleted($tag);
        $this->assertSoftDeleted($tagGroup);

        $cascadeUser = User::factory()->create();
        $cascadeItem = Item::factory()->for($cascadeUser)->create();
        $cascadeGroup = TagGroup::factory()->for($cascadeUser)->create();
        $cascadeTag = Tag::factory()->for($cascadeGroup)->create();

        $cascadeItem->tags()->attach($cascadeTag);
        $cascadeUser->delete();

        $this->assertDatabaseMissing('tag_groups', ['id' => $cascadeGroup->id]);
        $this->assertDatabaseMissing('tags', ['id' => $cascadeTag->id]);
        $this->assertDatabaseMissing('item_tag', [
            'item_id' => $cascadeItem->id,
            'tag_id' => $cascadeTag->id,
        ]);
    }
}
