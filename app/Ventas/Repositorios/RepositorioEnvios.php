<?php

namespace App\Ventas\Repositorios;

use App\Models\DeliveryZone;
use Illuminate\Support\Collection;

class RepositorioEnvios
{
    /**
     * @return Collection<int, DeliveryZone>
     */
    public function zonas(): Collection
    {
        return DeliveryZone::query()->orderBy('district')->get();
    }

    public function resolverDistrito(string $texto): ?DeliveryZone
    {
        $normalizado = $this->normalizarDistrito($texto);
        if ($normalizado === '') {
            return null;
        }

        $zonas = $this->zonas();
        foreach ($zonas as $zona) {
            if ($this->normalizarDistrito($zona->district) === $normalizado) {
                return $zona;
            }
        }

        $alias = config('flujo_ventas.alias_distritos', []);
        foreach ($alias as $clave => $nombreOficial) {
            if ($normalizado === $this->normalizarDistrito($clave)
                || $normalizado === $this->normalizarDistrito($nombreOficial)) {
                foreach ($zonas as $zona) {
                    if ($this->normalizarDistrito($zona->district) === $this->normalizarDistrito($nombreOficial)) {
                        return $zona;
                    }
                }
            }
        }

        foreach ($zonas as $zona) {
            if (str_contains($this->normalizarDistrito($zona->district), $normalizado)
                || str_contains($normalizado, $this->normalizarDistrito($zona->district))) {
                return $zona;
            }
        }

        return null;
    }

    public function costoMotorizado(?DeliveryZone $zona): float
    {
        if ($zona) {
            return (float) $zona->cost_motorizado;
        }

        return 15.0;
    }

    public function costoShalom(string $region = 'provincia'): float
    {
        return $region === 'lima'
            ? (float) config('flujo_ventas.costo_shalom_lima', 10)
            : (float) config('flujo_ventas.costo_shalom_provincia', 12);
    }

    public function normalizarDistrito(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return $t;
    }
}
