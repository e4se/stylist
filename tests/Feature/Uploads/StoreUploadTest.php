<?php

namespace Tests\Feature\Uploads;

use App\Actions\Uploads\StoreUpload;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_an_uploaded_file_and_records_metadata(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('quarterly-report.pdf', 128, 'application/pdf');

        $upload = app(StoreUpload::class)->execute($user, $file);

        Storage::disk('local')->assertExists($upload->path);

        $this->assertModelExists($upload);
        $this->assertTrue(Str::isUuid($upload->id));
        $this->assertSame($user->id, $upload->user_id);
        $this->assertTrue($upload->user->is($user));
        $this->assertTrue($user->uploads()->whereKey($upload->id)->exists());
        $this->assertSame('quarterly-report.pdf', $upload->name);
        $this->assertSame('local', $upload->disk);
        $this->assertSame('local', $upload->driver);
        $this->assertStringStartsWith('uploads/', $upload->path);
        $this->assertSame('pdf', $upload->extension);
        $this->assertSame($file->getSize(), $upload->size);
        $this->assertSame($file->getMimeType(), $upload->mime_type);
    }

    public function test_it_limits_overlong_original_filenames_before_persisting(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $filename = str_repeat('a', 252).'.txt';
        $file = UploadedFile::fake()->create($filename, 4, 'text/plain');

        $upload = app(StoreUpload::class)->execute($user, $file);

        $this->assertModelExists($upload);
        $this->assertSame(255, mb_strlen($upload->name));
        $this->assertSame('txt', $upload->extension);
        Storage::disk('local')->assertExists($upload->path);
    }

    public function test_it_deletes_the_stored_file_when_metadata_persistence_fails(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('duplicate.txt', 4, 'text/plain');
        $path = $file->hashName('uploads');

        Upload::factory()->for($user)->create([
            'disk' => 'local',
            'driver' => 'local',
            'path' => $path,
        ]);

        try {
            app(StoreUpload::class)->execute($user, $file);

            $this->fail('The upload metadata persistence should fail.');
        } catch (QueryException) {
            Storage::disk('local')->assertMissing($path);

            $this->assertSame(1, Upload::count());
        }
    }
}
