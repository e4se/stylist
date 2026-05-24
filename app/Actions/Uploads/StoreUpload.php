<?php

namespace App\Actions\Uploads;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreUpload
{
    private const int NAME_MAX_CHARACTERS = 255;

    public function execute(User $user, UploadedFile $file): Upload
    {
        $disk = (string) config('filesystems.default', 'local');
        $driver = (string) config("filesystems.disks.{$disk}.driver", $disk);
        $path = $file->store('uploads', $disk);

        if ($path === false) {
            throw new RuntimeException('Unable to store uploaded file.');
        }

        try {
            return Upload::create([
                'user_id' => $user->id,
                'name' => Str::limit($file->getClientOriginalName(), self::NAME_MAX_CHARACTERS, ''),
                'disk' => $disk,
                'driver' => $driver,
                'path' => $path,
                'extension' => $file->getClientOriginalExtension() ?: null,
                'size' => (int) $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
