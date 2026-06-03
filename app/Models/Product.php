<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const ESTADO_DISPONIBLE = 'disponible';

    public const ESTADO_AGOTADO = 'agotado';

    public const ESTADO_OCULTO = 'oculto';

    protected $fillable = [
        'name',
        'description',
        'price',
        'discount',
        'category_id',
        'status',
        'tags_ia',
    ];

    protected $casts = [
        'tags_ia' => 'array',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function similares(): HasMany
    {
        return $this->hasMany(ProductoSimilar::class)->orderBy('orden');
    }

    public function precioFinal(): float
    {
        return (float) $this->price - (float) ($this->discount ?? 0);
    }

    public function esVisibleEnBot(): bool
    {
        return $this->status !== self::ESTADO_OCULTO;
    }
}
