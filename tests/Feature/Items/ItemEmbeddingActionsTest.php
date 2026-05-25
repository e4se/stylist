<?php

namespace Tests\Feature\Items;

use App\Actions\Items\GenerateEmbedding;
use App\Actions\Items\UpdateItemEmbedding;
use App\Models\Item;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Providers\OpenAiProvider;
use RuntimeException;
use Tests\TestCase;

class ItemEmbeddingActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_embedding_uses_openai_with_1536_dimensions_and_request_cache(): void
    {
        $embedding = $this->embeddingVector(0.125);

        Embeddings::fake([[$embedding]])->preventStrayEmbeddings();

        $action = app(GenerateEmbedding::class);

        $this->assertSame($embedding, $action->execute('Linen shirt'));
        $this->assertSame($embedding, $action->execute('Linen shirt'));

        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('Linen shirt')
            && $prompt->dimensions === 1536
            && $prompt->provider instanceof OpenAiProvider
            && $prompt->model === 'text-embedding-3-small');
    }

    public function test_update_item_embedding_persists_generated_embedding_and_hash(): void
    {
        $generatedAt = Carbon::parse('2026-05-25 12:00:00');
        $embedding = $this->embeddingVector(0.25);

        Carbon::setTestNow($generatedAt);
        Embeddings::fake([[$embedding]])->preventStrayEmbeddings();

        try {
            $item = Item::factory()->create([
                'name' => 'Linen shirt',
                'description' => 'Lightweight summer layer.',
            ]);

            $wasUpdated = app(UpdateItemEmbedding::class)->execute($item);

            $this->assertTrue($wasUpdated);

            $item->refresh();
            $embeddingGeneratedAt = $item->getAttribute('embedding_generated_at');

            $this->assertSame($embedding, $item->getAttribute('embedding'));
            $this->assertSame(hash('sha256', "Linen shirt\n\nLightweight summer layer."), $item->getAttribute('embedding_source_hash'));
            $this->assertInstanceOf(CarbonInterface::class, $embeddingGeneratedAt);
            $this->assertTrue($embeddingGeneratedAt->isSameSecond($generatedAt));
            Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains("Linen shirt\n\nLightweight summer layer.")
                && $prompt->dimensions === 1536);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_update_item_embedding_skips_generation_when_hash_is_current(): void
    {
        $embedding = $this->embeddingVector(0.5);
        $generatedAt = now()->subHour();
        $item = Item::factory()->create([
            'name' => 'White sneakers',
            'description' => 'Leather low tops.',
        ]);
        $sourceHash = hash('sha256', $item->embeddingInput());

        $item->forceFill([
            'embedding' => $embedding,
            'embedding_source_hash' => $sourceHash,
            'embedding_generated_at' => $generatedAt,
        ])->save();

        Embeddings::fake()->preventStrayEmbeddings();

        $wasUpdated = app(UpdateItemEmbedding::class)->execute($item);

        $this->assertFalse($wasUpdated);

        $item->refresh();
        $embeddingGeneratedAt = $item->getAttribute('embedding_generated_at');

        $this->assertSame($embedding, $item->getAttribute('embedding'));
        $this->assertSame($sourceHash, $item->getAttribute('embedding_source_hash'));
        $this->assertInstanceOf(CarbonInterface::class, $embeddingGeneratedAt);
        $this->assertTrue($embeddingGeneratedAt->isSameSecond($generatedAt));
        Embeddings::assertNothingGenerated();
    }

    public function test_update_item_embedding_reports_generation_failure_and_clears_stale_embedding_data(): void
    {
        $exception = new RuntimeException('Embedding generation failed.');
        $item = Item::factory()->create([
            'name' => 'Changed jacket',
            'description' => 'Updated waterproof shell.',
        ]);

        $item->forceFill([
            'embedding' => $this->embeddingVector(0.75),
            'embedding_source_hash' => hash('sha256', 'Old jacket'),
            'embedding_generated_at' => now()->subDay(),
        ])->save();

        Exceptions::fake();
        Embeddings::fake(function (EmbeddingsPrompt $prompt) use ($exception): array {
            throw $exception;
        });

        $wasUpdated = app(UpdateItemEmbedding::class)->execute($item);

        $this->assertFalse($wasUpdated);
        Exceptions::assertReported(fn (RuntimeException $reported): bool => $reported === $exception);

        $item->refresh();

        $this->assertNull($item->getAttribute('embedding'));
        $this->assertNull($item->getAttribute('embedding_source_hash'));
        $this->assertNull($item->getAttribute('embedding_generated_at'));
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains("Changed jacket\n\nUpdated waterproof shell.")
            && $prompt->dimensions === 1536);
    }

    /**
     * @return list<float>
     */
    private function embeddingVector(float $value): array
    {
        return array_fill(0, 1536, $value);
    }
}
