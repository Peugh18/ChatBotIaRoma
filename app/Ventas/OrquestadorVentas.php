<?php

namespace App\Ventas;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Services\ServicioConfigNegocio;
use App\Support\ContratoMensajeWhatsapp;
use App\Ventas\Constructores\TraductorAccionesWhatsapp;
use App\Ventas\Contratos\RespuestaBot;
use App\Ventas\Manejadores\ManejadorCheckout;
use App\Ventas\Manejadores\ManejadorMatchImagen;
use App\Ventas\Manejadores\ManejadorRespuestasTransversales;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Servicios\ServicioAntiBucle;

/**
 * Punto de entrada del flujo: traduce acciones, delega al enrutador y aplica anti-bucle.
 */
class OrquestadorVentas
{
    public function __construct(
        protected ServicioConfigNegocio $configNegocio,
        protected MaquinaEstadosVentas $maquina,
        protected TraductorAccionesWhatsapp $traductor,
        protected EnrutadorVentas $enrutador,
        protected ManejadorMatchImagen $matchImagen,
        protected ManejadorCheckout $checkout,
        protected ManejadorRespuestasTransversales $transversal,
        protected ServicioAntiBucle $antiBucle,
    ) {}

    /**
     * @return array{text: string, metadata: array}
     */
    public function procesar(
        string $telefono,
        string $mensaje,
        ?string $urlImagen = null,
        ?array $metadataEntrada = null
    ): array {
        $ajustes = $this->configNegocio->bot();
        if (! $ajustes?->auto_reply_enabled) {
            return ['text' => '', 'metadata' => []];
        }

        $mensaje = $this->traductor->traducir($mensaje, $metadataEntrada);

        $cliente = Customer::firstOrCreate(
            ['phone_number' => $telefono],
            ['first_seen_at' => now(), 'last_seen_at' => now(), 'segment' => 'lead']
        );
        $cliente->update(['last_seen_at' => now()]);

        $estado = ConversationState::firstOrCreate(
            ['phone_number' => $telefono],
            [
                'customer_id' => $cliente->id,
                'current_state' => 'greeting',
                'context' => [],
                'last_activity_at' => now(),
            ]
        );

        if ($estado->requires_human) {
            return ['text' => '', 'metadata' => []];
        }

        $estado->update(['last_activity_at' => now()]);

        $etapa = $this->maquina->obtener($estado);

        $ubicacion = ContratoMensajeWhatsapp::ubicacionDesdePayload($metadataEntrada);

        if ($urlImagen) {
            $respuesta = $this->procesarImagen($estado, $urlImagen, $etapa);
        } elseif ($ubicacion !== null) {
            $respuesta = $this->checkout->capturarUbicacion($estado, $cliente, $ubicacion);
        } else {
            $respuesta = $this->enrutador->despachar($estado, $cliente, $mensaje, $etapa);
        }

        $sinAvanceUtil = ! $respuesta->debeEnviar() || $this->esMenuRescate($respuesta);
        if ($this->antiBucle->registrarMensaje($estado, $this->maquina->obtener($estado), $sinAvanceUtil)) {
            $this->antiBucle->reiniciarContador($estado);
            $respuesta = $this->transversal->menuRescate($cliente);
        }

        $this->sincronizarImagenPendienteEnContexto($estado, $respuesta);

        return $respuesta->aArray();
    }

    protected function procesarImagen(ConversationState $estado, string $urlImagen, ?string $etapa): RespuestaBot
    {
        if ($etapa === EtapaVentas::COMPROBANTE) {
            return $this->checkout->recibirComprobante($estado, $urlImagen);
        }

        return $this->matchImagen->procesarFoto($estado, $urlImagen);
    }

    protected function esMenuRescate(RespuestaBot $respuesta): bool
    {
        $intro = trim(config('copy_ventas.rescate_sin_nombre', ''));
        $texto = trim($respuesta->texto);

        return $intro !== '' && str_contains($texto, mb_substr($intro, 0, 20));
    }

    protected function sincronizarImagenPendienteEnContexto(ConversationState $estado, RespuestaBot $respuesta): void
    {
        if (! $respuesta->urlImagen) {
            return;
        }

        $ctx = $estado->context ?? [];
        $ctx['pending_image_url'] = $respuesta->urlImagen;
        $ctx['pending_image_caption'] = $respuesta->captionImagen ?? '📸';
        $estado->context = $ctx;
        $estado->save();
    }
}
