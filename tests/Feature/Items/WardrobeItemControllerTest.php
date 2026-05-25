<?php

namespace Tests\Feature\Items;

use App\Actions\Uploads\StoreUpload;
use App\Models\Item;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WardrobeItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_wardrobe_routes_are_registered_for_authenticated_verified_users(): void
    {
        $this->assertRoute('wardrobe.index', ['GET', 'HEAD'], 'wardrobe');
        $this->assertRoute('wardrobe.items.store', ['POST'], 'wardrobe/items');
        $this->assertRoute('wardrobe.items.update', ['PUT', 'PATCH'], 'wardrobe/items/{item}');
        $this->assertRoute('wardrobe.items.destroy', ['DELETE'], 'wardrobe/items/{item}');
    }

    public function test_index_renders_only_the_authenticated_users_items_with_main_upload_data(): void
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
            'path' => 'uploads/linen-shirt.jpg',
        ]);
        $colorGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Color',
        ]);
        $seasonGroup = TagGroup::factory()->for($user)->create([
            'name' => 'Season',
        ]);
        $blackTag = Tag::factory()->for($colorGroup)->create([
            'name' => 'Black',
            'color' => '#111827',
        ]);
        $summerTag = Tag::factory()->for($seasonGroup)->create([
            'name' => 'Summer',
            'color' => '#f59e0b',
        ]);
        $foreignGroup = TagGroup::factory()->for($otherUser)->create([
            'name' => 'Hidden',
        ]);
        Tag::factory()->for($foreignGroup)->create([
            'name' => 'Private',
        ]);
        $item->mainUpload()->attach($upload);
        $item->tags()->attach([$summerTag->id, $blackTag->id]);
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
                ->has('filters.tag_ids', 0)
                ->where('items.data.0.id', $item->id)
                ->where('items.data.0.name', 'Linen shirt')
                ->where('items.data.0.description', 'Lightweight summer layer.')
                ->where('items.data.0.main_upload.0.id', $upload->id)
                ->where('items.data.0.main_upload.0.name', 'linen-shirt.jpg')
                ->where('items.data.0.main_upload.0.url', '/storage/uploads/linen-shirt.jpg')
                ->has('items.data.0.tags', 2)
                ->where('items.data.0.tags.0.id', $blackTag->id)
                ->where('items.data.0.tags.0.name', 'Black')
                ->where('items.data.0.tags.0.color', '#111827')
                ->where('items.data.0.tags.0.tag_group.id', $colorGroup->id)
                ->where('items.data.0.tags.0.tag_group.name', 'Color')
                ->where('items.data.0.tags.1.id', $summerTag->id)
                ->where('items.data.0.tags.1.name', 'Summer')
                ->where('items.data.0.tags.1.color', '#f59e0b')
                ->where('items.data.0.tags.1.tag_group.id', $seasonGroup->id)
                ->where('items.data.0.tags.1.tag_group.name', 'Season')
                ->has('tagGroups', 2)
                ->where('tagGroups.0.id', $colorGroup->id)
                ->where('tagGroups.0.name', 'Color')
                ->has('tagGroups.0.tags', 1)
                ->where('tagGroups.0.tags.0.id', $blackTag->id)
                ->where('tagGroups.0.tags.0.tag_group_id', $colorGroup->id)
                ->where('tagGroups.0.tags.0.name', 'Black')
                ->where('tagGroups.0.tags.0.color', '#111827')
                ->where('tagGroups.1.id', $seasonGroup->id)
                ->where('tagGroups.1.name', 'Season')
                ->has('tagGroups.1.tags', 1)
                ->where('tagGroups.1.tags.0.id', $summerTag->id)
                ->where('tagGroups.1.tags.0.tag_group_id', $seasonGroup->id)
                ->where('tagGroups.1.tags.0.name', 'Summer')
                ->where('tagGroups.1.tags.0.color', '#f59e0b'),
            );
    }

    public function test_an_item_can_be_stored_with_a_main_upload(): void
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
        Storage::disk('local')->assertExists($upload->path);
    }

    public function test_an_item_can_be_stored_without_a_main_upload(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('wardrobe.items.store'), [
                'name' => 'White sneakers',
                'description' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('wardrobe.index'));

        $item = $user->items()->sole();

        $this->assertSame('White sneakers', $item->name);
        $this->assertNull($item->description);
        $this->assertFalse($item->mainUpload()->exists());
    }

    public function test_an_item_can_be_updated_and_its_main_upload_can_be_replaced(): void
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
        $this->assertModelExists($oldUpload);
        Storage::disk('local')->assertExists($oldUpload->path);
        Storage::disk('local')->assertExists($newUpload->path);
    }

    public function test_another_users_item_cannot_be_updated_or_receive_a_new_upload(): void
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

        $item->refresh();

        $this->assertSame('Owner jacket', $item->name);
        $this->assertSame('Owner description.', $item->description);
        $this->assertSame(0, Upload::count());
        $this->assertSame([], Storage::disk('local')->allFiles('uploads'));
    }

    public function test_upload_metadata_failure_cleans_up_the_stored_file_and_rolls_back_the_item(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('duplicate-jacket.jpg');
        $path = $file->hashName('uploads');

        Upload::factory()->for($user)->create([
            'disk' => 'local',
            'driver' => 'local',
            'path' => $path,
        ]);

        try {
            $this
                ->actingAs($user)
                ->post(route('wardrobe.items.store'), [
                    'name' => 'Duplicate jacket',
                    'description' => null,
                    'main_upload' => $file,
                ]);

            $this->fail('The duplicate upload path should fail metadata persistence.');
        } catch (QueryException) {
            Storage::disk('local')->assertMissing($path);

            $this->assertFalse($user->items()->where('name', 'Duplicate jacket')->exists());
            $this->assertSame(1, Upload::count());
        }
    }

    public function test_main_upload_pivot_failure_cleans_up_the_newly_stored_file(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $this->withoutExceptionHandling();

        $this->app->bind(StoreUpload::class, function (): StoreUpload {
            return new class extends StoreUpload
            {
                public function execute(User $user, UploadedFile $file): Upload
                {
                    $upload = parent::execute($user, $file);
                    $upload->forceFill(['id' => (string) Str::uuid()]);

                    return $upload;
                }
            };
        });

        $user = User::factory()->create();
        $tagGroup = TagGroup::factory()->for($user)->create();
        $tag = Tag::factory()->for($tagGroup)->create();
        $file = UploadedFile::fake()->image('orphaned-jacket.jpg');
        $path = $file->hashName('uploads');

        try {
            $this
                ->actingAs($user)
                ->post(route('wardrobe.items.store'), [
                    'name' => 'Orphaned jacket',
                    'description' => null,
                    'main_upload' => $file,
                    'tag_ids' => [$tag->id],
                ]);

            $this->fail('The main upload pivot persistence should fail.');
        } catch (QueryException) {
            Storage::disk('local')->assertMissing($path);

            $this->assertSame(0, Item::count());
            $this->assertSame(0, Upload::count());
            $this->assertDatabaseMissing('item_tag', [
                'tag_id' => $tag->id,
            ]);
        }
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
