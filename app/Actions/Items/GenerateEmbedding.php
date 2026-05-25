<?php

namespace App\Actions\Items;

use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class GenerateEmbedding
{
    private const int EMBEDDING_DIMENSIONS = 1536;

    /**
     * @return list<float>
     */
    public function execute(string $input): array
    {
        return Embeddings::for([$input])
            ->dimensions(self::EMBEDDING_DIMENSIONS)
            ->cache()
            ->generate(Lab::OpenAI)
            ->first();
    }
}
