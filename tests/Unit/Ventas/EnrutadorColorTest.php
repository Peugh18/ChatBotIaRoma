<?php

namespace Tests\Unit\Ventas;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Ventas\EnrutadorVentas;
use App\Ventas\MaquinaEstados\EtapaVentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrutadorColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_resuelve_color_escrito_en_etapa_producto(): void
    {
        $customer = Customer::create([
            'phone_number' => '51999000001',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $producto = Product::create([
            'name' => 'Test Azul',
            'price' => 100,
            'description' => 'Test',
            'status' => Product::ESTADO_DISPONIBLE,
        ]);
        ProductVariant::create([
            'product_id' => $producto->id,
            'color' => 'Azul',
            'sizes_stock' => ['M' => 2],
        ]);

        $state = ConversationState::create([
            'phone_number' => $customer->phone_number,
            'customer_id' => $customer->id,
            'current_state' => 'greeting',
            'context' => [
                'etapa_venta' => EtapaVentas::PRODUCTO,
                'producto_actual_id' => $producto->id,
            ],
            'last_activity_at' => now(),
        ]);

        $resp = app(EnrutadorVentas::class)->despachar(
            $state->fresh(),
            $customer,
            'Azul',
            EtapaVentas::PRODUCTO
        );

        $this->assertTrue($resp->debeEnviar());
        $this->assertSame('Azul', $state->fresh()->context['color_actual'] ?? null);
    }
}
