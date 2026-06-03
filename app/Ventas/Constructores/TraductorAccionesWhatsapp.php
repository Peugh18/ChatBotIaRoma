<?php

namespace App\Ventas\Constructores;

class TraductorAccionesWhatsapp
{
    public function traducir(string $mensaje, ?array $metadata = null): string
    {
        $id = $metadata['interactive']['id']
            ?? $metadata['interactive']['button_reply']['id']
            ?? $metadata['interactive']['list_reply']['id']
            ?? null;

        if (! $id) {
            return $mensaje;
        }

        return match (true) {
            $id === 'escalate_human' => 'Quiero hablar con un asesor humano',
            $id === 'rescue_categories' => 'ver categorias',
            $id === 'rescue_cart' => 'ver carrito',
            $id === 'rescue_continue' => 'continuar pedido',
            $id === 'rescue_human' => 'Quiero hablar con un asesor humano',
            $id === 'add_more_product' => 'agregar otro producto',
            $id === 'confirm_cart' => 'confirmar pedido',
            $id === 'other_categories' => 'otras categorias',
            $id === 'same_data_yes' => 'si mismos datos',
            $id === 'update_data' => 'actualizar datos',
            $id === 'restart_yes' => 'si reiniciar',
            $id === 'restart_no' => 'no reiniciar',
            preg_match('/^page_categories_(\d+)$/', $id, $m) === 1 => 'page_categories_'.$m[1],
            preg_match('/^pick_category_(\d+)$/', $id, $m) === 1 => "pick_category_{$m[1]}",
            preg_match('/^page_shalom_(\d+)$/', $id, $m) === 1 => 'page_shalom_'.$m[1],
            preg_match('/^pick_product_(\d+)$/', $id, $m) === 1 => "pick_product_{$m[1]}",
            preg_match('/^pick_color_(\d+)_(.+)$/', $id, $m) === 1 => 'pick_color_'.$m[1].'_'.urldecode($m[2]),
            preg_match('/^size_idx_(\d+)$/', $id, $m) === 1 => 'size_idx_'.$m[1],
            preg_match('/^size_([a-z0-9]+)$/i', $id, $m) === 1 => 'Quiero talla '.strtoupper($m[1]),
            preg_match('/^page_products_(\d+)$/', $id, $m) === 1 => 'page_products_'.$m[1],
            preg_match('/^pick_similar_(\d+)$/', $id, $m) === 1 => 'pick_similar_'.$m[1],
            preg_match('/^pick_shalom_(\d+)$/', $id, $m) === 1 => 'pick_shalom_'.$m[1],
            $id === 'shipping_motorizado' => 'shipping_motorizado',
            $id === 'shipping_shalom' => 'shipping_shalom',
            $id === 'confirm_resumen' => 'confirmar resumen',
            $id === 'edit_cart' => 'editar compra',
            $id === 'cancel_edit_cart' => 'cancel_edit_cart',
            preg_match('/^rm_item_(\d+)$/', $id, $m) === 1 => 'rm_item_'.$m[1],
            $id === 'pay_yape' => 'pago_yape',
            $id === 'pay_card' => 'pago_tarjeta',
            $id === 'ver_otros_modelos' => 'ver_otros_modelos',
            default => $mensaje,
        };
    }
}
