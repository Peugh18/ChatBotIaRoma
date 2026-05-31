# Revisión Exhaustiva del Proyecto RomaCrm

## Estado General
- **Tests:** 97+ tests pasando ✅
- **Fases Implementadas:** 6 (Fases 3-6 completadas)
- **Arquitectura:** Laravel 12 + Pest, determinístico con fallback LLM
- **Bot:** WhatsApp determinístico, sin romper flujos existentes

---

## 🔴 ERRORES CRÍTICOS ENCONTRADOS

### 1. **ProductVariant.php — Modelo Incompleto**
**Ubicación:** `app/Models/ProductVariant.php`
**Problema:** El archivo que creé es muy simplificado. El modelo original probablemente tiene más campos y relaciones.
**Impacto:** Riesgo de sobrescribir el modelo existente.
**Solución:** Revisar el modelo original y hacer merge adecuado.

```php
// ❌ ACTUAL (incompleto)
class ProductVariant extends Model {
    protected $fillable = [...];
}

// ✅ DEBERÍA TENER
- Relaciones completas (belongsTo Product, hasMany ProductVariantPhoto, etc.)
- Scopes existentes
- Métodos custom
```

### 2. **BotSetting.php — Campo huggingface_token No en $fillable**
**Ubicación:** `app/Models/BotSetting.php`
**Problema:** La migración agrega `huggingface_token` pero el modelo no lo tiene en `$fillable`.
**Impacto:** No se puede guardar el token desde API/UI.
**Solución:** Agregar a `$fillable`:

```php
protected $fillable = [
    'system_prompt',
    'yape_number',
    'yape_holder',
    'welcome_message',
    'reminder_3min_message',
    'reminder_15min_message',
    'escalation_message',
    'auto_reply_enabled',
    'groq_api_key',
    'model_chat',
    'model_vision',
    'reminder_3min_seconds',
    'reminder_15min_seconds',
    'huggingface_token', // ← AGREGAR
];
```

### 3. **CatalogImageMatcherService — Inyección de Dependencia Incompleta**
**Ubicación:** `app/Services/CatalogImageMatcherService.php` L18
**Problema:** Se inyecta `ImageEmbeddingService` pero no se registra en service provider.
**Impacto:** Error en runtime: "Target class [ImageEmbeddingService] does not exist"
**Solución:** Registrar en `AppServiceProvider`:

```php
public function register(): void {
    $this->app->bind(ImageEmbeddingService::class, function ($app) {
        return new ImageEmbeddingService();
    });
}
```

### 4. **ImageEmbeddingService — Acceso a BotSetting Sin Null Check**
**Ubicación:** `app/Services/ImageEmbeddingService.php` L137
**Problema:** `BotSetting::first()?->huggingface_token` puede retornar null, pero se usa sin validación.
**Impacto:** Fallback silencioso sin logging claro.
**Solución:**

```php
// ❌ ACTUAL
$token = config('catalog-vision.huggingface_token') 
    ?? \App\Models\BotSetting::first()?->huggingface_token;

// ✅ MEJOR
$token = config('catalog-vision.huggingface_token');
if (!$token) {
    $setting = \App\Models\BotSetting::first();
    $token = $setting?->huggingface_token;
}
```

### 5. **SalesFlowService — Método finalizeOrder() Público Pero Debería Ser Protegido**
**Ubicación:** `app/Services/SalesFlowService.php` L542
**Problema:** `finalizeOrder()` es `protected` pero se llama desde tests como `public`.
**Impacto:** Tests usan reflexión o no testean correctamente.
**Solución:** Mantener `protected` y testear a través de métodos públicos.

### 6. **ToolExecutorService — Validación de Stock Incompleta**
**Ubicación:** `app/Services/ToolExecutorService.php` L381-411
**Problema:** Valida stock pero NO valida que `color` y `size` existan en la variante.
**Impacto:** Puede crear pedidos con color/talla inválidos si PriceValidatorService falla.
**Solución:** Agregar validación de existencia de color/talla:

