<?php

namespace App\Support;

use App\Models\ConversationState;

/**
 * Lectura de etapa de venta desde context (legacy + nuevo) sin máquina de estados.
 */
class EtapaVenta
{
    public const ESPERANDO_VALIDACION_PAGO = 'esperando_validacion_pago';

    public const LEGACY_VALIDACION_PAGO = 'awaiting_payment_validation';

    public static function obtener(?ConversationState $estado): ?string
    {
        if (! $estado) {
            return null;
        }

        $ctx = $estado->context ?? [];

        return $ctx['etapa_venta'] ?? $ctx['sales_stage'] ?? null;
    }

    public static function esValidacionPago(?ConversationState $estado): bool
    {
        $etapa = self::obtener($estado);

        return in_array($etapa, [
            self::ESPERANDO_VALIDACION_PAGO,
            self::LEGACY_VALIDACION_PAGO,
            'validacion_pago',
        ], true);
    }
}
