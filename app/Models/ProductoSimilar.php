<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoSimilar extends Model
{
    protected $table = 'producto_similares';

    protected $fillable = [
        'product_id',
        'similar_product_id',
        'orden',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function similarProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'similar_product_id');
    }
}
