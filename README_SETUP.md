# Setup de Base de Datos y Productos Locales

## 1. Crear Base de Datos MySQL

**Opción A: Ejecutar script PowerShell (recomendado)**
```powershell
.\setup_database.ps1
```

**Opción B: Usar MySQL Workbench/phpMyAdmin**
```sql
CREATE DATABASE goric_chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 2. Ejecutar Migraciones y Seeders

```bash
php artisan migrate --seed
```

Esto creará:
- Tablas de productos, variantes, categorías
- Productos de ejemplo con imágenes placeholder
- Configuración de Groq API key

## 3. Confirmar URL Pública

Tu ngrok URL ya está en `.env`:
```
PUBLIC_APP_URL=https://silkworm-humorous-properly.ngrok-free.app
```

## 4. Iniciar Servicios

```bash
php artisan serve
ngrok http 8000
```

## 5. Verificar Imágenes Locales

**Imagen existente:**
- `storage/app/public/products/1/rojo-6a18e964ad0ab.png`

**Proceso de actualizar:**
```bash
php artisan tinker --execute="
\$variant = App\Models\ProductVariant::find(2);
\$variant->update(['image_path' => 'products/1/rojo-6a18e964ad0ab.png']);
"
```

## Flujo de Imágenes en WhatsApp

1. Cliente envía foto → Webhook extrae `image_url`
2. URL es pública HTTPS (ngrok)
3. Groq Vision analiza imagen → describe vestido/color
4. Búsqueda en DB: compara descripciones con productos
5. Retorno: productos coincidentes + botones interactivos

## Verificar funcionamiento

```bash
php artisan tinker --execute="
\$variant = App\Models\ProductVariant::with('product')->find(2);
\$media = app(App\Services\ProductMediaService::class);
\$url = \$media->resolvePublicUrl(\$variant);
echo 'Product: ' . \$variant->product->name . PHP_EOL;
echo 'Image URL: ' . \$url . PHP_EOL;
echo 'Reachable: ' . (\$media->isUrlReachableByMeta(\$url) ? 'YES' : 'NO') . PHP_EOL;
"
```
