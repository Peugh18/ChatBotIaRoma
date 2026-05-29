<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\ConversationState;

/**
 * Construye el system prompt del LLM con contexto de negocio y contrato de herramientas.
 */
class PromptBuilder
{
    public function __construct(
        protected BusinessConfigService $business
    ) {
    }

    public function buildSystemPrompt(ConversationState $state, array $customerContext): string
    {
        $settings = $this->business->bot() ?? new BotSetting();
        $basePrompt = $settings->system_prompt
            ?: (string) config('bot.prompt.default_persona', 'Eres Roma, asesora de ventas de Vestidos Roma.');

        $maxLines = (int) config('bot.prompt.max_response_lines', 3);
        $maxButtons = (int) config('bot.prompt.max_interactive_buttons', 3);
        $maxListRows = (int) config('bot.prompt.max_interactive_list_rows', 10);
        $tools = config('bot.tools', []);

        $sections = [
            $basePrompt,
            $this->buildXmlSection('empresa', $this->buildCompanySection()),
            $this->buildXmlSection('cliente', $this->buildCustomerSection($state, $customerContext)),
            $this->buildXmlSection('carrito', $this->buildCartSection($state)),
            $this->buildXmlSection('ultimos_productos', $this->buildLastShownSection($state)),
            $this->buildXmlSection('contrato', $this->buildContractSection($maxLines, $maxButtons, $maxListRows, $tools)),
        ];

        return implode("\n\n", array_filter($sections));
    }

    protected function buildXmlSection(string $tag, string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        return "<{$tag}>\n{$content}\n</{$tag}>";
    }

    protected function buildCompanySection(): string
    {
        $block = $this->business->formatCompanyPromptBlock();

        return $block !== '' ? $block : 'Sin datos de empresa configurados.';
    }

    protected function buildCustomerSection(ConversationState $state, array $customerContext): string
    {
        $lines = [
            "- Teléfono: {$state->phone_number}",
            '- Nombre: ' . ($customerContext['name'] ?? 'Cliente'),
            '- Historial de compras: S/ ' . ($customerContext['total_spent'] ?? '0.00'),
        ];

        if (!empty($customerContext['notes'])) {
            $lines[] = "- Notas del asesor: {$customerContext['notes']}";
        }

        return implode("\n", $lines);
    }

    protected function buildCartSection(ConversationState $state): string
    {
        $contextData = $state->context ?? [];

        if (empty($contextData['current_product_name'])) {
            return '- [Vacío]';
        }

        $line = '- Vestido: "' . $contextData['current_product_name'] . '"';
        if (!empty($contextData['current_color'])) {
            $line .= ' | Color: ' . $contextData['current_color'];
        }
        if (!empty($contextData['current_size'])) {
            $line .= ' | Talla: ' . $contextData['current_size'];
        }

        return $line;
    }

    protected function buildLastShownSection(ConversationState $state): string
    {
        $products = $state->context['last_shown_products'] ?? [];

        if ($products === []) {
            return "- [Ninguno]\n(Si el cliente dice 'el primero' o 'ese', pide aclaración o usa get_products.)";
        }

        $lines = [];
        foreach ($products as $index => $p) {
            $pos = $index + 1;
            $stock = !empty($p['has_stock']) ? 'Sí' : 'No';
            $lines[] = "- [{$pos}] ID {$p['id']}: \"{$p['name']}\" | S/ {$p['final_price']} | Stock: {$stock}";
        }
        $lines[] = "(Referencias: 'el primero' = [1], 'el segundo' = [2], etc.)";

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $tools
     */
    protected function buildContractSection(int $maxLines, int $maxButtons, int $maxListRows, array $tools): string
    {
        $toolList = implode(', ', array_map(fn (string $t) => "`{$t}`", $tools));
        $toolBullets = '';
        foreach ($tools as $tool) {
            $toolBullets .= $this->toolRuleLine($tool);
        }

        return implode("\n", [
            '1. PROHIBICIÓN DE INVENCIÓN: Nunca inventes vestidos, precios, stock ni costos de envío. Usa herramientas.',
            '2. TOOL-FIRST: Para catálogo, precios, tallas, stock, envíos, pedidos o CRM → llama la herramienta correcta.',
            "   Herramientas disponibles: {$toolList}.",
            $toolBullets,
            '3. VENTA POR PRODUCTO: Muestra vestidos concretos (nombre, precio, foto). Categorías solo filtran.',
            "4. WHATSAPP UI: `send_interactive_buttons` (máx {$maxButtons}) o `send_interactive_list` (máx {$maxListRows} filas). IDs: pick_product_{id}.",
            "5. CONCISIÓN: Respuestas finales ≤ {$maxLines} líneas, tono {$this->business->salesTone()}, 1 CTA ({$this->business->salesClosingCta()}).",
            '6. PEDIDO: Si confirma productos + talla/color + envío/pago → `create_order`.',
            '7. HUMANO: Quejas, reclamos o pide persona → `escalate_to_human` de inmediato.',
        ]);
    }

    protected function toolRuleLine(string $tool): string
    {
        return match ($tool) {
            'get_products' => '   - get_products: catálogo, vestidos, precios.',
            'check_stock' => '   - check_stock: colores, tallas, disponibilidad.',
            'get_delivery_cost' => '   - get_delivery_cost: envío por distrito.',
            'get_customer_profile' => '   - get_customer_profile: historial CRM.',
            'get_order_status' => '   - get_order_status: estado de pedido.',
            'send_product_image' => '   - send_product_image: foto del vestido elegido.',
            'send_interactive_buttons', 'send_interactive_list' => '',
            default => "   - {$tool}: según nombre.",
        };
    }
}
