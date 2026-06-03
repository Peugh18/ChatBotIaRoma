<?php

namespace App\Ventas\Manejadores;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Services\ServicioConfigNegocio;
use App\Services\ServicioEscalamientoHumano;
use App\Ventas\Constructores\ConstructorInteractivos;
use App\Ventas\Constructores\PaginadorListasWhatsapp;
use App\Ventas\Constructores\ConstructorMensaje;
use App\Ventas\Contratos\RespuestaBot;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Repositorios\RepositorioEnvios;
use App\Ventas\Repositorios\RepositorioPedidos;
use App\Ventas\Servicios\ServicioCarrito;

class ManejadorCheckout
{
    public function __construct(
        protected ConstructorMensaje $mensajes,
        protected ConstructorInteractivos $interactivos,
        protected MaquinaEstadosVentas $maquina,
        protected RepositorioEnvios $envios,
        protected RepositorioPedidos $pedidos,
        protected ServicioCarrito $carrito,
        protected ServicioConfigNegocio $config,
        protected PaginadorListasWhatsapp $paginador,
    ) {}

    public function iniciarDesdeCarrito(ConversationState $estado, Customer $cliente): RespuestaBot
    {
        $revalidado = $this->carrito->revalidar($this->maquina->carrito($estado));
        if ($revalidado['cambio']) {
            $this->maquina->guardarCarrito($estado, $revalidado['lineas']);
        }
        if ($revalidado['lineas'] === []) {
            return RespuestaBot::texto($this->mensajes->plantilla('carrito_vacio'));
        }

        $datos = $this->maquina->datosEnvio($estado);
        if ($this->tieneDatosCompletos($datos)) {
            $this->maquina->establecer($estado, EtapaVentas::DATOS_REUTILIZAR);

            return $this->preguntarReutilizarDatos($datos);
        }

        return $this->preguntarMetodoEnvio($estado);
    }

    public function procesar(
        ConversationState $estado,
        Customer $cliente,
        string $mensaje,
        ?string $etapa
    ): RespuestaBot {
        $m = trim($mensaje);

        if ($etapa === EtapaVentas::VALIDACION_PAGO) {
            return RespuestaBot::texto((string) config(
                'flujo_ventas.mensaje_pago_pendiente',
                'Tu pago sigue en validación hermosa 💕 En unos minutos te confirmamos.'
            ));
        }

        if ($m === 'shipping_motorizado' || $m === 'envio_motorizado') {
            $this->maquina->guardarDatosEnvio($estado, ['metodo' => 'motorizado']);

            return $this->pedirCampo($estado, 'distrito');
        }

        if ($m === 'shipping_shalom' || $m === 'envio_shalom') {
            $this->maquina->guardarDatosEnvio($estado, ['metodo' => 'shalom']);

            return $this->preguntarSedeShalom($estado);
        }

        if (preg_match('/^page_shalom_(\d+)$/', $m, $match)) {
            return $this->preguntarSedeShalom($estado, (int) $match[1]);
        }

        if (preg_match('/^pick_shalom_(\d+)$/', $m, $match)) {
            $sedeId = (int) $match[1];
            $sede = $this->envios->sedesShalomActivas()->firstWhere('id', $sedeId);
            $costo = $this->envios->costoShalomPorSede($sedeId);
            $this->maquina->guardarDatosEnvio($estado, [
                'sede_shalom_id' => $sedeId,
                'distrito' => $sede?->nombre ?? 'Shalom',
            ]);
            $this->maquina->guardarCostoEnvio($estado, $costo);

            return $this->pedirCampo($estado, 'nombre', $cliente);
        }

        if ($m === 'same_data_yes' || $m === 'si mismos datos') {
            $datos = $this->maquina->datosEnvio($estado);
            if (! $this->tieneDatosCompletos($datos)) {
                return $this->preguntarMetodoEnvio($estado);
            }

            return $this->mostrarResumen($estado);
        }

        if ($m === 'update_data' || $m === 'actualizar datos') {
            return $this->preguntarMetodoEnvio($estado);
        }

        if ($m === 'confirm_resumen' || $m === 'confirmar resumen') {
            return $this->preguntarMetodoPago($estado);
        }

        if ($m === 'pay_yape' || $m === 'pago_yape') {
            return $this->iniciarPagoYape($estado, $cliente);
        }

        if ($m === 'pay_card' || $m === 'pago_tarjeta') {
            $this->maquina->establecer($estado, EtapaVentas::TARJETA_DATOS);
            $this->maquina->establecerCheckoutPaso($estado, 'tarjeta_nombre');

            return RespuestaBot::texto($this->mensajes->plantilla('tarjeta_pide_nombre'));
        }

        $paso = $this->maquina->checkoutPaso($estado);
        if ($paso !== null) {
            return $this->capturarCampo($estado, $cliente, $paso, $m);
        }

        if ($etapa === EtapaVentas::DATOS_REUTILIZAR) {
            return $this->preguntarReutilizarDatos($this->maquina->datosEnvio($estado));
        }

        if ($etapa === EtapaVentas::ENVIO_METODO) {
            return $this->preguntarMetodoEnvio($estado);
        }

        if ($etapa === EtapaVentas::RESUMEN) {
            return $this->mostrarResumen($estado);
        }

        if ($etapa === EtapaVentas::PAGO) {
            return $this->preguntarMetodoPago($estado);
        }

        if ($etapa === EtapaVentas::COMPROBANTE) {
            return RespuestaBot::texto($this->mensajes->plantilla('comprobante_pide_captura'));
        }

        return $this->preguntarMetodoEnvio($estado);
    }

