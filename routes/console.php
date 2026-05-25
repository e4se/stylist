<?php

use App\Actions\Items\UpdateItemEmbedding;
use App\Models\Item;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('items:generate-embeddings', function (UpdateItemEmbedding $updateItemEmbedding): void {
    $processed = 0;
    $updated = 0;

    Item::query()
        ->whereNull('embedding_source_hash')
        ->lazyById()
        ->each(function (Item $item) use ($updateItemEmbedding, &$processed, &$updated): void {
            $processed++;

            if ($updateItemEmbedding->execute($item)) {
                $updated++;
            }
        });

    $this->info("Generated embeddings for {$updated} of {$processed} eligible items.");
})->purpose('Generate embeddings for existing wardrobe items missing them');
