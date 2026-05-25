<?php

namespace Tests\Feature\Tags;

use App\Models\Item;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TagManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_wardrobe_tag_routes_are_registered_for_authenticated_verified_users(): void
    {
        $this->assertRoute('wardrobe.tags.index', ['GET', 'HEAD'], 'wardrobe/tags');
        $this->assertRoute('wardrobe.tag-groups.store', ['POST'], 'wardrobe/tag-groups');
        $this->assertRoute('wardrobe.tag-groups.update', ['PUT', 'PATCH'], 'wardrobe/tag-groups/{tagGroup}');
        $this->assertRoute('wardrobe.tag-groups.destroy', ['DELETE'], 'wardrobe/tag-groups/{tagGroup}');
        $this->assertRoute('wardrobe.tag-groups.tags.store', ['POST'], 'wardrobe/tag-groups/{tagGroup}/tags');
        $this->assertRoute('wardrobe.tag-groups.tags.update', ['PUT', 'PATCH'], 'wardrobe/tag-groups/{tagGroup}/tags/{tag}');
        $this->assertRoute('wardrobe.tag-groups.tags.destroy', ['DELETE'], 'wardrobe/tag-groups/{tagGroup}/tags/{tag}');
    }

    public function test_guests_are_redirected_from_wardrobe_tag_routes(): void
    {
        $tagGroup = TagGroup::factory()->create();
        $tag = Tag::factory()->for($tagGroup)->create();

        $this->get(route('wardrobe.tags.index'))->assertRedirect(route('login'));

        $this
            ->post(route('wardrobe.tag-groups.store'), ['name' => 'Guest group'])
            ->assertRedirect(route('login'));

        $this
            ->patch(route('wardrobe.tag-groups.update', $tagGroup), ['name' => 'Guest group'])
            ->assertRedirect(route('login'));

        $this
            ->delete(route('wardrobe.tag-groups.destroy', $tagGroup))
            ->assertRedirect(route('login'));

        $this
            ->post(route('wardrobe.tag-groups.tags.store', $tagGroup), ['name' => 'Guest tag'])
            ->assertRedirect(route('login'));

        $this
            ->patch(route('wardrobe.tag-groups.tags.update', [$tagGroup, $tag]), ['name' => 'Guest tag'])
            ->assertRedirect(route('login'));

        $this
            ->delete(route('wardrobe.tag-groups.tags.destroy', [$tagGroup, $tag]))
            ->assertRedirect(route('login'));
    }

    public function test_index_renders_only_the_authenticated_users_tag_groups_and_tags(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $colorGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Color',
        ]);
        $seasonGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Season',
        ]);
        $summerTag = Tag::factory()->for($seasonGroup)->create([
            'name' => 'Summer',
        ]);
        Tag::factory()->for($seasonGroup)->create([
            'name' => 'Winter',
        ]);
        $foreignGroup = TagGroup::factory()->for($otherUser)->create([
            'name' => 'Hidden',
        ]);
        Tag::factory()->for($foreignGroup)->create([
            'name' => 'Private',
        ]);

        $this
            ->actingAs($user)
            ->get(route('wardrobe.tags.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/tags/index')
                ->has('tagGroups', 2)
                ->whereType('tagGroups.0.id', 'string')
                ->whereType('tagGroups.0.name', 'string')
                ->whereType('tagGroups.0.tags', 'array')
                ->where('tagGroups.0.id', $colorGroup->id)
                ->where('tagGroups.0.name', 'Color')
                ->has('tagGroups.0.tags', 0)
                ->where('tagGroups.1.id', $seasonGroup->id)
                ->where('tagGroups.1.name', 'Season')
                ->has('tagGroups.1.tags', 2)
                ->whereType('tagGroups.1.tags.0.id', 'string')
                ->whereType('tagGroups.1.tags.0.tag_group_id', 'string')
                ->whereType('tagGroups.1.tags.0.name', 'string')
                ->where('tagGroups.1.tags.0.id', $summerTag->id)
                ->where('tagGroups.1.tags.0.tag_group_id', $seasonGroup->id)
                ->where('tagGroups.1.tags.0.name', 'Summer'),
            );
    }

    public function test_tag_group_mutations_redirect_to_tag_index_and_delete_cascades_children(): void
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

        $this
            ->actingAs($user)
            ->post(route('wardrobe.tag-groups.store'), [
                'name' => 'Color',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.tags.index'));

        $createdTagGroup = $user->tagGroups()->where('name', 'Color')->sole();

        $this->assertSame('Color', $createdTagGroup->name);

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.tag-groups.update', $createdTagGroup), [
                'name' => 'Palette',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.tags.index'));

        $this->assertSame('Palette', $createdTagGroup->refresh()->name);

        $this
            ->actingAs($user)
            ->delete(route('wardrobe.tag-groups.destroy', $tagGroup))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.tags.index'));

        $this->assertDatabaseMissing('tag_groups', ['id' => $tagGroup->id]);
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('item_tag', [
            'item_id' => $item->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_tag_mutations_redirect_to_tag_index_and_delete_cascades_item_assignments(): void
    {
        $user = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Style',
        ]);
        $item = Item::factory()->for($user)->create();

        $this
            ->actingAs($user)
            ->post(route('wardrobe.tag-groups.tags.store', $tagGroup), [
                'name' => 'Casual',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.tags.index'));

        $tag = $tagGroup->tags()->where('name', 'Casual')->sole();

        $this->assertSame('Casual', $tag->name);

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.tag-groups.tags.update', [$tagGroup, $tag]), [
                'name' => 'Weekend',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.tags.index'));

        $this->assertSame('Weekend', $tag->refresh()->name);

        $item->tags()->attach($tag);

        $this
            ->actingAs($user)
            ->delete(route('wardrobe.tag-groups.tags.destroy', [$tagGroup, $tag]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.tags.index'));

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('item_tag', [
            'item_id' => $item->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_mutations_do_not_expose_or_change_another_users_tag_records(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownedGroup = TagGroup::factory()->for($user)->create();
        $foreignGroup = TagGroup::factory()->for($otherUser)->create([
            'name' => 'Private group',
        ]);
        $foreignTag = Tag::factory()->for($foreignGroup)->create([
            'name' => 'Private tag',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.tag-groups.update', $foreignGroup), [
                'name' => 'Stolen group',
            ])
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->delete(route('wardrobe.tag-groups.destroy', $foreignGroup))
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->post(route('wardrobe.tag-groups.tags.store', $foreignGroup), [
                'name' => 'Stolen tag',
            ])
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.tag-groups.tags.update', [$foreignGroup, $foreignTag]), [
                'name' => 'Stolen tag',
            ])
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->delete(route('wardrobe.tag-groups.tags.destroy', [$foreignGroup, $foreignTag]))
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.tag-groups.tags.update', [$ownedGroup, $foreignTag]), [
                'name' => 'Leaked tag',
            ])
            ->assertNotFound();

        $this->assertSame('Private group', $foreignGroup->refresh()->name);
        $this->assertSame('Private tag', $foreignTag->refresh()->name);
        $this->assertFalse($foreignGroup->tags()->where('name', 'Stolen tag')->exists());
    }

    /**
     * @param  list<string>  $methods
     */
    private function assertRoute(string $name, array $methods, string $uri): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertInstanceOf(RoutingRoute::class, $route);
        $this->assertSame($methods, $route->methods());
        $this->assertSame($uri, $route->uri());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('verified', $route->gatherMiddleware());
    }
}
