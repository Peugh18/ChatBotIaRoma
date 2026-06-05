<?php

namespace App\Ventas\Manejadores;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Services\ServicioConfigNegocio;
use App\Services\ServicioLinkPagoTarjeta;
use App\Ventas\Constructores\ConstructorInteractivos;
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

        if ($etapa === EtapaVentas::ESPERANDO_LINK_TARJETA) {
            return RespuestaBot::texto($this->mensajes->plantilla('tarjeta_espera_link'));
        }

        if ($m === 'shipping_motorizado' || $m === 'envio_motorizado') {
            $this->prepararMetodo($estado, 'motorizado');

            return $this->pedirCampo($estado, 'distrito', $cliente, $this->textoIntroMotorizado($estado));
        }

        if ($m === 'shipping_shalom' || $m === 'envio_shalom') {
            return $this->iniciarDatosShalom($estado, $cliente);
        }

        if ($m === 'same_data_yes' || $m === 'si mismos datos') {
            $datos = $this->maquina->datosEnvio($estado);
            if (! $this->tieneDatosCompletos($datos)) {
                return $this->preguntarMetodoEnvio($estado);
            }

            // Recalcular el costo de envío porque se limpia tras cada compra
            $metodo = $datos['metodo'] ?? '';
            $costo = 0.0;
            if ($metodo === 'shalom') {
                $costo = $this->envios->costoShalom('provincia');
            } else {
                $zona = $this->envios->resolverDistrito($datos['distrito'] ?? '');
                $costo = $this->envios->costoMotorizado($zona);
            }
            $this->maquina->guardarCostoEnvio($estado, $costo);

            return $this->mostrarResumen($estado);
        }

        if ($m === 'update_data' || $m === 'actualizar datos') {
            $this->maquina->limpiarDatosEnvio($estado);

            return $this->preguntarMetodoEnvio($estado);
        }

        if ($m === 'confirm_resumen' || $m === 'confirmar resumen') {
            return $this->preguntarMetodoPago($estado);
        }

        if ($m === 'edit_cart' || $m === 'editar compra' || $m === 'editar carrito') {
            return $this->preguntarEliminarItem($estado);
        }

        if (preg_match('/^rm_item_(\d+)$/', $m, $match)) {
            return $this->removerItem($estado, (int) $match[1]);
        }

        if ($m === 'cancel_edit_cart') {
            return $this->mostrarResumen($estado);
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

    protected function iniciarDatosShalom(ConversationState $estado, Customer $cliente): RespuestaBot
    {
        $this->prepararMetodo($estado, 'shalom');
        $this->maquina->guardarCostoEnvio($estado, $this->envios->costoShalom('provincia'));

        return $this->pedirCampo($estado, 'sede_shalom', $cliente, $this->textoIntroShalom($estado));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function preguntarReutilizarDatos(array $datos): RespuestaBot
    {
        $metodo = (string) ($datos['metodo'] ?? '');
        $envioLinea = $metodo === 'shalom'
            ? 'Envío: Shalom'.(isset($datos['distrito']) ? " · {$datos['distrito']}\n" : "\n")
            : 'Envío: Motorizado'.(isset($datos['distrito']) ? " · {$datos['distrito']}\n" : "\n");

        $payload = $this->interactivos->construir(
            $this->mensajes->plantilla('datos_guardados_intro', [
                'envio_linea' => $envioLinea,
                'nombre_linea' => isset($datos['nombre']) ? "Nombre: {$datos['nombre']}\n" : '',
                'celular_linea' => isset($datos['celular']) ? "Celular: {$datos['celular']}\n" : '',
                'direccion_linea' => ($metodo === 'motorizado' && isset($datos['direccion']))
                    ? "Dirección: {$datos['direccion']}\n"
                    : '',
                'dni_linea' => ($metodo === 'shalom' && isset($datos['dni']))
                    ? "DNI: {$datos['dni']}\n"
                    : '',
            ]),
            [
                ['id' => 'same_data_yes', 'title' => 'Mismos datos'],
                ['id' => 'update_data', 'title' => 'Actualizar'],
            ]
        );

        return RespuestaBot::conInteractivo('', $payload);
    }

    protected function pedirCampo(
        ConversationState $estado,
        string $campo,
        ?Customer $cliente = null,
        ?string $prefijo = null
    ): RespuestaBot {
        $this->maquina->establecer($estado, EtapaVentas::ENVIO_DATOS);
        $this->maquina->establecerCheckoutPaso($estado, $campo);
        $metodo = (string) ($this->maquina->datosEnvio($estado)['metodo'] ?? '');

        if ($campo === 'nombre' && $cliente && trim((string) $cliente->name) !== '') {
            $this->maquina->guardarDatosEnvio($estado, ['nombre' => $cliente->name]);

            return $metodo === 'shalom'
                ? $this->pedirCampo($estado, 'dni', $cliente)
                : $this->pedirCampo($estado, 'celular', $cliente);
        }

        if ($campo === 'celular' && $cliente) {
            $tel = preg_replace('/\D/', '', (string) $cliente->phone_number);
            if (strlen($tel) >= 9) {
                $celular = strlen($tel) > 9 && str_starts_with($tel, '51')
                    ? substr($tel, -9)
                    : $tel;
                $this->maquina->guardarDatosEnvio($estado, ['celular' => $celular]);

                if ($metodo === 'shalom') {
                    $this->maquina->establecerCheckoutPaso($estado, null);

                    return $this->mostrarResumen($estado);
                }

                return $this->pedirCampo($estado, 'direccion', $cliente);
            }
        }

        $texto = $this->mensajes->plantilla('checkout_pide_'.$campo);
        if ($prefijo !== null && $prefijo !== '') {
            $texto = $prefijo."\n\n".$texto;
        }

        return RespuestaBot::texto($texto);
    }

    public function capturarUbicacion(ConversationState $estado, Customer $cliente, array $ubicacion): RespuestaBot
    {
        $metodo = (string) ($this->maquina->datosEnvio($estado)['metodo'] ?? '');
        if ($metodo !== 'motorizado') {
            return RespuestaBot::texto($this->mensajes->plantilla('ubicacion_fuera_envio'));
        }

        $this->maquina->guardarDatosEnvio($estado, [
            'ubicacion_lat' => (float) ($ubicacion['lat'] ?? 0),
            'ubicacion_lng' => (float) ($ubicacion['lng'] ?? 0),
            'ubicacion_label' => isset($ubicacion['name']) ? (string) $ubicacion['name'] : null,
        ]);

        $paso = $this->maquina->checkoutPaso($estado);
        if ($paso === 'ubicacion') {
            $this->maquina->establecerCheckoutPaso($estado, null);

            return $this->mostrarResumen($estado);
        }

        $datos = $this->maquina->datosEnvio($estado);
        if ($this->tieneDatosCompletos($datos)) {
            $this->maquina->establecerCheckoutPaso($estado, null);

            return $this->mostrarResumen($estado);
        }

        if ($paso !== null) {
            return RespuestaBot::texto(
                $this->mensajes->plantilla('ubicacion_referencia_recibida')."\n\n"
                .$this->mensajes->plantilla('checkout_pide_'.$paso)
            );
        }

        return RespuestaBot::texto($this->mensajes->plantilla('ubicacion_referencia_recibida'));
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
            $pedido = $this->pedidos->crearPendiente(
                $estado,
                $cliente,
                $revalidado['lineas'],
                $this->maquina->datosEnvio($estado),
                $this->maquina->costoEnvio($estado),
                'tarjeta'
            );
            app(ServicioLinkPagoTarjeta::class)->marcarPendienteEnvio($estado);
            $this->maquina->establecerCheckoutPaso($estado, null);

            $total = number_format((float) $pedido->amount_total, 0);

            return RespuestaBot::texto($this->mensajes->plantilla('tarjeta_espera', [
                'total' => $total,
                'pedido' => (string) $pedido->id,
            ]));
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

            $metodo = (string) ($this->maquina->datosEnvio($estado)['metodo'] ?? '');

            return $metodo === 'shalom'
                ? $this->pedirCampo($estado, 'dni', $cliente)
                : $this->pedirCampo($estado, 'celular', $cliente);
        }

        if ($paso === 'sede_shalom') {
            $sedeTexto = trim($valor);
            if (mb_strlen($sedeTexto) < 3) {
                return RespuestaBot::texto($this->mensajes->plantilla('checkout_pide_sede_shalom'));
            }
            $this->maquina->guardarDatosEnvio($estado, [
                'distrito' => $sedeTexto,
                'sede_shalom_texto' => $sedeTexto,
            ]);

            return $this->pedirCampo($estado, 'nombre', $cliente);
        }

        if ($paso === 'dni') {
            $dni = preg_replace('/\D/', '', $valor) ?? '';
            if (strlen($dni) !== 8) {
                return RespuestaBot::texto($this->mensajes->plantilla('checkout_pide_dni_invalido'));
            }
            $this->maquina->guardarDatosEnvio($estado, ['dni' => $dni]);

            return $this->pedirCampo($estado, 'celular', $cliente);
        }

        if ($paso === 'celular') {
            $this->maquina->guardarDatosEnvio($estado, ['celular' => $valor]);
            $metodo = (string) ($this->maquina->datosEnvio($estado)['metodo'] ?? '');

            if ($metodo === 'shalom') {
                $this->maquina->establecerCheckoutPaso($estado, null);

                return $this->mostrarResumen($estado);
            }

            return $this->pedirCampo($estado, 'direccion', $cliente);
        }

        if ($paso === 'direccion') {
            $this->maquina->guardarDatosEnvio($estado, ['direccion' => $valor]);

            return $this->pedirCampo($estado, 'ubicacion', $cliente);
        }

        if ($paso === 'ubicacion') {
            $coords = $this->parsearUbicacionTexto($valor);
            if ($coords !== null) {
                return $this->capturarUbicacion($estado, $cliente, $coords);
            }
            $this->maquina->guardarDatosEnvio($estado, ['ubicacion_texto' => $valor]);
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
            $nombre = trim($l['nombre'] ?? 'Producto');
            $color = trim($l['color'] ?? '-');
            $talla = trim($l['talla'] ?? '-');
            $precio = number_format((float) ($l['precio'] ?? 0), 0);

            $lineas[] = "🛍️ *{$nombre}*\n   └ Color: {$color} | Talla: {$talla} | S/{$precio}";
        }

        $datos = $this->maquina->datosEnvio($estado);
        $texto = $this->mensajes->plantilla('resumen_pedido', [
            'lineas' => implode("\n", $lineas),
            'subtotal' => number_format($revalidado['subtotal'], 0),
            'envio' => number_format($envio, 0),
            'total' => number_format($total, 0),
            'envio_detalle' => $this->textoDetalleEnvio($datos),
        ]);

        $this->maquina->establecer($estado, EtapaVentas::RESUMEN);
        $this->maquina->establecerCheckoutPaso($estado, null);

        $payload = $this->interactivos->construir($texto, [
            ['id' => 'confirm_resumen', 'title' => 'Confirmar total'],
            ['id' => 'edit_cart', 'title' => 'Editar compra'],
        ]);

        return RespuestaBot::conInteractivo('', $payload);
    }

    protected function preguntarEliminarItem(ConversationState $estado): RespuestaBot
    {
        $lineas = $this->maquina->carrito($estado);
        if (count($lineas) === 0) {
            return RespuestaBot::texto('Tu carrito está vacío.');
        }

        $rows = [];
        foreach ($lineas as $idx => $linea) {
            $nombre = trim($linea['nombre'] ?? 'Producto');
            $color = trim($linea['color'] ?? '-');
            $talla = trim($linea['talla'] ?? '-');
            $precio = number_format((float) ($linea['precio'] ?? 0), 0);

            $titleCorto = mb_substr($nombre, 0, 21);
            $desc = mb_substr("Color: {$color} | Talla: {$talla} | S/{$precio}", 0, 72);

            $rows[] = [
                'id' => 'rm_item_'.$idx,
                'title' => '❌ '.$titleCorto,
                'description' => $desc,
            ];
        }

        $rows[] = [
            'id' => 'add_more_product',
            'title' => '➕ Agregar producto',
            'description' => 'Ir al catálogo para sumar más cosas',
        ];

        $rows[] = [
            'id' => 'cancel_edit_cart',
            'title' => 'Volver al resumen',
            'description' => 'No eliminar nada',
        ];

        $payload = [
            'kind' => 'list',
            'body' => ['text' => "¿Qué producto deseas eliminar de tu compra?\n\nToca el botón de abajo para ver tu lista y elegir qué producto quitar."],
            'button' => 'Ver lista',
            'sections' => [
                ['title' => 'Opciones de edición', 'rows' => $rows],
            ],
        ];

        return RespuestaBot::conInteractivo('', $payload);
    }

    protected function removerItem(ConversationState $estado, int $idx): RespuestaBot
    {
        $lineas = $this->maquina->carrito($estado);
        if (isset($lineas[$idx])) {
            unset($lineas[$idx]);
            $lineas = array_values($lineas);

            $ctx = $estado->context ?? [];
            unset($ctx['ultimo_pedido_id'], $ctx['last_order_id']);
            $estado->context = $ctx;
            $estado->save();

            $this->maquina->guardarCarrito($estado, $lineas);

            if (count($lineas) === 0) {
                $this->maquina->reiniciarCarrito($estado);

                return RespuestaBot::texto('Tu carrito quedó vacío 🛒 Escribe *hola* para volver a ver el catálogo.');
            }
        }

        return $this->mostrarResumen($estado);
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
        $metodo = (string) ($datos['metodo'] ?? '');
        if ($metodo === '' || ! isset($datos['nombre'], $datos['celular'])) {
            return false;
        }

        if ($metodo === 'shalom') {
            return isset($datos['dni'])
                && mb_strlen(trim((string) ($datos['distrito'] ?? ''))) >= 3;
        }

        return isset($datos['distrito'], $datos['direccion'])
            && $this->tieneUbicacionMotorizado($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function tieneUbicacionMotorizado(array $datos): bool
    {
        if (isset($datos['ubicacion_lat'], $datos['ubicacion_lng'])
            && (float) $datos['ubicacion_lat'] !== 0.0) {
            return true;
        }

        return isset($datos['ubicacion_texto']) && trim((string) $datos['ubicacion_texto']) !== '';
    }

    protected function prepararMetodo(ConversationState $estado, string $metodo): void
    {
        if ($metodo === 'motorizado') {
            $this->maquina->quitarCamposEnvio($estado, [
                'sede_shalom_id', 'sede_shalom_texto', 'dni', 'ubicacion_lat', 'ubicacion_lng', 'ubicacion_label', 'ubicacion_texto',
            ]);
        } else {
            $this->maquina->quitarCamposEnvio($estado, [
                'direccion', 'referencia', 'ubicacion_lat', 'ubicacion_lng', 'ubicacion_label', 'ubicacion_texto',
            ]);
        }
        $this->maquina->guardarDatosEnvio($estado, ['metodo' => $metodo]);
    }

    protected function textoIntroMotorizado(ConversationState $estado): string
    {
        $intro = $this->mensajes->plantilla('envio_intro_motorizado', [
            'horario_entregas' => (string) config('flujo_ventas.horario_entregas'),
        ]);
        $lineas = $this->textoLineasCarrito($estado);

        return $lineas !== '' ? $intro."\n\n".$lineas : $intro;
    }

    protected function textoIntroShalom(ConversationState $estado): string
    {
        $intro = $this->mensajes->plantilla('envio_intro_shalom');
        $lineas = $this->textoLineasCarrito($estado);

        return $lineas !== '' ? $intro."\n\n".$lineas : $intro;
    }

    protected function textoLineasCarrito(ConversationState $estado): string
    {
        $revalidado = $this->carrito->revalidar($this->maquina->carrito($estado));
        if ($revalidado['lineas'] === []) {
            return '';
        }

        $lineas = [];
        foreach ($revalidado['lineas'] as $l) {
            $lineas[] = '· '.trim($l['nombre'] ?? 'Producto')
                .' · '.trim($l['color'] ?? '-')
                .' · Talla '.trim($l['talla'] ?? '-');
        }

        return $this->mensajes->plantilla('envio_lineas_pedido', [
            'lineas' => implode("\n", $lineas),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function textoDetalleEnvio(array $datos): string
    {
        if (($datos['metodo'] ?? '') === 'shalom') {
            return $this->mensajes->plantilla('resumen_envio_shalom', [
                'sede' => $datos['distrito'] ?? 'Shalom',
                'dni' => $datos['dni'] ?? '-',
            ]);
        }

        $ubicacion = '';
        if (isset($datos['ubicacion_lat'], $datos['ubicacion_lng'])) {
            $ubicacion = '📍 https://maps.google.com/?q='
                .$datos['ubicacion_lat'].','.$datos['ubicacion_lng'];
        } elseif (isset($datos['ubicacion_texto'])) {
            $ubicacion = '📍 '.$datos['ubicacion_texto'];
        }

        return $this->mensajes->plantilla('resumen_envio_motorizado', [
            'distrito' => $datos['distrito'] ?? '',
            'direccion' => $datos['direccion'] ?? '',
            'ubicacion_linea' => $ubicacion,
        ]);
    }

    /**
     * @return array{lat: float, lng: float, name?: string}|null
     */
    protected function parsearUbicacionTexto(string $valor): ?array
    {
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $valor, $m) === 1) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }
        if (preg_match('/q=(-?\d+\.\d+),(-?\d+\.\d+)/', $valor, $m) === 1) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        return null;
    }
}