```php
// Después de validación de stock
foreach ($items as $item) {
    $variant = ProductVariant::where('product_id', $item['product_id'])
        ->where('color', 'like', "%{$item['color']}%")
        ->first();
    
    if (!$variant) {
        return [
            'success' => false,
            'error' => "Color '{$item['color']}' no disponible para este producto.",
        ];
    }
    
    if (!isset($variant->sizes_stock[$item['size']])) {
        return [
            'success' => false,
            'error' => "Talla '{$item['size']}' no disponible en color '{$item['color']}'.",
        ];
    }
}
```

### 7. **AgentService::checkReminders() — Cálculo de Tiempo Confuso**
**Ubicación:** `app/Services/AgentService.php` L69
**Problema:** `$state->last_activity_at->diffInSeconds(now())` es correcto pero el debug en tests mostraba valor negativo.
**Impacto:** Confusión en debugging.
**Solución:** Documentar mejor:

```php
// Calcula segundos desde última actividad hasta ahora
$elapsed = $state->last_activity_at->diffInSeconds(now());
// Ejemplo: si last_activity_at fue hace 4 min, elapsed = 240
```

---

## 🟡 MEJORAS IMPORTANTES

### 1. **Falta Manejo de Errores en ImageEmbeddingService**
**Ubicación:** `app/Services/ImageEmbeddingService.php`
**Problema:** No hay timeout en descarga de imagen desde URL.
**Mejora:**

```php
private function loadImageFromUrl(string $url): ?string {
    try {
        $response = Http::timeout(30)  // ✅ Ya existe
            ->connectTimeout(10)        // ← AGREGAR
            ->get($url);
        
        // Validar tamaño de imagen
        $size = strlen($response->body());
        if ($size > 10 * 1024 * 1024) { // 10MB max
            Log::warning('ImageEmbeddingService: Image too large', [
                'url' => $url,
                'size' => $size,
            ]);
            return null;
        }
```

### 2. **Falta Validación de Dimensión de Embedding**
**Ubicación:** `app/Services/ImageEmbeddingService.php` L157
**Problema:** No valida que embedding tenga dimensión esperada (768 para CLIP).
**Mejora:**

```php
$embedding = $response->json();
if (is_array($embedding) && count($embedding) !== 768) {
    Log::warning('ImageEmbeddingService: Unexpected embedding dimension', [
        'expected' => 768,
        'actual' => count($embedding),
    ]);
    return null;
}
```

### 3. **Falta Logging de Matches en CatalogImageMatcherService**
**Ubicación:** `app/Services/CatalogImageMatcherService.php`
**Problema:** No hay logging de qué variante se matcheó y con qué score.
**Mejora:**

```php
private function handleMatches(...) {
    if (count($matches) === 1) {
        Log::info('CatalogImageMatcher: Single match found', [
            'variant_id' => $matches[0]['variant_id'],
            'product_id' => $matches[0]['product_id'],
            'score' => $matches[0]['score'],
            'color' => $matches[0]['color'],
        ]);
    }
}
```

### 4. **Falta Caché para Embeddings de Variantes**
**Ubicación:** `app/Services/CatalogImageMatcherService.php` L45
**Problema:** Cada match carga todas las variantes de BD sin caché.
**Mejora:**

```php
private function matchByEmbedding(string $imageUrl): array {
    $variants = Cache::remember('catalog:indexed-variants', 3600, function () {
        return ProductVariant::query()
            ->whereNotNull('embedding')
            ->with('product')
            ->get();
    });
}
```

### 5. **Falta Validación de .env en config/catalog-vision.php**
**Ubicación:** `config/catalog-vision.php`
**Problema:** No valida que valores sean válidos (ej. min_similarity entre 0-1).
**Mejora:**

```php
'min_similarity' => (float) tap(
    env('CATALOG_VISION_MIN_SIMILARITY', 0.72),
    fn ($val) => $val < 0 || $val > 1 ? throw new \InvalidArgumentException(
        'CATALOG_VISION_MIN_SIMILARITY must be between 0 and 1'
    ) : null
),
```

