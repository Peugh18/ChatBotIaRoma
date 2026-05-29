<?php

namespace App\Services;

class ResponseSanitizer
{
    /**
     * Patrones que indican que el modelo intentó emitir un tool call como TEXTO.
     */
    public const GARBAGE_PATTERNS = [
        '/<\s*function[\s=][^>]*>/i',
        '/<\/?\s*function\s*>/i',
        '/<\s*tool_use[\s>]/i',
        '/<\s*tool_call[\s>]/i',
        '/<\s*invoke[\s>]/i',
        '/<\|tool[^|]*\|>/i',

        '/\{\s*"(name|function|tool|tool_name)"\s*:/i',
        '/\{\s*"arguments?"\s*:/i',
        '/\{\s*"(query|product_id|district|items|color)"\s*:[^}]{2,}\}/i',

        '/\b(get_products|check_stock|get_delivery_cost|create_order|escalate_to_human|send_product_image|send_interactive_buttons|send_interactive_list)\s*\(/i',

        '/```(json|tool|function)/i',
        '/<\|[a-z_]+\|>/i',
    ];

    /**
     * Detecta si la respuesta contiene basura técnica que indica un tool call fallido.
     */
    public static function hasTechnicalGarbage(string $text): bool
    {
        foreach (self::GARBAGE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Limpia la respuesta de cualquier resto de marcado de herramientas.
     */
    public static function sanitize(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Eliminar tags completos estilo XML
        $text = preg_replace('/<\s*function\b[^>]*>(.*?)<\/\s*function\s*>/is', '', $text);
        $text = preg_replace('/<\s*tool_use\b[^>]*>(.*?)<\/\s*tool_use\s*>/is', '', $text);
        $text = preg_replace('/<\s*tool_call\b[^>]*>(.*?)<\/\s*tool_call\s*>/is', '', $text);
        $text = preg_replace('/<\s*invoke\b[^>]*>(.*?)<\/\s*invoke\s*>/is', '', $text);

        // Eliminar tags sueltos
        $text = preg_replace('/<\s*\/?\s*function\b[^>]*>/i', '', $text);
        $text = preg_replace('/<\s*\/?\s*tool_use\b[^>]*>/i', '', $text);
        $text = preg_replace('/<\s*\/?\s*tool_call\b[^>]*>/i', '', $text);
        $text = preg_replace('/<\s*\/?\s*invoke\b[^>]*>/i', '', $text);

        // Eliminar tokens especiales del modelo
        $text = preg_replace('/<\|[a-z_]+\|>/i', '', $text);

        // Eliminar bloques JSON que emulen llamadas
        $text = preg_replace('/\{\s*"(name|function|tool|tool_name)"\s*:[^{}]*?(\{[^{}]*\})?[^{}]*?\}/is', '', $text);
        $text = preg_replace('/\{[^{}]*?"(query|product_id|color|district|items|arguments)"[^{}]*?\}/is', '', $text);

        // Eliminar llamadas a funciones estilo JS/Python
        $text = preg_replace('/\b(get_products|check_stock|get_delivery_cost|create_order|escalate_to_human|send_product_image|send_interactive_buttons|send_interactive_list)\s*\([^)]*\)/i', '', $text);

        // Eliminar bloques de código markdown con JSON/tool
        $text = preg_replace('/```(?:json|tool|function|python)?\s*\{.*?\}\s*```/is', '', $text);
        $text = preg_replace('/```(json|tool|function)[\s\S]*?```/i', '', $text);

        // Limpiar espacios y saltos múltiples
        $text = preg_replace('/[\r\n]{3,}/', "\n\n", $text);
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = trim($text);

        return strlen($text) < 3 ? '' : $text;
    }
}
