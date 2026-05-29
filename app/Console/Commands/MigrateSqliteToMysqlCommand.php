<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia datos de database/database.sqlite a la conexión MySQL activa (una sola vez).
 */
class MigrateSqliteToMysqlCommand extends Command
{
    protected $signature = 'db:migrate-sqlite-to-mysql
                            {--sqlite= : Ruta al archivo .sqlite (default: database/database.sqlite)}
                            {--fresh : Ejecuta migrate:fresh en MySQL antes de copiar}';

    protected $description = 'Migra datos desde SQLite (archivo local) hacia MySQL';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('DB_CONNECTION debe ser mysql en .env');

            return self::FAILURE;
        }

        $sqlitePath = $this->option('sqlite') ?: database_path('database.sqlite');
        if (!is_file($sqlitePath)) {
            $this->error("No existe el archivo SQLite: {$sqlitePath}");

            return self::FAILURE;
        }

        config(['database.connections.sqlite_migration' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        try {
            DB::connection('sqlite_migration')->getPdo();
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar: ' . $e->getMessage());
            $this->line('Crea la base en MySQL: CREATE DATABASE roma_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Ejecutando migrate:fresh en MySQL...');
            $this->call('migrate:fresh', ['--force' => true]);
        } elseif (!Schema::connection('mysql')->hasTable('migrations')) {
            $this->warn('MySQL sin tablas. Ejecutando migrate...');
            $this->call('migrate', ['--force' => true]);
        }

        $sqlite = DB::connection('sqlite_migration');
        $mysql = DB::connection('mysql');

        $tables = collect($sqlite->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        ))->pluck('name');

        if ($tables->isEmpty()) {
            $this->warn('SQLite no tiene tablas.');

            return self::SUCCESS;
        }

        $mysql->statement('SET FOREIGN_KEY_CHECKS=0');

        $copied = 0;
        foreach ($tables as $table) {
            if (!Schema::connection('mysql')->hasTable($table)) {
                $this->line("  Omitida (no existe en MySQL): {$table}");
                continue;
            }

            $rows = $sqlite->table($table)->get();
            if ($rows->isEmpty()) {
                $this->line("  {$table}: 0 filas");
                continue;
            }

            $mysql->table($table)->truncate();
            foreach ($rows->chunk(200) as $chunk) {
                $mysql->table($table)->insert($chunk->map(fn ($row) => (array) $row)->all());
            }

            $this->info("  {$table}: {$rows->count()} filas");
            $copied += $rows->count();
        }

        $mysql->statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info("Migración completada ({$copied} filas copiadas).");

        return self::SUCCESS;
    }
}