    public function recibirComprobante(ConversationState $estado, string $imageUrl): RespuestaBot
    {
        $pedido = $this->pedidos->pedidoActivo($estado);
        if ($pedido) {
            $this->pedidos->guardarComprobante($pedido->id, $imageUrl);
        }

        $this->maquina->establecer($estado, EtapaVentas::VALIDACION_PAGO);

        return RespuestaBot::texto($this->mensajes->plantilla('comprobante_recibido'));
    }

    protected function iniciarPagoYape(ConversationState $estado, Customer $cliente): RespuestaBot
    {
        $revalidado = $this->carrito->revalidar($this->maquina->carrito($estado));
        $this->maquina->guardarCarrito($estado, $revalidado['lineas']);
        $datos = $this->maquina->datosEnvio($estado);
        $envio = $this->maquina->costoEnvio($estado);
        $total = $revalidado['subtotal'] + $envio;

        $pedido = $this->pedidos->pedidoActivo($estado);
        if (! $pedido) {
            $pedido = $this->pedidos->crearPendiente(
                $estado,
                $cliente,
                $revalidado['lineas'],
                $datos,
                $envio,
                'yape'
            );
        }

        $this->maquina->establecer($estado, EtapaVentas::COMPROBANTE);

        $texto = $this->config->yapePaymentMessageWithTotal((float) $pedido->amount_total);

        return RespuestaBot::texto($texto);
    }

    protected function preguntarMetodoEnvio(ConversationState $estado): RespuestaBot
    {
        $this->maquina->establecer($estado, EtapaVentas::ENVIO_METODO);
        $this->maquina->establecerCheckoutPaso($estado, null);

        $payload = $this->interactivos->construir(
            $this->mensajes->plantilla('envio_elige_metodo'),
            [
                ['id' => 'shipping_motorizado', 'title' => 'Motorizado Lima'],
                ['id' => 'shipping_shalom', 'title' => 'Shalom provincia'],
            ]
        );

        return RespuestaBot::conInteractivo('', $payload);
    }

