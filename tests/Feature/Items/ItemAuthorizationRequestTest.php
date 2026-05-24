<?php

namespace Tests\Feature\Items;

use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ItemAuthorizationRequestTest extends TestCase
{
    use RefreshDatabase;

    private const string STORE_URI = '/__tests/items';

    private const string UPDATE_URI = '/__tests/items/';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::post(self::STORE_URI, static function (StoreItemRequest $request): Response {
                $request->validated();

                return response()->noContent();
            });

            Route::put(self::UPDATE_URI.'{item}', static function (UpdateItemRequest $request): Response {
                $request->validated();

                return response()->noContent();
            });
        });
    }

    public function test_item_policy_allows_authenticated_users_to_view_any_and_create_items(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Item::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', Item::class));
    }

    public function test_item_policy_allows_only_owners_to_view_update_and_delete_items(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = Item::factory()->for($owner)->create();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $item));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $item));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $item));

        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $item));
        $this->assertFalse(Gate::forUser($otherUser)->allows('update', $item));
        $this->assertFalse(Gate::forUser($otherUser)->allows('delete', $item));
    }

    public function test_store_item_request_authorizes_authenticated_users_through_the_policy(): void
    {
        $this
            ->postItem()
            ->assertForbidden();

        $this
            ->actingAs(User::factory()->create())
            ->postItem()
            ->assertNoContent();
    }

    public function test_update_item_request_authorizes_only_owners_through_the_policy(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = Item::factory()->for($owner)->create();

        $this
            ->actingAs($owner)
            ->putItem($item)
            ->assertNoContent();

        $this
            ->actingAs($otherUser)
            ->putItem($item)
            ->assertForbidden();
    }

    public function test_store_item_request_requires_a_name(): void
    {
        $this
            ->actingAs(User::factory()->create())
            ->postItem(['name' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_item_request_requires_a_name(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();

        $this
            ->actingAs($user)
            ->putItem($item, ['name' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_item_requests_reject_overlong_names(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();
        $name = str_repeat('a', StoreItemRequest::NAME_MAX_CHARACTERS + 1);

        $this
            ->actingAs($user)
            ->postItem(['name' => $name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this
            ->actingAs($user)
            ->putItem($item, ['name' => $name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_item_requests_reject_invalid_main_upload_files(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();

        $this
            ->actingAs($user)
            ->postItem([
                'main_upload' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('main_upload');

        $this
            ->actingAs($user)
            ->putItem($item, [
                'main_upload' => UploadedFile::fake()
                    ->image('jacket.jpg')
                    ->size(UpdateItemRequest::MAIN_UPLOAD_MAX_KILOBYTES + 1),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('main_upload');
    }

    public function test_item_requests_reject_overlong_main_upload_filenames(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();
        $filename = str_repeat('a', StoreItemRequest::MAIN_UPLOAD_NAME_MAX_CHARACTERS - 3).'.jpg';

        $this
            ->actingAs($user)
            ->postItem([
                'main_upload' => UploadedFile::fake()->image($filename),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('main_upload');

        $this
            ->actingAs($user)
            ->putItem($item, [
                'main_upload' => UploadedFile::fake()->image($filename),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('main_upload');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postItem(array $overrides = []): TestResponse
    {
        return $this->post(self::STORE_URI, [
            'name' => 'Linen shirt',
            'description' => null,
            ...$overrides,
        ], [
            'Accept' => 'application/json',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function putItem(Item $item, array $overrides = []): TestResponse
    {
        return $this->put(self::UPDATE_URI.$item->id, [
            'name' => 'Linen shirt',
            'description' => 'Lightweight summer layer.',
            ...$overrides,
        ], [
            'Accept' => 'application/json',
        ]);
    }
}
