<?php

namespace Tests\Unit\Ventas;

use App\Ventas\Manejadores\ManejadorCheckout;
use Tests\TestCase;

class DatosEnvioCompletosTest extends TestCase
{
    public function test_motorizado_requiere_ubicacion_o_texto(): void
    {
        $checkout = app(ManejadorCheckout::class);
        $ref = new \ReflectionClass($checkout);
        $method = $ref->getMethod('tieneDatosCompletos');
        $method->setAccessible(true);

        $incompleto = $method->invoke($checkout, [
            'metodo' => 'motorizado',
            'nombre' => 'Ana',
            'celular' => '51999999999',
            'distrito' => 'Surco',
            'direccion' => 'Av 1',
        ]);
        $this->assertFalse($incompleto);

        $completo = $method->invoke($checkout, [
            'metodo' => 'motorizado',
            'nombre' => 'Ana',
            'celular' => '51999999999',
            'distrito' => 'Surco',
            'direccion' => 'Av 1',
            'ubicacion_lat' => -12.1,
            'ubicacion_lng' => -77.0,
        ]);
        $this->assertTrue($completo);
    }

    public function test_shalom_requiere_dni_y_sede(): void
    {
        $checkout = app(ManejadorCheckout::class);
        $ref = new \ReflectionClass($checkout);
        $method = $ref->getMethod('tieneDatosCompletos');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($checkout, [
            'metodo' => 'shalom',
            'nombre' => 'Ana',
            'celular' => '51999999999',
            'distrito' => 'Cusco',
        ]));

        $this->assertTrue($method->invoke($checkout, [
            'metodo' => 'shalom',
            'nombre' => 'Ana',
            'celular' => '51999999999',
            'distrito' => 'Cusco — Av. El Sol',
            'dni' => '12345678',
            'sede_shalom_texto' => 'Cusco — Av. El Sol',
        ]));
    }
}
