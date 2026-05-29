<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;

class CreateMysqlDatabaseCommand extends Command
{
    protected $signature = 'db:create-mysql {--test : Crear también roma_crm_test}';

    protected $description = 'Crea la base de datos MySQL configurada en .env si no existe';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('DB_CONNECTION debe ser mysql');

            return self::FAILURE;
        }

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');
        $mainDb = config('database.connections.mysql.database');

        $databases = array_filter([$mainDb, $this->option('test') ? 'roma_crm_test' : null]);

        try {
            $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar a MySQL: ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($databases as $db) {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->info("Base creada o ya existía: {$db}");
        }

        return self::SUCCESS;
    }
}
