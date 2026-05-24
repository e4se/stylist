<?php

namespace Tests\Feature\Uploads;

use App\Http\Requests\Uploads\StoreUploadRequest;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_upload_store(): void
    {
        $this
            ->post(route('uploads.store'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_store_an_image_upload(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('black-jacket.jpg');

        $response = $this
            ->actingAs($user)
            ->post(route('uploads.store'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'black-jacket.jpg')
            ->assertJsonPath('disk', 'local')
            ->assertJsonPath('user_id', $user->id);

        $upload = Upload::query()->sole();

        $response
            ->assertJsonPath('id', $upload->id)
            ->assertJsonPath('url', Storage::disk('local')->url($upload->path));

        $this->assertSame($user->id, $upload->user_id);
        Storage::disk('local')->assertExists($upload->path);
    }

    public function test_upload_store_requires_an_image_file(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('uploads.store'), [
                'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_store_rejects_oversized_images(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('uploads.store'), [
                'file' => UploadedFile::fake()
                    ->image('large-jacket.jpg')
                    ->size(StoreUploadRequest::FILE_MAX_KILOBYTES + 1),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }
}
