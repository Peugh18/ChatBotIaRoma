<?php

namespace Tests\Unit\Ventas;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Ventas\Manejadores\ManejadorCheckout;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlujoShalomTest extends TestCase
{
    use RefreshDatabase;

    public function test_dni_rechaza_mas_de_ocho_digitos(): void
    {
        [$estado, $cliente] = $this->estadoShalomEnPaso('dni');

        $respuesta = app(ManejadorCheckout::class)->procesar(
            $estado,
            $cliente,
            '76929299292',
            EtapaVentas::ENVIO_DATOS,
        );

        $this->assertStringContainsString('8 dígitos', $respuesta->texto);
        $this->assertNull(app(MaquinaEstadosVentas::class)->datosEnvio($estado->fresh())['dni'] ?? null);
    }

    public function test_dni_acepta_exactamente_ocho_digitos(): void
    {
        [$estado, $cliente] = $this->estadoShalomEnPaso('dni');
        $cliente->update(['phone_number' => '51959166911']);

        $respuesta = app(ManejadorCheckout::class)->procesar(
            $estado,
            $cliente,
            '76929299',
            EtapaVentas::ENVIO_DATOS,
        );

        $this->assertTrue($respuesta->debeEnviar());
        $this->assertSame('76929299', app(MaquinaEstadosVentas::class)->datosEnvio($estado->fresh())['dni']);
    }

    public function test_shalom_pide_sede_texto_libre_antes_del_nombre(): void
    {
        $cliente = Customer::create([
            'phone_number' => '51977776666',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'segment' => 'lead',
        ]);

        $estado = ConversationState::create([
            'phone_number' => '51977776666',
            'customer_id' => $cliente->id,
            'current_state' => 'greeting',
            'context' => [],
            'last_activity_at' => now(),
        ]);

        $checkout = app(ManejadorCheckout::class);
        $respuesta = $checkout->procesar($estado, $cliente, 'shipping_shalom', EtapaVentas::ENVIO_METODO);

        $this->assertStringContainsString('sede', mb_strtolower($respuesta->texto));
        $this->assertStringNotContainsString('necesitamos:', mb_strtolower($respuesta->texto));
        $this->assertSame('sede_shalom', app(MaquinaEstadosVentas::class)->checkoutPaso($estado->fresh()));
    }

    /**
     * @return array{0: ConversationState, 1: Customer}
     */
    protected function estadoShalomEnPaso(string $paso): array
    {
        $cliente = Customer::create([
            'phone_number' => '51966665555',
            'name' => 'Miguel',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'segment' => 'lead',
        ]);

        $estado = ConversationState::create([
            'phone_number' => '51966665555',
            'customer_id' => $cliente->id,
            'current_state' => 'greeting',
            'context' => [],
            'last_activity_at' => now(),
        ]);

        $maquina = app(MaquinaEstadosVentas::class);
        $maquina->guardarDatosEnvio($estado, [
            'metodo' => 'shalom',
            'distrito' => 'Cusco — Av. El Sol',
        ]);
        $maquina->establecer($estado, EtapaVentas::ENVIO_DATOS);
        $maquina->establecerCheckoutPaso($estado, $paso);

        return [$estado->fresh(), $cliente];
    }
}
