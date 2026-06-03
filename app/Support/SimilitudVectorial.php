<?php

namespace App\Support;

/**
 * Vector similarity calculations for embeddings.
 * Pure functions, testeable, no dependencies.
 */
class SimilitudVectorial
{
    /**
     * Calculate cosine similarity between two vectors.
     *
     * Cosine similarity = (A · B) / (||A|| * ||B||)
     * Range: -1 to 1 (typically 0 to 1 for normalized embeddings)
     *
     * @param  array<float>  $vectorA
     * @param  array<float>  $vectorB
     * @return float Cosine similarity score (0-1 for normalized vectors)
     */
    public static function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (empty($vectorA) || empty($vectorB)) {
            return 0.0;
        }

        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        // Calculate dot product
        $dotProduct = 0.0;
        foreach ($vectorA as $i => $a) {
            $dotProduct += $a * $vectorB[$i];
        }

        // Calculate magnitudes
        $magnitudeA = sqrt(array_sum(array_map(fn ($x) => $x ** 2, $vectorA)));
        $magnitudeB = sqrt(array_sum(array_map(fn ($x) => $x ** 2, $vectorB)));

        // Avoid division by zero
        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Find top K most similar vectors with their indices.
     *
     * @param  array<float>  $queryVector
     * @param  array<array<float>>  $vectorDatabase  Array of vectors to compare against
     * @param  int  $k  Number of top results to return
     * @return array<array{index: int, score: float}>  Top K results sorted by score descending
     */
    public static function topKSimilar(array $queryVector, array $vectorDatabase, int $k = 3): array
    {
        $scores = [];

        foreach ($vectorDatabase as $index => $vector) {
            $similarity = self::cosineSimilarity($queryVector, $vector);
            $scores[] = [
                'index' => $index,
                'score' => $similarity,
            ];
        }

        // Sort by score descending
        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Return top K
        return array_slice($scores, 0, $k);
    }

    /**
     * Filter vectors by minimum similarity threshold.
     *
     * @param  array<float>  $queryVector
     * @param  array<array<float>>  $vectorDatabase
     * @param  float  $minSimilarity  Minimum score threshold (0-1)
     * @return array<array{index: int, score: float}>  Matching vectors sorted by score descending
     */
    public static function filterBySimilarity(array $queryVector, array $vectorDatabase, float $minSimilarity = 0.72): array
    {
        $matches = [];

        foreach ($vectorDatabase as $index => $vector) {
            $similarity = self::cosineSimilarity($queryVector, $vector);
            if ($similarity >= $minSimilarity) {
                $matches[] = [
                    'index' => $index,
                    'score' => $similarity,
                ];
            }
        }

        // Sort by score descending
        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $matches;
    }
}
