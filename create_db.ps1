<?php

use Illuminate\Database\Capsule\Manager as DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

// Crear base de datos
DB::schema()->createIfNotExists('goric_chatbot', function ($table) {
    // Las tablas se crean cuando se ejecutan las migraciones
});

echo "✅ Base de datos 'goric_chatbot' configurada" . PHP_EOL;
echo "Ejecuta: php artisan migrate --seed" . PHP_EOL;