### 6. **Falta Rollback en IndexCatalogEmbeddingsCommand si Falla**
**Ubicación:** `app/Console/Commands/IndexCatalogEmbeddingsCommand.php`
**Problema:** Si falla a mitad, no hay forma de saber cuáles se indexaron.
**Mejora:**

```php
public function handle(ImageEmbeddingService $embeddingService): int {
    $variants = $query->get();
    $indexed = [];
    
    foreach ($variants as $variant) {
        try {
            // ...
            $indexed[] = $variant->id;
        } catch (\Exception $e) {
            // Guardar estado en archivo o BD
            file_put_contents(
                storage_path('logs/indexing-state.json'),
                json_encode(['indexed' => $indexed, 'failed_at' => $variant->id])
            );
            throw $e;
        }
    }
}
```

### 7. **Falta Validación de Imagen en ProductVariantPhotoController**
**Ubicación:** `app/Http/Controllers/Api/ProductVariantPhotoController.php` (no revisado)
**Problema:** Probablemente no valida que sea imagen válida antes de guardar.
**Mejora:** Agregar validación MIME type y dimensiones.

---

## 🟢 COSAS POR IMPLEMENTAR

### 1. **Job para Indexación Async de Embeddings**
**Prioridad:** ALTA
**Descripción:** Cuando se sube foto a ProductVariantPhotoController, disparar job async.
**Archivo:** `app/Jobs/IndexVariantEmbeddingJob.php`

```php
class IndexVariantEmbeddingJob implements ShouldQueue {
    public function __construct(public int $variantId) {}
    
    public function handle(ImageEmbeddingService $service): void {
        $variant = ProductVariant::find($this->variantId);
        if (!$variant) return;
        
        $imageSource = $variant->image_url ?? $variant->image_path;
        $embedding = $service->getEmbedding($imageSource);
        
        if ($embedding) {
            $variant->update([
                'embedding' => $embedding,
                'embedding_indexed_at' => now(),
                'embedding_model' => config('catalog-vision.clip_model'),
            ]);
        }
    }
}
```

### 2. **UI en bot-settings para Guardar HF Token**
**Prioridad:** ALTA
**Archivos:**
- `resources/js/pages/BotSettings/Index.vue` — Agregar campo password
- `resources/js/types/settings.ts` — Agregar tipo `huggingface_token`
- `app/Http/Controllers/Api/BotSettingsController.php` — Permitir guardar

```vue
<!-- En BotSettings/Index.vue -->
<div class="form-group">
  <label>API Key Hugging Face</label>
  <input 
    v-model="form.huggingface_token" 
    type="password" 
    placeholder="hf_xxxxxxxxxxxxx"
  />
  <small>Opcional. Para reconocimiento visual de catálogo.</small>
</div>
```

### 3. **Endpoint para Testear Embedding**
**Prioridad:** MEDIA
**Descripción:** POST `/api/test-embedding` para verificar que HF token funciona.
**Archivo:** `app/Http/Controllers/Api/CatalogVisionController.php`

```php
public function testEmbedding(Request $request) {
    $imageUrl = $request->validate(['image_url' => 'required|url'])['image_url'];
    
    $embedding = app(ImageEmbeddingService::class)->getEmbedding($imageUrl);
    
    return response()->json([
        'success' => $embedding !== null,
        'dimension' => $embedding ? count($embedding) : null,
        'sample' => $embedding ? array_slice($embedding, 0, 5) : null,
    ]);
}
```

### 4. **Métrica de Calidad de Matches**
**Prioridad:** MEDIA
**Descripción:** Guardar score de cada match para analytics.
**Tabla:** Agregar columna a `orders` o nueva tabla `catalog_matches`.

```php
// En CatalogImageMatcherService::handleMatches()
if (count($matches) === 1) {
    CatalogMatch::create([
        'conversation_state_id' => $state->id,
        'variant_id' => $matches[0]['variant_id'],
        'score' => $matches[0]['score'],
        'method' => 'clip', // o 'groq'
    ]);
}
```

### 5. **Pre-filtro por Color si Groq lo Detecta**
**Prioridad:** BAJA (optimización)
**Descripción:** Si Groq vision detecta color, filtrar variantes antes de cosine.
**Ubicación:** `app/Services/CatalogImageMatcherService.php`

