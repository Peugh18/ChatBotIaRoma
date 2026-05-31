# Setup Database Script for Windows
# Ejecuta este script como Administrador

Write-Host "=== Configurando Base de Datos ===" -ForegroundColor Cyan

# 1. Verificar si MySQL está instalado
try {
    $mysqlPath = "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe"
    if (-not (Test-Path $mysqlPath)) {
        $mysqlPath = "C:\Program Files\MySQL\MySQL Server 5.7\bin\mysql.exe"
    }
    if (-not (Test-Path $mysqlPath)) {
        Write-Host "❌ MySQL no encontrado. Instala MySQL Server o usa XAMPP/WAMP" -ForegroundColor Red
        exit 1
    }
    Write-Host "✅ MySQL encontrado en: $mysqlPath" -ForegroundColor Green
} catch {
    Write-Host "❌ Error al verificar MySQL" -ForegroundColor Red
    exit 1
}

# 2. Crear base de datos
$dbName = "goric_chatbot"
Write-Host "`n📦 Creando base de datos '$dbName'..." -ForegroundColor Yellow

try {
    & $mysqlPath -u root -e "CREATE DATABASE IF NOT EXISTS \`$dbName\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Base de datos '$dbName' creada exitosamente" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Posible error de permisos. Intentando con contraseña..." -ForegroundColor Yellow
        # Si falla, el usuario debe ingresar contraseña
        & $mysqlPath -u root -p -e "CREATE DATABASE IF NOT EXISTS \`$dbName\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
    }
} catch {
    Write-Host "❌ Error al crear base de datos: $_" -ForegroundColor Red
    Write-Host "`n💡 Solución:" -ForegroundColor Yellow
    Write-Host "1. Abre MySQL Workbench o phpMyAdmin" -ForegroundColor White
    Write-Host "2. Ejecuta: CREATE DATABASE goric_chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" -ForegroundColor White
    exit 1
}

# 3. Verificar conexión
Write-Host "`n🔍 Verificando conexión..." -ForegroundColor Yellow
try {
    $result = & $mysqlPath -u root -e "USE \`$dbName\`; SHOW TABLES;" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Conexión exitosa a '$dbName'" -ForegroundColor Green
    }
} catch {
    Write-Host "❌ No se pudo conectar a la base de datos" -ForegroundColor Red
    exit 1
}

Write-Host "`n=== ✅ Setup completado ===" -ForegroundColor Green
Write-Host "`nEjecuta los siguientes comandos:" -ForegroundColor Cyan
Write-Host "  php artisan migrate --seed" -ForegroundColor White
Write-Host "  php artisan serve" -ForegroundColor White
Write-Host "  ngrok http 8000" -ForegroundColor White