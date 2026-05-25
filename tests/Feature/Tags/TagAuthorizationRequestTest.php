<?php

namespace Tests\Feature\Tags;

use App\Enums\Role;
use App\Http\Requests\Tags\StoreTagGroupRequest;
use App\Http\Requests\Tags\StoreTagRequest;
use App\Http\Requests\Tags\UpdateTagGroupRequest;
use App\Http\Requests\Tags\UpdateTagRequest;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TagAuthorizationRequestTest extends TestCase
{
    use RefreshDatabase;

    private const string STORE_GROUP_URI = '/__tests/tag-groups';

    private const string UPDATE_GROUP_URI = '/__tests/tag-groups/';

    private const string STORE_TAG_URI = '/__tests/tags';

    private const string UPDATE_TAG_URI = '/__tests/tags/';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::post(self::STORE_GROUP_URI, static function (StoreTagGroupRequest $request): Response {
                $request->validated();

                return response()->noContent();
            });

            Route::put(self::UPDATE_GROUP_URI.'{tagGroup}', static function (UpdateTagGroupRequest $request, TagGroup $tagGroup): Response {
                $request->validated();

                return response()->noContent();
            });

            Route::post(self::STORE_TAG_URI, static function (StoreTagRequest $request): Response {
                $request->validated();

                return response()->noContent();
            });

            Route::put(self::UPDATE_TAG_URI.'{tag}', static function (UpdateTagRequest $request, Tag $tag): Response {
                $request->validated();

                return response()->noContent();
            });
        });
    }

    public function test_tag_group_policy_allows_users_to_manage_only_their_own_groups_and_admins_bypass(): void
    {
        $this->seed(RoleSeeder::class);

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($owner)->create();

        $admin->assignRole(Role::Admin);

        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', TagGroup::class));
        $this->assertTrue(Gate::forUser($owner)->allows('create', TagGroup::class));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $tagGroup));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $tagGroup));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $tagGroup));

        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $tagGroup));
        $this->assertFalse(Gate::forUser($otherUser)->allows('update', $tagGroup));
        $this->assertFalse(Gate::forUser($otherUser)->allows('delete', $tagGroup));

        $this->assertTrue(Gate::forUser($admin)->allows('update', $tagGroup));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $tagGroup));
    }

    public function test_tag_policy_allows_users_to_manage_only_their_own_tags_and_admins_bypass(): void
    {
        $this->seed(RoleSeeder::class);

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($owner)->create();
        $tag = Tag::factory()->for($tagGroup)->create();

        $admin->assignRole(Role::Admin);

        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', Tag::class));
        $this->assertTrue(Gate::forUser($owner)->allows('create', [Tag::class, $tagGroup]));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $tag));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $tag));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $tag));

        $this->assertFalse(Gate::forUser($otherUser)->allows('create', [Tag::class, $tagGroup]));
        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $tag));
        $this->assertFalse(Gate::forUser($otherUser)->allows('update', $tag));
        $this->assertFalse(Gate::forUser($otherUser)->allows('delete', $tag));

        $this->assertTrue(Gate::forUser($admin)->allows('update', $tag));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $tag));
    }

    public function test_tag_group_requests_validate_required_length_and_scoped_unique_names(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $existingTagGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Season',
        ]);
        $editableTagGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Style',
        ]);
        TagGroup::factory()->for($otherUser)->create([
            'name' => 'Palette',
        ]);

        $this
            ->actingAs($user)
            ->postTagGroup(['name' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name')
            ->assertJsonPath('errors.name.0', 'The tag group name field is required.');

        $this
            ->actingAs($user)
            ->postTagGroup(['name' => str_repeat('a', StoreTagGroupRequest::NAME_MAX_CHARACTERS + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this
            ->actingAs($user)
            ->postTagGroup(['name' => $existingTagGroup->name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this
            ->actingAs($user)
            ->postTagGroup(['name' => 'Palette'])
            ->assertNoContent();

        $this
            ->actingAs($user)
            ->putTagGroup($editableTagGroup, ['name' => 'Style'])
            ->assertNoContent();

        $this
            ->actingAs($user)
            ->putTagGroup($editableTagGroup, ['name' => 'Season'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this
            ->actingAs($otherUser)
            ->putTagGroup($editableTagGroup, ['name' => 'Outerwear'])
            ->assertForbidden();
    }

    public function test_tag_requests_validate_group_ownership_and_scoped_unique_names(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $alternateTagGroup = TagGroup::factory()->for($user)->create();
        $foreignTagGroup = TagGroup::factory()->for($otherUser)->create();
        $existingTag = Tag::factory()->for($tagGroup)->create([
            'name' => 'Formal',
        ]);
        $editableTag = Tag::factory()->for($tagGroup)->create([
            'name' => 'Casual',
        ]);

        $this
            ->actingAs($user)
            ->postTag(['tag_group_id' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_group_id')
            ->assertJsonPath('errors.tag_group_id.0', 'The tag group field is required.');

        $this
            ->actingAs($user)
            ->postTag([
                'tag_group_id' => $tagGroup->id,
                'name' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name')
            ->assertJsonPath('errors.name.0', 'The tag name field is required.');

        $this
            ->actingAs($user)
            ->postTag(['tag_group_id' => $foreignTagGroup->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_group_id');

        $this
            ->actingAs($user)
            ->postTag([
                'tag_group_id' => $tagGroup->id,
                'name' => $existingTag->name,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this
            ->actingAs($user)
            ->postTag([
                'tag_group_id' => $alternateTagGroup->id,
                'name' => $existingTag->name,
            ])
            ->assertNoContent();

        $this
            ->actingAs($user)
            ->putTag($editableTag, ['name' => 'Casual'])
            ->assertNoContent();

        $this
            ->actingAs($user)
            ->putTag($editableTag, ['name' => 'Formal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this
            ->actingAs($otherUser)
            ->putTag($editableTag, ['name' => 'Hidden'])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postTagGroup(array $overrides = []): TestResponse
    {
        return $this->postJson(self::STORE_GROUP_URI, [
            'name' => 'Season',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function putTagGroup(TagGroup $tagGroup, array $overrides = []): TestResponse
    {
        return $this->putJson(self::UPDATE_GROUP_URI.$tagGroup->id, [
            'name' => 'Season',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postTag(array $overrides = []): TestResponse
    {
        return $this->postJson(self::STORE_TAG_URI, [
            'tag_group_id' => TagGroup::factory()->create()->id,
            'name' => 'Formal',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function putTag(Tag $tag, array $overrides = []): TestResponse
    {
        return $this->putJson(self::UPDATE_TAG_URI.$tag->id, [
            'name' => 'Formal',
            ...$overrides,
        ]);
    }
}
