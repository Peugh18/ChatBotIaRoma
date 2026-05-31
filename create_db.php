<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

// Crear base de datos si no existe
try {
    DB::connection()->getPdo();
    echo "✅ Conexión a MySQL exitosa" . PHP_EOL;
} catch (\PDOException $e) {
    echo "❌ Error de conexión a MySQL: " . $e->getMessage() . PHP_EOL;
    echo "Verifica: 1) MySQL está corriendo, 2) Usuario root tiene permisos" . PHP_EOL;
    exit(1);
}

// Verificar si la base de datos existe
$dbName = env('DB_DATABASE', 'goric_chatbot');
$pdo = DB::connection()->getPdo();
$stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
if ($stmt->rowCount() > 0) {
    echo "✅ Base de datos '$dbName' ya existe" . PHP_EOL;
} else {
    echo "⚠️  Base de datos '$dbName' no existe. Creando..." . PHP_EOL;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Base de datos '$dbName' creada exitosamente" . PHP_EOL;
}

echo PHP_EOL;
echo "Ejecuta: php artisan migrate --seed" . PHP_EOL;