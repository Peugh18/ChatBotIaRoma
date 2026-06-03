<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Voyage AI (reconocimiento visual del catálogo)
    |--------------------------------------------------------------------------
    |
    | Embeddings multimodales vía voyage-multimodal-3.5 para búsqueda visual.
    | Docs: https://docs.voyageai.com/docs/multimodal-embeddings
    |
    */

    'voyage_api_key' => env('VOYAGE_API_KEY'),

    'voyage_model' => env('CATALOG_VISION_VOYAGE_MODEL', 'voyage-multimodal-3.5'),

    'voyage_api_url' => env('CATALOG_VISION_VOYAGE_URL', 'https://api.voyageai.com/v1/multimodalembeddings'),

    'voyage_dimensions' => (int) env('CATALOG_VISION_VOYAGE_DIMENSIONS', 1024),

    'min_similarity' => (float) env('CATALOG_VISION_MIN_SIMILARITY', 0.72),

    'top_k' => (int) env('CATALOG_VISION_TOP_K', 3),

    'index_sleep_ms' => (int) env('CATALOG_VISION_INDEX_SLEEP_MS', 1000),

    'enabled' => env('CATALOG_VISION_ENABLED', true),
];
