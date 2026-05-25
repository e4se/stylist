<?php

namespace App\Actions\Items;

use App\Models\Item;
use Throwable;

class UpdateItemEmbedding
{
    public function __construct(public GenerateEmbedding $generateEmbedding) {}

    public function execute(Item $item): bool
    {
        $sourceHash = $this->sourceHash($item);

        if ($item->getAttribute('embedding_source_hash') === $sourceHash) {
            return false;
        }

        try {
            $embedding = $this->generateEmbedding->execute($item->embeddingInput());
        } catch (Throwable $exception) {
            report($exception);

            $this->clearStaleEmbedding($item);

            return false;
        }

        $item->forceFill([
            'embedding' => $embedding,
            'embedding_source_hash' => $sourceHash,
            'embedding_generated_at' => now(),
        ])->save();

        return true;
    }

    private function sourceHash(Item $item): string
    {
        return hash('sha256', $item->embeddingInput());
    }

    private function clearStaleEmbedding(Item $item): void
    {
        $item->forceFill([
            'embedding' => null,
            'embedding_source_hash' => null,
            'embedding_generated_at' => null,
        ])->save();
    }
}
