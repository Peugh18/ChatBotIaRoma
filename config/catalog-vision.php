<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hugging Face Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for CLIP embeddings and image matching via Hugging Face API.
    |
    */

    'huggingface_token' => env('HUGGINGFACE_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | CLIP Model Configuration
    |--------------------------------------------------------------------------
    |
    | Model to use for generating embeddings.
    |
    */

    'clip_model' => env('CATALOG_VISION_CLIP_MODEL', 'openai/clip-vit-large-patch14'),

    /*
    |--------------------------------------------------------------------------
    | Similarity Threshold
    |--------------------------------------------------------------------------
    |
    | Minimum cosine similarity score (0-1) to consider a match.
    | Typical range: 0.65-0.80
    |
    */

    'min_similarity' => (float) env('CATALOG_VISION_MIN_SIMILARITY', 0.72),

    /*
    |--------------------------------------------------------------------------
    | Top K Results
    |--------------------------------------------------------------------------
    |
    | Number of top matching variants to return.
    | If > 1, show user a selection; if = 1, go directly to product.
    |
    */

    'top_k' => (int) env('CATALOG_VISION_TOP_K', 3),

    /*
    |--------------------------------------------------------------------------
    | Indexing Sleep (ms)
    |--------------------------------------------------------------------------
    |
    | Milliseconds to sleep between API calls during batch indexing.
    | Prevents rate limiting.
    |
    */

    'index_sleep_ms' => (int) env('CATALOG_VISION_INDEX_SLEEP_MS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Hugging Face API Endpoint
    |--------------------------------------------------------------------------
    |
    | Base URL for Hugging Face inference API.
    |
    */

    'huggingface_api_url' => env('HUGGINGFACE_API_URL', 'https://api-inference.huggingface.co'),

    /*
    |--------------------------------------------------------------------------
    | Enable Vision Matching
    |--------------------------------------------------------------------------
    |
    | Whether to enable CLIP-based image matching.
    | If false, falls back to Groq vision + text search.
    |
    */

    'enabled' => env('CATALOG_VISION_ENABLED', true),
];
