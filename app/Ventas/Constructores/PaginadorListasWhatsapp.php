<?php

namespace App\Ventas\Constructores;

/**
 * Listas WhatsApp: máx 10 filas. Reservamos 1 para «Ver más» → 9 ítems por página.
 */
class PaginadorListasWhatsapp
{
    public function porPagina(): int
    {
        return (int) config('flujo_ventas.lista_filas_por_pagina', 9);
    }

    /**
     * @param  list<array{id: string, title: string, description?: string}>  $todas
     * @return array{
     *   opciones: list<array{id: string, title: string, description?: string}>,
     *   pagina: int,
     *   hay_mas: bool,
     *   total: int
     * }
     */
    public function pagina(array $todas, int $pagina, string $prefijoSiguiente): array
    {
        $todas = array_values($todas);
        $porPagina = $this->porPagina();
        $offset = $pagina * $porPagina;
        $restantes = max(0, count($todas) - $offset);
        $hayMas = $restantes > $porPagina;
        $cantidad = $hayMas ? $porPagina : $restantes;

        $opciones = array_slice($todas, $offset, $cantidad);

        if ($hayMas) {
            $opciones[] = [
                'id' => $prefijoSiguiente.'_'.($pagina + 1),
                'title' => 'Ver más opciones',
                'description' => 'Página '.($pagina + 2),
            ];
        }

        return [
            'opciones' => $opciones,
            'pagina' => $pagina,
            'hay_mas' => $hayMas,
            'total' => count($todas),
        ];
    }
}
