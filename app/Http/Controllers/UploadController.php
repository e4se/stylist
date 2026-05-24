<?php

namespace App\Http\Controllers;

use App\Actions\Uploads\StoreUpload;
use App\Http\Requests\Uploads\StoreUploadRequest;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public function __construct(private readonly StoreUpload $storeUpload) {}

    /**
     * Store an uploaded file and return its upload model.
     */
    public function store(StoreUploadRequest $request): JsonResponse
    {
        $this->authorize('create', Upload::class);

        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => __('The file field is required.'),
            ]);
        }

        $upload = $this->storeUpload->execute($user, $file);
        $upload->setAttribute('url', $this->uploadUrl($upload));

        return response()->json($upload, HttpResponse::HTTP_CREATED);
    }

    private function uploadUrl(Upload $upload): string
    {
        return Storage::disk((string) $upload->getAttribute('disk'))
            ->url((string) $upload->getAttribute('path'));
    }
}
