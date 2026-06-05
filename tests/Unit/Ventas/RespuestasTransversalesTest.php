<?php

namespace Tests\Unit\Ventas;

use App\Ventas\Manejadores\ManejadorRespuestasTransversales;
use Tests\TestCase;

class RespuestasTransversalesTest extends TestCase
{
    public function test_es_otras_categorias_no_dispara_quiere_categorias(): void
    {
        $transversal = app(ManejadorRespuestasTransversales::class);

        $this->assertTrue($transversal->esOtrasCategorias('otras categorias'));
        $this->assertTrue($transversal->esOtrasCategorias('Otras categorías'));
        $this->assertFalse($transversal->quiereCategorias('otras categorias'));
        $this->assertTrue($transversal->quiereCategorias('ver categorias'));
    }
}
