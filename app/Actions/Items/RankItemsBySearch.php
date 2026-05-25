<?php

namespace App\Actions\Items;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

class RankItemsBySearch
{
    public function __construct(private readonly GenerateEmbedding $generateEmbedding) {}

    /**
     * Apply the wardrobe index ordering for optional semantic search.
     *
     * @param  Builder<Item>  $query
     */
    public function apply(Builder $query, ?string $search): void
    {
        if ($search === null) {
            $query->latest();

            return;
        }

        $queryEmbedding = $this->generateEmbedding->execute($search);

        $query->whereNotNull('embedding');

        if ($query->getModel()->getConnection()->getDriverName() === 'pgsql') {
            $query->getQuery()->orderByVectorDistance('embedding', $queryEmbedding);
            $query->orderBy($query->getModel()->getQualifiedKeyName());

            return;
        }

        $this->applyInMemoryOrdering($query, $queryEmbedding);
    }

    /**
     * SQLite stores embeddings as JSON for tests and local fallback, so rank the
     * filtered candidate IDs in PHP before applying a deterministic SQL order.
     *
     * @param  Builder<Item>  $query
     * @param  list<float>  $queryEmbedding
     */
    private function applyInMemoryOrdering(Builder $query, array $queryEmbedding): void
    {
        $candidateQuery = clone $query;

        $candidates = $candidateQuery
            ->setEagerLoads([])
            ->select([
                $query->getModel()->getQualifiedKeyName(),
                'embedding',
            ])
            ->get()
            ->map(fn (Item $item): array => [
                'id' => (string) $item->getKey(),
                'distance' => $this->cosineDistance($queryEmbedding, $this->embeddingVector($item)),
            ])
            ->sort(function (array $first, array $second): int {
                $distanceComparison = $first['distance'] <=> $second['distance'];

                if ($distanceComparison !== 0) {
                    return $distanceComparison;
                }

                return strcmp($first['id'], $second['id']);
            })
            ->pluck('id')
            ->values()
            ->all();

        $this->applyExplicitIdOrdering($query, $candidates);
    }

    /**
     * @param  list<string>  $ids
     */
    private function applyExplicitIdOrdering(Builder $query, array $ids): void
    {
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $bindings = [];
        $caseSegments = array_fill(0, count($ids), 'when ? then ?');

        foreach ($ids as $position => $id) {
            $bindings[] = $id;
            $bindings[] = $position;
        }

        $bindings[] = count($ids);

        $qualifiedKey = $query->getModel()->getQualifiedKeyName();
        $wrappedKey = $query->getQuery()->getGrammar()->wrap($qualifiedKey);

        $query
            ->whereKey($ids)
            ->orderByRaw(
                sprintf('case %s %s else ? end', $wrappedKey, implode(' ', $caseSegments)),
                $bindings,
            )
            ->orderBy($qualifiedKey);
    }

    /**
     * @return list<float>
     */
    private function embeddingVector(Item $item): array
    {
        $embedding = $item->getAttribute('embedding');

        if (! is_array($embedding)) {
            return [];
        }

        return array_map(static fn (mixed $value): float => (float) $value, array_values($embedding));
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function cosineDistance(array $left, array $right): float
    {
        $dotProduct = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;
        $dimensions = max(count($left), count($right));

        for ($index = 0; $index < $dimensions; $index++) {
            $leftValue = $left[$index] ?? 0.0;
            $rightValue = $right[$index] ?? 0.0;

            $dotProduct += $leftValue * $rightValue;
            $leftMagnitude += $leftValue ** 2;
            $rightMagnitude += $rightValue ** 2;
        }

        if ($leftMagnitude === 0.0 || $rightMagnitude === 0.0) {
            return INF;
        }

        return 1.0 - ($dotProduct / (sqrt($leftMagnitude) * sqrt($rightMagnitude)));
    }
}
