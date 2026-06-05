<?php

namespace Tests\Unit;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Order;
use App\Services\ServicioLinkPagoTarjeta;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServicioLinkPagoTarjetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_pendiente_sin_apagar_bot(): void
    {
        Queue::fake();

        $cliente = Customer::create([
            'phone_number' => '51911111111',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $estado = ConversationState::create([
            'phone_number' => '51911111111',
            'customer_id' => $cliente->id,
            'current_state' => 'greeting',
            'requires_human' => true,
            'is_auto_escalated' => true,
            'context' => ['etapa_venta' => EtapaVentas::TARJETA_DATOS],
        ]);

        app(ServicioLinkPagoTarjeta::class)->marcarPendienteEnvio($estado->fresh());

        $estado->refresh();
        $this->assertFalse($estado->requires_human);
        $this->assertFalse($estado->is_auto_escalated);
        $this->assertTrue(app(ServicioLinkPagoTarjeta::class)->estaPendiente($estado));
        $this->assertSame(
            EtapaVentas::ESPERANDO_LINK_TARJETA,
            app(MaquinaEstadosVentas::class)->obtener($estado)
        );
    }

    public function test_enviar_link_pasa_a_comprobante(): void
    {
        Queue::fake();

        $cliente = Customer::create([
            'phone_number' => '51922222222',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $estado = ConversationState::create([
            'phone_number' => '51922222222',
            'customer_id' => $cliente->id,
            'current_state' => 'greeting',
            'context' => [
                'etapa_venta' => EtapaVentas::ESPERANDO_LINK_TARJETA,
                'pendiente_link_tarjeta' => true,
                'ultimo_pedido_id' => 0,
            ],
        ]);

        $order = Order::create([
            'customer_id' => $cliente->id,
            'conversation_state_id' => $estado->id,
            'status' => 'pending',
            'shipping_method' => 'motorizado',
            'shipping_cost' => 10,
            'payment_method' => 'card',
            'amount_subtotal' => 100,
            'amount_total' => 110,
        ]);

        $ctx = $estado->context;
        $ctx['ultimo_pedido_id'] = $order->id;
        $estado->update(['context' => $ctx]);

        app(ServicioLinkPagoTarjeta::class)->enviarLink(
            $estado->fresh(),
            'https://pay.example.com/checkout/abc'
        );

        $estado->refresh();
        $this->assertFalse(app(ServicioLinkPagoTarjeta::class)->estaPendiente($estado));
        $this->assertSame(EtapaVentas::COMPROBANTE, app(MaquinaEstadosVentas::class)->obtener($estado));
    }
}
