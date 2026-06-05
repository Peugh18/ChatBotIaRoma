<?php

namespace App\Ventas\Manejadores;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Ventas\Constructores\ConstructorInteractivos;
use App\Ventas\Constructores\ConstructorMensaje;
use App\Ventas\Constructores\PaginadorListasWhatsapp;
use App\Ventas\Contratos\RespuestaBot;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Repositorios\RepositorioCatalogo;

class ManejadorInicio
{
    public function __construct(
        protected ConstructorMensaje $mensajes,
        protected ConstructorInteractivos $interactivos,
        protected MaquinaEstadosVentas $maquina,
        protected RepositorioCatalogo $catalogo,
        protected PaginadorListasWhatsapp $paginador,
    ) {}

    public function mostrarCategorias(
        ?Customer $cliente,
        ConversationState $estado,
        int $pagina = 0,
        string $contextoLista = 'inicio'
    ): RespuestaBot {
        $categorias = $this->catalogo->categoriasConProductosVisibles();
        if ($categorias->isEmpty()) {
            return RespuestaBot::texto($this->mensajes->plantilla('sin_datos_bd'));
        }

        $todas = [];
        foreach ($categorias as $cat) {
            $todas[] = [
                'id' => 'pick_category_'.$cat->id,
                'title' => mb_substr($cat->name, 0, 24),
            ];
        }

        $pag = $this->paginador->pagina($todas, $pagina, 'page_categories');
        $cuerpo = $pagina === 0
            ? $this->cuerpoListaCategorias($cliente, $contextoLista)
            : $this->mensajes->plantilla('lista_pagina_siguiente', ['pagina' => (string) ($pagina + 1)]);

        $payload = $this->interactivos->construir(
            $cuerpo,
            $pag['opciones'],
            $this->mensajes->plantilla('pie_categorias')
        );

        $this->maquina->establecer($estado, EtapaVentas::CATEGORIA);

        return RespuestaBot::conInteractivo('', $payload);
    }

    public function esSaludo(string $mensaje): bool
    {
        $m = trim(mb_strtolower($mensaje));
        if (mb_strlen($m) > 30) {
            return false;
        }
        $saludos = [
            'hola', 'holi', 'holaaa', 'buenas', 'buenos dias', 'buenos días',
            'buenas tardes', 'buenas noches', 'hi', 'hello', 'hey', 'inicio', 'empezar',
        ];
        foreach ($saludos as $g) {
            if ($m === $g || $m === $g.'!' || $m === $g.'.') {
                return true;
            }
        }

        return false;
    }

    protected function cuerpoListaCategorias(?Customer $cliente, string $contextoLista): string
    {
        return match ($contextoLista) {
            'otras' => $this->mensajes->plantilla('otras_categorias_intro'),
            'agregar_otro' => $this->mensajes->plantilla('agregar_otro_categorias_intro'),
            default => $this->mensajes->saludo($cliente),
        };
    }
}
