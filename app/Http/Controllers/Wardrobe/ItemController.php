<?php

namespace App\Http\Controllers\Wardrobe;

use App\Actions\Uploads\StoreUpload;
use App\Enums\ItemUploadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Models\Item;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ItemController extends Controller
{
    public function __construct(private readonly StoreUpload $storeUpload) {}

    /**
     * Show the authenticated user's wardrobe items.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Item::class);

        $user = $this->authenticatedUser($request);
        $items = $user->items()
            ->with('mainUpload')
            ->latest()
            ->paginate(12)
            ->through(fn (Item $item): array => $this->itemData($item));

        return Inertia::render('wardrobe/index', [
            'items' => Inertia::scroll($items),
        ]);
    }

    /**
     * Store a new wardrobe item.
     */
    public function store(StoreItemRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $storedUpload = null;

        try {
            DB::transaction(function () use ($request, $user, &$storedUpload): void {
                $item = $user->items()->create($this->itemAttributes($request));
                $storedUpload = $this->storeMainUpload($request, $user);

                if ($storedUpload instanceof Upload) {
                    $this->replaceMainUpload($item, $storedUpload);
                }
            });
        } catch (Throwable $exception) {
            $this->cleanupUpload($storedUpload);

            throw $exception;
        }

        return to_route('wardrobe.index');
    }

    /**
     * Update an existing wardrobe item.
     */
    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $storedUpload = null;

        try {
            DB::transaction(function () use ($request, $item, $user, &$storedUpload): void {
                $item->update($this->itemAttributes($request));
                $storedUpload = $this->storeMainUpload($request, $user);

                if ($storedUpload instanceof Upload) {
                    $this->replaceMainUpload($item, $storedUpload);
                }
            });
        } catch (Throwable $exception) {
            $this->cleanupUpload($storedUpload);

            throw $exception;
        }

        return to_route('wardrobe.index');
    }

    /**
     * Soft delete a wardrobe item.
     */
    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return to_route('wardrobe.index');
    }

    /**
     * Get validated item attributes.
     *
     * @return array{name: string, description: string|null}
     */
    private function itemAttributes(StoreItemRequest|UpdateItemRequest $request): array
    {
        /** @var array{name: string, description?: string|null} $validated */
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];
    }

    private function storeMainUpload(StoreItemRequest|UpdateItemRequest $request, User $user): ?Upload
    {
        $file = $request->file('main_upload');

        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $this->storeUpload->execute($user, $file);
    }

    private function replaceMainUpload(Item $item, Upload $upload): void
    {
        $item->mainUpload()->syncWithPivotValues(
            [(string) $upload->getKey()],
            ['type' => ItemUploadType::Main->value],
        );
    }

    /**
     * Format a wardrobe item for the Inertia index page.
     *
     * @return array{id: string, name: string, description: string|null, main_upload: list<array{id: string, name: string, url: string}>}
     */
    private function itemData(Item $item): array
    {
        $mainUploads = $item->getRelationValue('mainUpload');
        $description = $item->getAttribute('description');

        assert($mainUploads instanceof EloquentCollection);
        assert(is_string($description) || $description === null);

        /** @var EloquentCollection<int, Upload> $mainUploads */
        return [
            'id' => (string) $item->getKey(),
            'name' => (string) $item->getAttribute('name'),
            'description' => $description,
            'main_upload' => $mainUploads
                ->map(fn (Upload $upload): array => [
                    'id' => (string) $upload->getKey(),
                    'name' => (string) $upload->getAttribute('name'),
                    'url' => Storage::disk((string) $upload->getAttribute('disk'))->url((string) $upload->getAttribute('path')),
                ])
                ->values()
                ->all(),
        ];
    }

    private function cleanupUpload(?Upload $upload): void
    {
        if (! $upload instanceof Upload) {
            return;
        }

        $disk = (string) $upload->getAttribute('disk');
        $path = (string) $upload->getAttribute('path');

        if ($disk !== '' && $path !== '') {
            Storage::disk($disk)->delete($path);

            Upload::query()
                ->whereKey($upload->getKey())
                ->delete();

            Upload::query()
                ->where('disk', $disk)
                ->where('path', $path)
                ->delete();
        }
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        assert($user instanceof User);

        return $user;
    }
}
