<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'color',
        'image_path',
        'image_url',
        'sizes_stock',
        'embedding',
        'embedding_indexed_at',
        'embedding_model',
    ];

    protected $casts = [
        'sizes_stock' => 'array',
        'embedding' => 'array',
        'embedding_indexed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
