<?php

namespace Tests\Unit\Ventas;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Support\ContratoMensajeWhatsapp;
use App\Ventas\Manejadores\ManejadorCheckout;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UbicacionMotorizadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_content_para_ubicacion_whatsapp(): void
    {
        $content = ContratoMensajeWhatsapp::inboundContent([
            'message_type' => 'location',
            'location' => ['latitude' => -12.1, 'longitude' => -77.0],
        ]);

        $this->assertSame('📍 Ubicación compartida', $content);
    }

    public function test_roma_api_ubicacion_en_raw_con_message_type_text(): void
    {
        $payload = [
            'message_type' => 'text',
            'message_body' => null,
            'raw' => [
                'type' => 'location',
                'location' => ['latitude' => -8.1154012, 'longitude' => -79.0174374],
            ],
        ];

        $this->assertTrue(ContratoMensajeWhatsapp::esMensajeUbicacion($payload));
        $this->assertSame('📍 Ubicación compartida', ContratoMensajeWhatsapp::inboundContent($payload));

        $meta = ContratoMensajeWhatsapp::inboundMetadata($payload);
        $this->assertSame('location', $meta['whatsapp_message_type']);
        $this->assertSame('location', $meta['type']);

        $coords = ContratoMensajeWhatsapp::ubicacionDesdePayload($meta);
        $this->assertNotNull($coords);
        $this->assertSame(-8.1154012, $coords['lat']);
        $this->assertSame(-79.0174374, $coords['lng']);
    }

    public function test_ubicacion_anticipada_guarda_referencia_y_sigue_pidiendo_distrito(): void
    {
        $cliente = Customer::create([
            'phone_number' => '51988887777',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'segment' => 'lead',
        ]);

        $estado = ConversationState::create([
            'phone_number' => '51988887777',
            'customer_id' => $cliente->id,
            'current_state' => 'greeting',
            'context' => [],
            'last_activity_at' => now(),
        ]);

        $maquina = app(MaquinaEstadosVentas::class);
        $maquina->guardarDatosEnvio($estado, ['metodo' => 'motorizado']);
        $maquina->establecer($estado, EtapaVentas::ENVIO_DATOS);
        $maquina->establecerCheckoutPaso($estado, 'distrito');

        $respuesta = app(ManejadorCheckout::class)->capturarUbicacion($estado, $cliente, [
            'lat' => -12.0464,
            'lng' => -77.0428,
            'name' => 'Surco',
        ]);

        $this->assertStringContainsString('referencia', mb_strtolower($respuesta->texto));
        $this->assertStringContainsString('distrito', mb_strtolower($respuesta->texto));

        $datos = $maquina->datosEnvio($estado->fresh());
        $this->assertSame(-12.0464, $datos['ubicacion_lat']);
        $this->assertSame('distrito', $maquina->checkoutPaso($estado->fresh()));
    }

    public function test_ubicacion_en_paso_final_muestra_resumen(): void
    {
        $cliente = Customer::create([
            'phone_number' => '51988886666',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'segment' => 'lead',
        ]);

        $estado = ConversationState::create([
            'phone_number' => '51988886666',
            'customer_id' => $cliente->id,
            'current_state' => 'greeting',
            'context' => [
                'carrito' => [
                    ['producto_id' => 1, 'nombre' => 'Mariela', 'color' => 'AZUL', 'talla' => 'M', 'precio' => 120],
                ],
            ],
            'last_activity_at' => now(),
        ]);

        $maquina = app(MaquinaEstadosVentas::class);
        $maquina->guardarDatosEnvio($estado, [
            'metodo' => 'motorizado',
            'distrito' => 'Surco',
            'nombre' => 'Ana',
            'celular' => '999888777',
            'direccion' => 'Av 1 123',
        ]);
        $maquina->guardarCostoEnvio($estado, 12.0);
        $maquina->establecer($estado, EtapaVentas::ENVIO_DATOS);
        $maquina->establecerCheckoutPaso($estado, 'ubicacion');

        $respuesta = app(ManejadorCheckout::class)->capturarUbicacion($estado, $cliente, [
            'lat' => -12.1,
            'lng' => -77.0,
        ]);

        $this->assertTrue($respuesta->debeEnviar());
        $this->assertNull($maquina->checkoutPaso($estado->fresh()));
        $this->assertSame(EtapaVentas::RESUMEN, $maquina->obtener($estado->fresh()));
    }
}
