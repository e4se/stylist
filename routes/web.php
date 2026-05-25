<?php

use App\Http\Controllers\UploadController;
use App\Http\Controllers\Wardrobe\ItemController;
use App\Http\Controllers\Wardrobe\TagController;
use App\Http\Controllers\Wardrobe\TagGroupController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');

    Route::get('wardrobe', [ItemController::class, 'index'])->name('wardrobe.index');
    Route::post('wardrobe/items', [ItemController::class, 'store'])->name('wardrobe.items.store');
    Route::match(['put', 'patch'], 'wardrobe/items/{item}', [ItemController::class, 'update'])->name('wardrobe.items.update');
    Route::delete('wardrobe/items/{item}', [ItemController::class, 'destroy'])->name('wardrobe.items.destroy');

    Route::get('wardrobe/tags', [TagGroupController::class, 'index'])->name('wardrobe.tags.index');
    Route::post('wardrobe/tag-groups', [TagGroupController::class, 'store'])->name('wardrobe.tag-groups.store');
    Route::match(['put', 'patch'], 'wardrobe/tag-groups/{tagGroup}', [TagGroupController::class, 'update'])->name('wardrobe.tag-groups.update');
    Route::delete('wardrobe/tag-groups/{tagGroup}', [TagGroupController::class, 'destroy'])->name('wardrobe.tag-groups.destroy');

    Route::scopeBindings()->group(function (): void {
        Route::post('wardrobe/tag-groups/{tagGroup}/tags', [TagController::class, 'store'])->name('wardrobe.tag-groups.tags.store');
        Route::match(['put', 'patch'], 'wardrobe/tag-groups/{tagGroup}/tags/{tag}', [TagController::class, 'update'])->name('wardrobe.tag-groups.tags.update');
        Route::delete('wardrobe/tag-groups/{tagGroup}/tags/{tag}', [TagController::class, 'destroy'])->name('wardrobe.tag-groups.tags.destroy');
    });
});

require __DIR__.'/settings.php';
