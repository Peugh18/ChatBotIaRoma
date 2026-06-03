<?php

namespace Tests\Unit;

use App\Support\SimilitudVectorial;
use Tests\TestCase;

class SimilitudVectorialTest extends TestCase
{
    public function test_cosine_similarity_identical_vectors()
    {
        $vector = [1.0, 0.0, 0.0];
        $similarity = SimilitudVectorial::cosineSimilarity($vector, $vector);
        
        $this->assertEquals(1.0, $similarity, '', 0.0001);
    }

    public function test_cosine_similarity_orthogonal_vectors()
    {
        $vectorA = [1.0, 0.0];
        $vectorB = [0.0, 1.0];
        $similarity = SimilitudVectorial::cosineSimilarity($vectorA, $vectorB);
        
        $this->assertEquals(0.0, $similarity, '', 0.0001);
    }

    public function test_cosine_similarity_opposite_vectors()
    {
        $vectorA = [1.0, 0.0];
        $vectorB = [-1.0, 0.0];
        $similarity = SimilitudVectorial::cosineSimilarity($vectorA, $vectorB);
        
        $this->assertEquals(-1.0, $similarity, '', 0.0001);
    }

    public function test_cosine_similarity_partial_match()
    {
        $vectorA = [1.0, 1.0];
        $vectorB = [1.0, 0.0];
        $similarity = SimilitudVectorial::cosineSimilarity($vectorA, $vectorB);
        
        $this->assertGreaterThan(0.5, $similarity);
        $this->assertLessThan(1.0, $similarity);
    }

    public function test_cosine_similarity_empty_vectors()
    {
        $similarity = SimilitudVectorial::cosineSimilarity([], []);
        $this->assertEquals(0.0, $similarity);
    }

    public function test_cosine_similarity_different_dimensions()
    {
        $vectorA = [1.0, 0.0];
        $vectorB = [1.0, 0.0, 0.0];
        $similarity = SimilitudVectorial::cosineSimilarity($vectorA, $vectorB);
        
        $this->assertEquals(0.0, $similarity);
    }

    public function test_top_k_similar()
    {
        $queryVector = [1.0, 0.0, 0.0];
        $database = [
            [1.0, 0.0, 0.0],  // identical
            [0.9, 0.1, 0.0],  // similar
            [0.0, 1.0, 0.0],  // orthogonal
            [0.8, 0.2, 0.0],  // similar
        ];

        $results = SimilitudVectorial::topKSimilar($queryVector, $database, 2);

        $this->assertCount(2, $results);
        $this->assertEquals(0, $results[0]['index']);
        $this->assertEquals(1.0, $results[0]['score'], '', 0.0001);
    }

    public function test_filter_by_similarity()
    {
        $queryVector = [1.0, 0.0];
        $database = [
            [1.0, 0.0],      // score 1.0
            [0.9, 0.1],      // score ~0.99
            [0.5, 0.5],      // score ~0.71
            [0.0, 1.0],      // score 0.0
        ];

        $results = SimilitudVectorial::filterBySimilarity($queryVector, $database, 0.72);

        $this->assertCount(2, $results);
        $this->assertEquals(0, $results[0]['index']);
        $this->assertEquals(1, $results[1]['index']);
    }
}
