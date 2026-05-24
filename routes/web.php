<?php

use App\Http\Controllers\UploadController;
use App\Http\Controllers\Wardrobe\ItemController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');

    Route::get('wardrobe', [ItemController::class, 'index'])->name('wardrobe.index');
    Route::post('wardrobe/items', [ItemController::class, 'store'])->name('wardrobe.items.store');
    Route::match(['put', 'patch'], 'wardrobe/items/{item}', [ItemController::class, 'update'])->name('wardrobe.items.update');
    Route::delete('wardrobe/items/{item}', [ItemController::class, 'destroy'])->name('wardrobe.items.destroy');
});

require __DIR__.'/settings.php';
