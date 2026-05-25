<?php

namespace Tests\Feature\Wardrobe;

use App\Enums\ItemUploadType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItemCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_wardrobe_crud_routes(): void
    {
        $item = Item::factory()->create();

        $this->get(route('wardrobe.index'))->assertRedirect(route('login'));

        $this
            ->post(route('wardrobe.items.store'), [
                'name' => 'Guest jacket',
            ])
            ->assertRedirect(route('login'));

        $this
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Guest jacket',
            ])
            ->assertRedirect(route('login'));

        $this
            ->delete(route('wardrobe.items.destroy', $item))
            ->assertRedirect(route('login'));
    }

    public function test_index_returns_only_the_owner_items_with_frontend_type_contracts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = Item::factory()->for($user)->create([
            'name' => 'Linen shirt',
            'description' => 'Lightweight summer layer.',
        ]);
        $upload = Upload::factory()->for($user)->create([
            'name' => 'linen-shirt.jpg',
            'disk' => 'local',
            'driver' => 'local',
            'path' => 'uploads/linen-shirt.jpg',
        ]);
        $tagGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Color',
        ]);
        $tag = Tag::factory()->for($tagGroup)->create([
            'name' => 'Black',
        ]);
        $foreignTagGroup = TagGroup::factory()->for($otherUser)->create([
            'name' => 'Hidden',
        ]);
        Tag::factory()->for($foreignTagGroup)->create([
            'name' => 'Private',
        ]);
        $item->mainUpload()->attach($upload);
        $item->tags()->attach($tag);
        Item::factory()->for($otherUser)->create([
            'name' => 'Hidden coat',
        ]);

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wardrobe/index')
                ->has('items.data', 1)
                ->whereType('items.data.0.id', 'string')
                ->whereType('items.data.0.name', 'string')
                ->whereType('items.data.0.description', 'string|null')
                ->whereType('items.data.0.main_upload', 'array')
                ->whereType('items.data.0.tags', 'array')
                ->where('items.data.0.id', $item->id)
                ->where('items.data.0.name', 'Linen shirt')
                ->where('items.data.0.description', 'Lightweight summer layer.')
                ->has('items.data.0.main_upload', 1)
                ->whereType('items.data.0.main_upload.0.id', 'string')
                ->whereType('items.data.0.main_upload.0.name', 'string')
                ->whereType('items.data.0.main_upload.0.url', 'string')
                ->where('items.data.0.main_upload.0.id', $upload->id)
                ->where('items.data.0.main_upload.0.name', 'linen-shirt.jpg')
                ->where('items.data.0.main_upload.0.url', '/storage/uploads/linen-shirt.jpg')
                ->has('items.data.0.tags', 1)
                ->whereType('items.data.0.tags.0.id', 'string')
                ->whereType('items.data.0.tags.0.name', 'string')
                ->whereType('items.data.0.tags.0.tag_group', 'array')
                ->whereType('items.data.0.tags.0.tag_group.id', 'string')
                ->whereType('items.data.0.tags.0.tag_group.name', 'string')
                ->where('items.data.0.tags.0.id', $tag->id)
                ->where('items.data.0.tags.0.name', 'Black')
                ->where('items.data.0.tags.0.tag_group.id', $tagGroup->id)
                ->where('items.data.0.tags.0.tag_group.name', 'Color')
                ->has('tagGroups', 1)
                ->whereType('tagGroups.0.id', 'string')
                ->whereType('tagGroups.0.name', 'string')
                ->whereType('tagGroups.0.tags', 'array')
                ->where('tagGroups.0.id', $tagGroup->id)
                ->where('tagGroups.0.name', 'Color')
                ->has('tagGroups.0.tags', 1)
                ->whereType('tagGroups.0.tags.0.id', 'string')
                ->whereType('tagGroups.0.tags.0.tag_group_id', 'string')
                ->whereType('tagGroups.0.tags.0.name', 'string')
                ->where('tagGroups.0.tags.0.id', $tag->id)
                ->where('tagGroups.0.tags.0.tag_group_id', $tagGroup->id)
                ->where('tagGroups.0.tags.0.name', 'Black'),
            );
    }

    public function test_an_item_can_be_created_with_a_main_image(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('black-jacket.jpg');

        $this
            ->actingAs($user)
            ->post(route('wardrobe.items.store'), [
                'name' => 'Black jacket',
                'description' => 'Water resistant shell.',
                'main_upload' => $file,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $item = $user->items()->with('mainUpload')->sole();
        $upload = $item->mainUpload()->sole();

        $this->assertSame('Black jacket', $item->name);
        $this->assertSame('Water resistant shell.', $item->description);
        $this->assertSame($user->id, $upload->user_id);
        $this->assertSame('black-jacket.jpg', $upload->name);
        $this->assertTrue($upload->items()->whereKey($item->id)->exists());
        Storage::disk('local')->assertExists($upload->path);

        $this->assertDatabaseHas('uploadables', [
            'upload_id' => $upload->id,
            'uploadable_type' => Item::class,
            'uploadable_id' => $item->id,
            'type' => ItemUploadType::Main->value,
        ]);
    }

    public function test_an_item_can_be_created_with_a_previously_uploaded_main_image(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $upload = Upload::factory()->for($user)->create([
            'name' => 'linen-shirt.jpg',
            'disk' => 'local',
            'driver' => 'local',
            'path' => 'uploads/linen-shirt.jpg',
        ]);
        Storage::disk('local')->put($upload->path, 'image');

        $this
            ->actingAs($user)
            ->post(route('wardrobe.items.store'), [
                'name' => 'Linen shirt',
                'description' => 'Lightweight summer layer.',
                'main_upload_id' => $upload->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $item = $user->items()->sole();

        $this->assertTrue($item->mainUpload()->whereKey($upload->id)->exists());
        $this->assertTrue($upload->items()->whereKey($item->id)->exists());
        Storage::disk('local')->assertExists($upload->path);
    }

    public function test_an_item_can_be_updated_and_its_main_image_can_be_replaced(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create([
            'name' => 'Old jacket',
            'description' => 'Original description.',
        ]);
        $oldUpload = Upload::factory()->for($user)->create([
            'disk' => 'local',
            'driver' => 'local',
            'path' => 'uploads/old-jacket.jpg',
        ]);
        Storage::disk('local')->put($oldUpload->path, 'old image');
        $item->mainUpload()->attach($oldUpload);

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Updated jacket',
                'description' => 'Updated description.',
                'main_upload' => UploadedFile::fake()->image('updated-jacket.jpg'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $item->refresh();
        $newUpload = $item->mainUpload()->sole();

        $this->assertSame('Updated jacket', $item->name);
        $this->assertSame('Updated description.', $item->description);
        $this->assertFalse($newUpload->is($oldUpload));
        $this->assertSame('updated-jacket.jpg', $newUpload->name);
        $this->assertFalse($item->uploads()->whereKey($oldUpload->id)->exists());
        $this->assertTrue($newUpload->items()->whereKey($item->id)->exists());
        $this->assertModelExists($oldUpload);
        Storage::disk('local')->assertExists($oldUpload->path);
        Storage::disk('local')->assertExists($newUpload->path);
    }

    public function test_an_item_main_image_can_be_replaced_with_a_previously_uploaded_image(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create([
            'name' => 'Old jacket',
            'description' => 'Original description.',
        ]);
        $oldUpload = Upload::factory()->for($user)->create([
            'disk' => 'local',
            'driver' => 'local',
            'path' => 'uploads/old-jacket.jpg',
        ]);
        $newUpload = Upload::factory()->for($user)->create([
            'name' => 'updated-jacket.jpg',
            'disk' => 'local',
            'driver' => 'local',
            'path' => 'uploads/updated-jacket.jpg',
        ]);
        Storage::disk('local')->put($oldUpload->path, 'old image');
        Storage::disk('local')->put($newUpload->path, 'new image');
        $item->mainUpload()->attach($oldUpload);

        $this
            ->actingAs($user)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Updated jacket',
                'description' => 'Updated description.',
                'main_upload_id' => $newUpload->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $item->refresh();

        $this->assertSame('Updated jacket', $item->name);
        $this->assertSame('Updated description.', $item->description);
        $this->assertFalse($item->uploads()->whereKey($oldUpload->id)->exists());
        $this->assertTrue($item->mainUpload()->whereKey($newUpload->id)->exists());
        $this->assertTrue($newUpload->items()->whereKey($item->id)->exists());
        Storage::disk('local')->assertExists($oldUpload->path);
        Storage::disk('local')->assertExists($newUpload->path);
    }

    public function test_an_item_cannot_use_another_users_uploaded_image(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $upload = Upload::factory()->for($otherUser)->create([
            'disk' => 'local',
            'driver' => 'local',
            'path' => 'uploads/other-user-jacket.jpg',
        ]);

        $this
            ->actingAs($user)
            ->post(route('wardrobe.items.store'), [
                'name' => 'Borrowed jacket',
                'description' => null,
                'main_upload_id' => $upload->id,
            ])
            ->assertSessionHasErrors('main_upload_id');

        $this->assertSame(0, $user->items()->count());
    }

    public function test_non_owners_cannot_update_or_delete_items(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = Item::factory()->for($owner)->create([
            'name' => 'Owner jacket',
            'description' => 'Owner description.',
        ]);

        $this
            ->actingAs($otherUser)
            ->patch(route('wardrobe.items.update', $item), [
                'name' => 'Stolen jacket',
                'description' => 'Changed by another user.',
                'main_upload' => UploadedFile::fake()->image('stolen-jacket.jpg'),
            ])
            ->assertForbidden();

        $this
            ->actingAs($otherUser)
            ->delete(route('wardrobe.items.destroy', $item))
            ->assertForbidden();

        $item->refresh();

        $this->assertSame('Owner jacket', $item->name);
        $this->assertSame('Owner description.', $item->description);
        $this->assertNull($item->deleted_at);
        $this->assertSame(0, Upload::count());
        $this->assertSame([], Storage::disk('local')->allFiles('uploads'));
    }

    public function test_an_item_can_be_soft_deleted_without_deleting_physical_upload_files(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();
        $upload = Upload::factory()->for($user)->create([
            'disk' => 'local',
            'driver' => 'local',
            'path' => 'uploads/soft-delete.jpg',
        ]);
        Storage::disk('local')->put($upload->path, 'image');
        $item->mainUpload()->attach($upload);

        $this
            ->actingAs($user)
            ->delete(route('wardrobe.items.destroy', $item))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $this->assertSoftDeleted($item);
        $this->assertModelExists($upload);
        Storage::disk('local')->assertExists($upload->path);
    }
}