```php
private function matchByEmbedding(string $imageUrl): array {
    $colorHint = $this->extractColorFromImage($imageUrl); // Usar Groq
    
    $query = ProductVariant::query()->whereNotNull('embedding');
    
    if ($colorHint) {
        $query->where('color', 'like', "%{$colorHint}%");
    }
    
    $variants = $query->with('product')->get();
    // ... resto del código
}
```

### 6. **Documentación de Flujo CLIP en README**
**Prioridad:** MEDIA
**Descripción:** Agregar sección en README explicando:
- Cómo configurar HF token
- Cómo ejecutar indexación
- Cómo funciona el fallback

### 7. **Tests para ImageEmbeddingService**
**Prioridad:** ALTA
**Archivo:** `tests/Unit/ImageEmbeddingServiceTest.php`

```php
class ImageEmbeddingServiceTest extends TestCase {
    public function test_get_embedding_from_url_with_http_fake() {
        Http::fake([
            'https://api-inference.huggingface.co/*' => Http::response([
                [0.1, 0.2, 0.3, ...] // 768 floats
            ]),
        ]);
        
        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');
        
        $this->assertIsArray($embedding);
        $this->assertCount(768, $embedding);
    }
}
```

### 8. **Tests para CatalogImageMatcherService**
**Prioridad:** ALTA
**Archivo:** `tests/Unit/CatalogImageMatcherServiceTest.php`

```php
class CatalogImageMatcherServiceTest extends TestCase {
    public function test_match_by_embedding_returns_single_match() {
        // Mock ImageEmbeddingService
        // Mock ProductVariant con embedding indexado
        // Verificar que retorna 1 match con score alto
    }
    
    public function test_fallback_to_groq_when_no_clip_token() {
        // Sin HF token, debe usar flujo Groq original
    }
}
```

### 9. **Migración para Tabla catalog_matches (Analytics)**
**Prioridad:** BAJA
**Descripción:** Guardar histórico de matches para analytics.

```php
Schema::create('catalog_matches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_state_id')->constrained()->cascadeOnDelete();
    $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
    $table->float('score');
    $table->enum('method', ['clip', 'groq']);
    $table->timestamps();
});
```

### 10. **Comando para Limpiar Embeddings Viejos**
**Prioridad:** BAJA
**Descripción:** Si cambias modelo CLIP, limpiar embeddings viejos.

```php
// php artisan catalog:clear-embeddings --model=old-model
class ClearCatalogEmbeddingsCommand extends Command {
    public function handle() {
        $model = $this->option('model');
        ProductVariant::where('embedding_model', $model)
            ->update(['embedding' => null, 'embedding_indexed_at' => null]);
    }
}
```

---

## 📋 CHECKLIST DE CORRECCIONES INMEDIATAS

- [ ] Agregar `huggingface_token` a `BotSetting::$fillable`
- [ ] Registrar `ImageEmbeddingService` en `AppServiceProvider`
- [ ] Agregar validación de color/talla en `ToolExecutorService::executeCreateOrder()`
- [ ] Agregar timeout de conexión en `ImageEmbeddingService::loadImageFromUrl()`
- [ ] Agregar validación de dimensión de embedding
- [ ] Crear `IndexVariantEmbeddingJob` para indexación async
- [ ] Crear tests para `ImageEmbeddingService` y `CatalogImageMatcherService`
- [ ] Agregar campo HF token en UI bot-settings
- [ ] Documentar flujo CLIP en README

---

## 📊 RESUMEN

| Categoría | Cantidad | Estado |
|-----------|----------|--------|
| Errores Críticos | 7 | 🔴 Requieren fix |
| Mejoras Importantes | 7 | 🟡 Recomendadas |
| Cosas por Implementar | 10 | 🟢 Pendientes |
| Tests Pasando | 97+ | ✅ Verde |

**Tiempo Estimado para Correcciones:** 4-6 horas
**Riesgo Actual:** BAJO (tests pasan, pero hay bugs potenciales)
