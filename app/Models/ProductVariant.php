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
    ];

    protected $appends = [
        'public_image_url',
    ];

    protected $casts = [
        'sizes_stock' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getPublicImageUrlAttribute(): ?string
    {
        return app(\App\Services\ProductMediaService::class)->resolvePublicUrl($this);
    }
}
