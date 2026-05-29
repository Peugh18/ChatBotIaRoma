<?php

namespace App\Console\Commands;

use App\Services\PriceValidatorService;
use Illuminate\Console\Command;

class AuditPricesCommand extends Command
{
    protected $signature = 'audit:prices {--fix : Intentar corregir precios con descuento > precio}';
    protected $description = 'Audita la integridad de precios de todos los productos';

    public function handle(): int
    {
        $this->info('🔍 Iniciando auditoría de precios...\n');

        $audit = PriceValidatorService::auditAllProducts();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de productos', $audit['total_products']],
                ['Productos válidos', $audit['valid_count']],
                ['Productos inválidos', $audit['invalid_count']],
                ['Estado', $audit['healthy'] ? '✅ SALUDABLE' : '❌ CON ERRORES'],
            ]
        );

        if (!empty($audit['invalid_products'])) {
            $this->error("\n❌ Productos con precios inválidos:\n");

            $rows = [];
            foreach ($audit['invalid_products'] as $product) {
                $rows[] = [
                    $product['id'],
                    $product['name'],
                    $product['price'] ?? 'NULL',
                    $product['error'],
                ];
            }

            $this->table(['ID', 'Nombre', 'Precio', 'Error'], $rows);

            $this->warn("\n⚠️  Acción requerida:");
            $this->warn("   1. Ve al panel de administración");
            $this->warn("   2. Configura precios correctos para estos productos");
            $this->warn("   3. El bot NO mostrará productos sin precio válido\n");

            return self::FAILURE;
        }

        $this->info("\n✅ Todos los productos tienen precios configurados correctamente.\n");

        return self::SUCCESS;
    }
}
