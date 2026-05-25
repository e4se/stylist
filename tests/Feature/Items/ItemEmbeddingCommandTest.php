<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\TestCase;

class ItemEmbeddingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_embeddings_processes_only_items_missing_source_hash(): void
    {
        $embedding = $this->embeddingVector(0.375);
        $missingHash = Item::factory()->create([
            'name' => 'Linen shirt',
            'description' => 'Lightweight summer layer.',
        ]);
        $missingHashWithStaleEmbedding = Item::factory()->create([
            'name' => 'White sneakers',
            'description' => 'Leather low tops.',
        ]);
        $nonNullStaleHash = Item::factory()->create([
            'name' => 'Changed coat',
            'description' => 'Updated wool layer.',
        ]);

        $missingHashWithStaleEmbedding->forceFill([
            'embedding' => $this->embeddingVector(0.125),
            'embedding_source_hash' => null,
            'embedding_generated_at' => now()->subDay(),
        ])->save();

        $nonNullStaleHash->forceFill([
            'embedding' => $this->embeddingVector(0.75),
            'embedding_source_hash' => hash('sha256', 'Original coat'),
            'embedding_generated_at' => now()->subDay(),
        ])->save();

        Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => array_map(
            fn (): array => $embedding,
            $prompt->inputs,
        ))->preventStrayEmbeddings();

        $this->artisan('items:generate-embeddings')
            ->expectsOutput('Generated embeddings for 2 of 2 eligible items.')
            ->assertSuccessful();

        $missingHash->refresh();
        $missingHashWithStaleEmbedding->refresh();
        $nonNullStaleHash->refresh();

        $this->assertSame($embedding, $missingHash->getAttribute('embedding'));
        $this->assertSame(hash('sha256', "Linen shirt\n\nLightweight summer layer."), $missingHash->getAttribute('embedding_source_hash'));
        $this->assertInstanceOf(CarbonInterface::class, $missingHash->getAttribute('embedding_generated_at'));

        $this->assertSame($embedding, $missingHashWithStaleEmbedding->getAttribute('embedding'));
        $this->assertSame(hash('sha256', "White sneakers\n\nLeather low tops."), $missingHashWithStaleEmbedding->getAttribute('embedding_source_hash'));
        $this->assertInstanceOf(CarbonInterface::class, $missingHashWithStaleEmbedding->getAttribute('embedding_generated_at'));

        $this->assertSame($this->embeddingVector(0.75), $nonNullStaleHash->getAttribute('embedding'));
        $this->assertSame(hash('sha256', 'Original coat'), $nonNullStaleHash->getAttribute('embedding_source_hash'));

        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains("Linen shirt\n\nLightweight summer layer.")
            && $prompt->dimensions === 1536);
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains("White sneakers\n\nLeather low tops.")
            && $prompt->dimensions === 1536);
        Embeddings::assertNotGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains("Changed coat\n\nUpdated wool layer."));
    }

    public function test_generate_embeddings_reports_when_no_items_are_eligible(): void
    {
        $item = Item::factory()->create([
            'name' => 'Current jacket',
            'description' => 'Already embedded.',
        ]);

        $item->forceFill([
            'embedding' => $this->embeddingVector(0.5),
            'embedding_source_hash' => hash('sha256', $item->embeddingInput()),
            'embedding_generated_at' => now()->subHour(),
        ])->save();

        Embeddings::fake()->preventStrayEmbeddings();

        $this->artisan('items:generate-embeddings')
            ->expectsOutput('Generated embeddings for 0 of 0 eligible items.')
            ->assertSuccessful();

        Embeddings::assertNothingGenerated();
    }

    /**
     * @return list<float>
     */
    private function embeddingVector(float $value): array
    {
        return array_fill(0, 1536, $value);
    }
}
