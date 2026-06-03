<?php

namespace App\Ventas\Servicios;

use App\Models\ConversationState;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;

class ServicioAntiBucle
{
    public const CLAVE_INTENTOS = 'intentos_sin_avance';

    public const CLAVE_ULTIMA_ETAPA = 'ultima_etapa_avance';

    public function __construct(
        protected MaquinaEstadosVentas $maquina,
    ) {}

    /**
     * @param  bool  $sinAvanceUtil  true si la respuesta no avanzó el flujo (vacía o menú rescate)
     */
    public function registrarMensaje(ConversationState $estado, ?string $etapaActual, bool $sinAvanceUtil = true): bool
    {
        $max = (int) config('flujo_ventas.max_intentos_sin_avance', 3);
        $ctx = $estado->context ?? [];
        $etapaAnterior = $ctx[self::CLAVE_ULTIMA_ETAPA] ?? null;

        if (! $sinAvanceUtil) {
            $ctx[self::CLAVE_INTENTOS] = 0;
            if ($etapaActual !== null) {
                $ctx[self::CLAVE_ULTIMA_ETAPA] = $etapaActual;
            }
            $estado->context = $ctx;
            $estado->save();

            return false;
        }

        if ($etapaActual !== null && $etapaActual !== $etapaAnterior) {
            $ctx[self::CLAVE_INTENTOS] = 0;
            $ctx[self::CLAVE_ULTIMA_ETAPA] = $etapaActual;
            $estado->context = $ctx;
            $estado->save();

            return false;
        }

        $intentos = (int) ($ctx[self::CLAVE_INTENTOS] ?? 0) + 1;
        $ctx[self::CLAVE_INTENTOS] = $intentos;
        if ($etapaActual !== null) {
            $ctx[self::CLAVE_ULTIMA_ETAPA] = $etapaActual;
        }
        $estado->context = $ctx;
        $estado->save();

        return $intentos >= $max;
    }

    public function reiniciarContador(ConversationState $estado): void
    {
        $ctx = $estado->context ?? [];
        $ctx[self::CLAVE_INTENTOS] = 0;
        $estado->context = $ctx;
        $estado->save();
    }
}