    protected function preguntarSedeShalom(ConversationState $estado, int $pagina = 0): RespuestaBot
    {
        $sedes = $this->envios->sedesShalomActivas();
        if ($sedes->isEmpty()) {
            $costo = $this->envios->costoShalomPorSede(null, 'provincia');
            $this->maquina->guardarCostoEnvio($estado, $costo);

            return $this->pedirCampo($estado, 'nombre');
        }

        $todas = [];
        foreach ($sedes as $sede) {
            $todas[] = [
                'id' => 'pick_shalom_'.$sede->id,
                'title' => mb_substr($sede->nombre, 0, 24),
                'description' => 'S/'.number_format((float) $sede->costo, 0),
            ];
        }

        $pag = $this->paginador->pagina($todas, $pagina, 'page_shalom');
        $cuerpo = $pagina === 0
            ? $this->mensajes->plantilla('envio_elige_sede')
            : $this->mensajes->plantilla('lista_pagina_siguiente', ['pagina' => (string) ($pagina + 1)]);

        $payload = $this->interactivos->construir($cuerpo, $pag['opciones']);
        $this->maquina->establecerCheckoutPaso($estado, 'sede_shalom');

        return RespuestaBot::conInteractivo('', $payload);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function preguntarReutilizarDatos(array $datos): RespuestaBot
    {
        $payload = $this->interactivos->construir(
            $this->mensajes->plantilla('datos_guardados_intro', [
                'nombre_linea' => isset($datos['nombre']) ? "Nombre: {$datos['nombre']}\n" : '',
                'celular_linea' => isset($datos['celular']) ? "Celular: {$datos['celular']}\n" : '',
                'direccion_linea' => isset($datos['direccion']) ? "Dirección: {$datos['direccion']}\n" : '',
            ]),
            [
                ['id' => 'same_data_yes', 'title' => 'Mismos datos'],
                ['id' => 'update_data', 'title' => 'Actualizar'],
            ]
        );

        return RespuestaBot::conInteractivo('', $payload);
    }

    protected function pedirCampo(ConversationState $estado, string $campo, ?Customer $cliente = null): RespuestaBot
    {
        $this->maquina->establecer($estado, EtapaVentas::ENVIO_DATOS);
        $this->maquina->establecerCheckoutPaso($estado, $campo);

        if ($campo === 'nombre' && $cliente && trim((string) $cliente->name) !== '') {
            $this->maquina->guardarDatosEnvio($estado, ['nombre' => $cliente->name]);

            return $this->pedirCampo($estado, 'celular', $cliente);
        }

        if ($campo === 'celular' && $cliente) {
            $this->maquina->guardarDatosEnvio($estado, ['celular' => $cliente->phone_number]);

            return $this->pedirCampo($estado, 'direccion', $cliente);
        }

        return RespuestaBot::texto($this->mensajes->plantilla('checkout_pide_'.$campo));
    }

    protected function capturarCampo(
        ConversationState $estado,
        Customer $cliente,
        string $paso,
        string $valor
    ): RespuestaBot {
        if ($paso === 'tarjeta_nombre') {
            $this->maquina->guardarDatosEnvio($estado, ['tarjeta_nombre' => $valor]);
            $this->maquina->establecerCheckoutPaso($estado, 'tarjeta_email');

            return RespuestaBot::texto($this->mensajes->plantilla('tarjeta_pide_email'));
        }

        if ($paso === 'tarjeta_email') {
            $this->maquina->guardarDatosEnvio($estado, ['tarjeta_email' => $valor]);
            $this->maquina->establecerCheckoutPaso($estado, 'tarjeta_celular');

            return RespuestaBot::texto($this->mensajes->plantilla('tarjeta_pide_celular'));
        }

        if ($paso === 'tarjeta_celular') {
            $this->maquina->guardarDatosEnvio($estado, ['tarjeta_celular' => $valor]);
            $revalidado = $this->carrito->revalidar($this->maquina->carrito($estado));
            $this->pedidos->crearPendiente(
                $estado,
                $cliente,
                $revalidado['lineas'],
                $this->maquina->datosEnvio($estado),
                $this->maquina->costoEnvio($estado),
                'tarjeta'
            );
            app(ServicioEscalamientoHumano::class)->escalar(
                $estado,
                'Cliente eligió pago con tarjeta — enviar link'
            );

            return RespuestaBot::texto($this->mensajes->plantilla('tarjeta_espera'))
                ->marcarEscalamientoHumano();
        }

        if ($paso === 'distrito') {
            $zona = $this->envios->resolverDistrito($valor);
            $costo = ($this->maquina->datosEnvio($estado)['metodo'] ?? '') === 'shalom'
                ? $this->maquina->costoEnvio($estado)
                : $this->envios->costoMotorizado($zona);
            $this->maquina->guardarCostoEnvio($estado, $costo);
            $this->maquina->guardarDatosEnvio($estado, [
                'distrito' => $zona?->district ?? $valor,
            ]);

            return $this->pedirCampo($estado, 'nombre', $cliente);
        }

        if ($paso === 'nombre') {
            $this->maquina->guardarDatosEnvio($estado, ['nombre' => $valor]);
            if (trim((string) $cliente->name) === '') {
                $cliente->update(['name' => $valor]);
            }

            return $this->pedirCampo($estado, 'celular', $cliente);
        }

        if ($paso === 'celular') {
            $this->maquina->guardarDatosEnvio($estado, ['celular' => $valor]);

            return $this->pedirCampo($estado, 'direccion', $cliente);
        }

        if ($paso === 'direccion') {
            $this->maquina->guardarDatosEnvio($estado, ['direccion' => $valor]);

            return $this->pedirCampo($estado, 'referencia', $cliente);
        }

        if ($paso === 'referencia') {
            $this->maquina->guardarDatosEnvio($estado, [
                'referencia' => $valor !== '' ? $valor : '-',
            ]);
            $this->maquina->establecerCheckoutPaso($estado, null);

            return $this->mostrarResumen($estado);
        }

        $this->maquina->guardarDatosEnvio($estado, [$paso => $valor]);

        return $this->mostrarResumen($estado);
    }

    protected function mostrarResumen(ConversationState $estado): RespuestaBot
    {
        $revalidado = $this->carrito->revalidar($this->maquina->carrito($estado));
        $this->maquina->guardarCarrito($estado, $revalidado['lineas']);
        $envio = $this->maquina->costoEnvio($estado);
        $total = $revalidado['subtotal'] + $envio;

        $lineas = [];
        foreach ($revalidado['lineas'] as $l) {
            $lineas[] = '· '.$l['nombre'].' '.$l['color'].' '.$l['talla'].' — S/'.number_format((float) $l['precio'], 0);
        }

        $datos = $this->maquina->datosEnvio($estado);
        $texto = $this->mensajes->plantilla('resumen_pedido', [
            'lineas' => implode("\n", $lineas),
            'subtotal' => number_format($revalidado['subtotal'], 0),
            'envio' => number_format($envio, 0),
            'total' => number_format($total, 0),
            'distrito' => $datos['distrito'] ?? '',
            'direccion' => $datos['direccion'] ?? '',
        ]);

        $this->maquina->establecer($estado, EtapaVentas::RESUMEN);
        $this->maquina->establecerCheckoutPaso($estado, null);

        $payload = $this->interactivos->construir($texto, [
            ['id' => 'confirm_resumen', 'title' => 'Confirmar total'],
        ]);

        return RespuestaBot::conInteractivo('', $payload);
    }

    protected function preguntarMetodoPago(ConversationState $estado): RespuestaBot
    {
        $this->maquina->establecer($estado, EtapaVentas::PAGO);

        $payload = $this->interactivos->construir(
            $this->mensajes->plantilla('pago_elige_metodo'),
            [
                ['id' => 'pay_yape', 'title' => 'Yape'],
                ['id' => 'pay_card', 'title' => 'Tarjeta'],
            ]
        );

        return RespuestaBot::conInteractivo('', $payload);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function tieneDatosCompletos(array $datos): bool
    {
        $base = isset($datos['nombre'], $datos['celular'], $datos['direccion'], $datos['metodo']);
        if (! $base) {
            return false;
        }

        if (($datos['metodo'] ?? '') === 'shalom') {
            return isset($datos['distrito']) || isset($datos['sede_shalom_id']);
        }

        return isset($datos['distrito']);
    }
}
