<?php

namespace App\Services;

use App\Ventas\OrquestadorVentas;

/**
 * Punto de entrada del bot de ventas — delega al orquestador V1.
 */
class ServicioBotEntrada
{
    public function __construct(
        protected OrquestadorVentas $orquestador,
    ) {}

    /**
     * @return array{text: string, metadata: array}
     */
    public function procesar(
        string $telefono,
        string $mensaje,
        ?string $urlImagen = null,
        ?array $metadata = null
    ): array {
        return $this->orquestador->procesar($telefono, $mensaje, $urlImagen, $metadata);
    }
}
