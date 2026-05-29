<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class IntentTranslatorService
{
    /**
     * Traduce los metadatos interactivos de un botón/lista a un mensaje de texto plano comprensible por la IA.
     */
    public function translate(string $message, ?array $metadata = null): string
    {
        $buttonId = $metadata['interactive']['id']
            ?? $metadata['interactive']['button_reply']['id']
            ?? $metadata['interactive']['list_reply']['id']
            ?? null;

        if (!$buttonId) {
            return $message;
        }

        Log::info('IntentTranslatorService: Mapeando ID de botón interactivo', ['button_id' => $buttonId]);
        if ($buttonId === 'escalate_human') {
            return 'Quiero hablar con un asesor humano';
        }
        if ($buttonId === 'confirm_order') {
            return 'Sí, confirmo mi pedido';
        }
        if ($buttonId === 'shipping_shalom') {
            return 'Quiero el envío por Shalom (Agencia)';
        }
        if ($buttonId === 'shipping_motorizado') {
            return 'Quiero el envío por Motorizado a mi distrito';
        }
        if ($buttonId === 'payment_yape') {
            return 'Pagaré por Yape';
        }
        if ($buttonId === 'payment_card') {
            return 'Quiero pagar con tarjeta o link de pago';
        }
        if ($buttonId === 'proceed_payment') {
            return 'sí continuar con el pago';
        }
        if ($buttonId === 'shalom_lima') {
            return 'shalom lima';
        }
        if ($buttonId === 'shalom_provincia') {
            return 'shalom provincia';
        }
        if ($buttonId === 'skip_style_filter') {
            return 'skip_style_filter';
        }
        if (preg_match('/^pick_style_([a-z0-9_]+)$/', $buttonId, $matches)) {
            return 'pick_style_' . $matches[1];
        }
        if (preg_match('/^size_([a-z0-9]+)$/i', $buttonId, $matches)) {
            return 'Quiero talla ' . strtoupper($matches[1]);
        }

        // Mapeos con expresiones regulares para botones dinámicos
        if (preg_match('/^view_img_(\d+)$/', $buttonId, $matches)) {
            return "Quiero ver la foto del vestido ID {$matches[1]}";
        }

        if (preg_match('/^check_stock_(\d+)_(.+)$/', $buttonId, $matches)) {
            $productId = $matches[1];
            $color = urldecode($matches[2]);
            return "Quiero consultar stock del vestido ID {$productId} en color {$color}";
        }

        if (preg_match('/^buy_product_(\d+)$/', $buttonId, $matches)) {
            return "pick_product_{$matches[1]}";
        }

        if (preg_match('/^pick_category_(\d+)$/', $buttonId, $matches)) {
            return "pick_category_{$matches[1]}";
        }

        if ($buttonId === 'show_all_products') {
            return 'show_all_products';
        }

        if (preg_match('/^pick_product_(\d+)$/', $buttonId, $matches)) {
            return "pick_product_{$matches[1]}";
        }

        if (preg_match('/^pick_color_(\d+)_(.+)$/', $buttonId, $matches)) {
            return "pick_color_{$matches[1]}_" . urldecode($matches[2]);
        }

        return $message;
    }
}
