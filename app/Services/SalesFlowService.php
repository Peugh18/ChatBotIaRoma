<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\ConversationState;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;

/**
 * Flujo comercial determinístico (prompt maestro):
 * pedido → envío → distrito/tarifa → resumen → pago → comprobante → datos envío → registro.
 */
class SalesFlowService
{
    public function __construct(
        protected ToolExecutorService $tools,
        protected BusinessConfigService $business,
        protected CustomerDataSyncService $customerSync
    ) {}

    public function handleStage(ConversationState $state, string $message, bool $hasImage = false, ?string $imageUrl = null): ?array
    {
        $ctx = $state->context ?? [];
        $stage = $ctx['sales_stage'] ?? null;
        $msg = mb_strtolower(trim($message));

        if (preg_match('/\btalla\s*(s|m|l|xl|xs)\b/u', $msg, $m)) {
            $ctx['current_size'] = strtoupper($m[1]);
            $state->context = $ctx;
            $state->save();
        }
        if (preg_match('/\bcolor\s+([a-záéíóúñ]+)/iu', $message, $m)) {
            $ctx['current_color'] = mb_strtolower($m[1]);
            $state->context = $ctx;
            $state->save();
        }

        return match ($stage) {
            'awaiting_order_confirmation' => $this->handleOrderConfirmation($state, $msg),
            'awaiting_shipping_method' => $this->handleShippingMethod($state, $msg),
            'awaiting_district' => $this->handleDistrict($state, $message),
            'awaiting_shalom_region' => $this->handleShalomRegion($state, $msg),
            'awaiting_order_summary' => $this->handleOrderSummaryAck($state, $msg),
            'awaiting_payment_method' => $this->handlePaymentMethod($state, $msg),
            'awaiting_payment_proof' => $this->handlePaymentProof($state, $msg, $hasImage, $imageUrl),
            'awaiting_payment_validation' => $this->handleAwaitingPaymentValidation($state),
            'awaiting_card_full_name' => $this->handleCardStep($state, $message, 'awaiting_card_email', 'card_full_name'),
            'awaiting_card_email' => $this->handleCardStep($state, $message, 'awaiting_card_phone', 'card_email'),
            'awaiting_card_phone' => $this->handleCardPhone($state, $message),
            default => $this->handleShippingDataCollection($state, $message, $stage),
        };
    }

    public function armConfirmationStage(ConversationState $state): void
    {
        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_order_confirmation';
        $state->context = $ctx;
        $state->save();
    }

    protected function handleOrderConfirmation(ConversationState $state, string $msg): ?array
    {
        if ($this->isPositive($msg)) {
            return $this->askShippingMethod($state);
        }
        if ($this->isNegative($msg)) {
            $ctx = $state->context ?? [];
            $ctx['sales_stage'] = 'awaiting_product_selection';
            $state->context = $ctx;
            $state->save();

            return ['text' => $this->business->applyBrandCta('Perfecto hermosa ✨ Cuando quieras me dices y seguimos 💕'), 'metadata' => []];
        }

        return null;
    }

    protected function askShippingMethod(ConversationState $state): array
    {
        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_shipping_method';
        $ctx['order_confirmed'] = true;
        $state->context = $ctx;
        $state->save();

        $text = '¿Cómo deseas recibir tu pedido? ✨';
        $this->tools->executeSendInteractiveButtons(
            $state,
            $text,
            [
                ['id' => 'shipping_motorizado', 'title' => 'Motorizado'],
                ['id' => 'shipping_shalom', 'title' => 'Shalom'],
                ['id' => 'escalate_human', 'title' => 'Hablar asesor'],
            ],
            'Medio de envío'
        );

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    protected function handleShippingMethod(ConversationState $state, string $msg): ?array
    {
        $ctx = $state->context ?? [];

        if (str_contains($msg, 'shalom')) {
            $ctx['sales_stage'] = 'awaiting_shalom_region';
            $ctx['shipping_method'] = 'shalom';
            $state->context = $ctx;
            $state->save();

            $text = '¿Tu envío Shalom es Lima o provincia? 💕';
            $this->tools->executeSendInteractiveButtons(
                $state,
                $text,
                [
                    ['id' => 'shalom_lima', 'title' => 'Lima'],
                    ['id' => 'shalom_provincia', 'title' => 'Provincia'],
                ],
                'Zona Shalom'
            );

            return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
        }

        if (str_contains($msg, 'motorizado')) {
            $ctx['sales_stage'] = 'awaiting_district';
            $ctx['shipping_method'] = 'motorizado';
            $state->context = $ctx;
            $state->save();

            return [
                'text' => $this->business->applyBrandCta('¿A qué distrito sería el envío hermosa? 💕'),
                'metadata' => [],
            ];
        }

        return null;
    }

    protected function handleDistrict(ConversationState $state, string $message): ?array
    {
        $zone = $this->resolveDeliveryZone($message);
        if (! $zone) {
            return [
                'text' => $this->business->applyBrandCta(
                    'No ubiqué ese distrito en nuestra tabla 😊 Escríbelo completo (ej: Miraflores, Surco, SMP).'
                ),
                'metadata' => [],
            ];
        }

        $ctx = $state->context ?? [];
        $ctx['delivery_district'] = $zone->district;
        $ctx['delivery_cost'] = (float) $zone->cost_motorizado;
        $state->context = $ctx;
        $state->save();

        return $this->showOrderSummary($state);
    }

    protected function handleShalomRegion(ConversationState $state, string $msg): ?array
    {
        $ctx = $state->context ?? [];

        if (str_contains($msg, 'lima') || $msg === 'shalom_lima') {
            $ctx['delivery_cost'] = (float) config('sales_flow.shalom_lima_cost', 10);
            $ctx['shalom_region'] = 'lima';
        } elseif (str_contains($msg, 'provincia') || $msg === 'shalom_provincia') {
            $ctx['delivery_cost'] = (float) config('sales_flow.shalom_provincia_cost', 12);
            $ctx['shalom_region'] = 'provincia';
        } else {
            return [
                'text' => $this->business->applyBrandCta('Elige *Lima* o *Provincia* para cotizar Shalom 💕'),
                'metadata' => [],
            ];
        }

        $state->context = $ctx;
        $state->save();

        return $this->showOrderSummary($state);
    }

    protected function showOrderSummary(ConversationState $state): array
    {
        $ctx = $state->context ?? [];
        $totals = $this->calculateTotals($ctx);
        $ctx['product_subtotal'] = $totals['product'];
        $ctx['delivery_cost'] = $totals['delivery'];
        $ctx['order_total'] = $totals['total'];
        $ctx['sales_stage'] = 'awaiting_order_summary';
        $state->context = $ctx;
        $state->save();

        $text = $this->buildOrderSummaryText($ctx, $totals);
        $this->tools->executeSendInteractiveButtons(
            $state,
            $text,
            [
                ['id' => 'proceed_payment', 'title' => 'Continuar pago'],
                ['id' => 'escalate_human', 'title' => 'Hablar asesor'],
            ],
            'Resumen pedido'
        );

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    protected function handleOrderSummaryAck(ConversationState $state, string $msg): ?array
    {
        if (! $this->isPositive($msg) && ! str_contains($msg, 'pago') && ! str_contains($msg, 'continuar')) {
            return null;
        }

        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_payment_method';
        $state->context = $ctx;
        $state->save();

        $text = '¿Cómo deseas realizar el pago hermosa? 💕';
        $this->tools->executeSendInteractiveButtons(
            $state,
            $text,
            [
                ['id' => 'payment_yape', 'title' => 'Yape'],
                ['id' => 'payment_card', 'title' => 'Tarjeta'],
                ['id' => 'escalate_human', 'title' => 'Hablar asesor'],
            ],
            'Método de pago'
        );

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    protected function handlePaymentMethod(ConversationState $state, string $msg): ?array
    {
        $ctx = $state->context ?? [];

        if (str_contains($msg, 'yape')) {
            $ctx['sales_stage'] = 'awaiting_payment_proof';
            $ctx['payment_method'] = 'yape';
            $state->context = $ctx;
            $state->save();

            return ['text' => $this->business->applyBrandCta($this->business->yapePaymentMessage()), 'metadata' => []];
        }

        if (str_contains($msg, 'tarjeta') || str_contains($msg, 'link')) {
            $ctx['sales_stage'] = 'awaiting_card_full_name';
            $ctx['payment_method'] = 'card_or_link';
            $state->context = $ctx;
            $state->save();

            return [
                'text' => $this->business->applyBrandCta('Perfecto 💕 ¿Cuál es tu nombre completo?'),
                'metadata' => [],
            ];
        }

        return null;
    }

    protected function handleCardStep(ConversationState $state, string $message, string $nextStage, string $field): ?array
    {
        if (mb_strlen(trim($message)) < 2) {
            return ['text' => $this->business->applyBrandCta('Por favor escríbelo completo hermosa 💕'), 'metadata' => []];
        }

        $ctx = $state->context ?? [];
        $ctx[$field] = trim($message);
        $ctx['sales_stage'] = $nextStage;
        $state->context = $ctx;
        $state->save();

        // Sincronizar datos del cliente después de guardar card_full_name o card_email
        if ($field === 'card_full_name' || $field === 'card_email') {
            $this->customerSync->syncFromConversationContext($state);
        }

        $prompt = match ($nextStage) {
            'awaiting_card_email' => '¿Tu correo electrónico?',
            'awaiting_card_phone' => '¿Tu número de celular?',
            default => '',
        };

        return ['text' => $this->business->applyBrandCta($prompt), 'metadata' => []];
    }

    public function handleCardPhone(ConversationState $state, string $message): ?array
    {
        if (! preg_match('/\d{7,}/', $message)) {
            return ['text' => $this->business->applyBrandCta('Indícame un celular válido (9 dígitos) 💕'), 'metadata' => []];
        }

        $ctx = $state->context ?? [];
        $ctx['card_phone'] = trim($message);
        $ctx['sales_stage'] = 'awaiting_shipping_data';
        $ctx['shipping_data_step'] = 0;
        $ctx['card_flow'] = true; // Marcar que es flujo tarjeta para escalar después
        $state->context = $ctx;
        $state->save();

        // Sincronizar datos del cliente después de guardar card_phone
        $this->customerSync->syncFromConversationContext($state);

        // NO escalar a humano aquí - esperar a completar datos de envío (opción A)

        return [
            'text' => $this->business->applyBrandCta(
                "Listo hermosa ✨ Un asesor te enviará el link de pago en breve.\n\n".$this->firstShippingDataPrompt($ctx)
            ),
            'metadata' => [],
        ];
    }

    protected function handlePaymentProof(ConversationState $state, string $msg, bool $hasImage, ?string $imageUrl = null): ?array
    {
        $isProof = $hasImage
          || str_contains($msg, 'captura')
          || str_contains($msg, 'comprobante')
          || str_contains($msg, 'ya pag')
          || str_contains($msg, 'enviado')
          || str_contains($msg, 'pago');

        if (! $isProof) {
            return null;
        }

        $ctx = $state->context ?? [];
        $ctx['payment_confirmed'] = false;
        $ctx['payment_validation_requested_at'] = now()->toDateTimeString();
        if ($imageUrl !== null && $imageUrl !== '') {
            $ctx['payment_proof_url'] = $imageUrl;
        }
        $ctx['sales_stage'] = 'awaiting_payment_validation';
        $state->context = $ctx;
        $state->save();

        $this->ensurePipelineOrderAwaitingValidation($state, $ctx);

        $this->tools->executeEscalateToHuman(
            $state,
            (string) config('sales_flow.payment_validation_escalation_reason', 'Comprobante de pago pendiente de validación')
        );

        $clientMessage = (string) config(
            'sales_flow.payment_validation_client_message',
            'Recibimos tu comprobante. Un momento, estamos validando tu pago ✨'
        );

        return [
            'text' => $this->business->applyBrandCta($clientMessage),
            'metadata' => ['trigger_human_escalation' => true],
        ];
    }

    protected function handleAwaitingPaymentValidation(ConversationState $state): ?array
    {
        $ctx = $state->context ?? [];
        $orderId = (int) ($ctx['last_order_id'] ?? 0);
        $order = $orderId > 0 ? Order::find($orderId) : null;

        if ($order && $order->status === 'paid') {
            return $this->resumeShippingAfterPaymentApproval($state, $ctx);
        }

        $pendingMessage = (string) config(
            'sales_flow.payment_validation_pending_message',
            'Tu pago sigue en validación hermosa 💕 En breve te confirmamos.'
        );

        return [
            'text' => $this->business->applyBrandCta($pendingMessage),
            'metadata' => [],
        ];
    }

    /**
     * Tras validación humana (pedido en paid + modo bot): continúa datos de envío.
     *
     * @param  array<string, mixed>  $ctx
     */
    public function resumeBotAfterPaymentValidation(ConversationState $state): ?string
    {
        $ctx = $state->context ?? [];
        if (($ctx['sales_stage'] ?? null) !== 'awaiting_payment_validation') {
            return null;
        }

        $orderId = (int) ($ctx['last_order_id'] ?? 0);
        $order = $orderId > 0 ? Order::find($orderId) : null;
        if (! $order || $order->status !== 'paid') {
            return null;
        }

        $response = $this->resumeShippingAfterPaymentApproval($state, $ctx);

        return $response['text'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function resumeShippingAfterPaymentApproval(ConversationState $state, array $ctx): array
    {
        $ctx['payment_confirmed'] = true;
        $ctx['sales_stage'] = 'awaiting_shipping_data';
        $ctx['shipping_data_step'] = 0;
        $state->context = $ctx;
        $state->save();

        $approvedLead = (string) config(
            'sales_flow.payment_validation_approved_message',
            'Listo hermosa 💕 Tu pago fue validado correctamente ✨'
        );

        return [
            'text' => $this->business->applyBrandCta(
                $approvedLead."\n\n".$this->firstShippingDataPrompt($ctx)
            ),
            'metadata' => [],
        ];
    }

    public function handleShippingDataCollection(ConversationState $state, string $message, ?string $stage): ?array
    {
        if ($stage !== 'awaiting_shipping_data') {
            return null;
        }

        $ctx = $state->context ?? [];
        $method = (string) ($ctx['shipping_method'] ?? 'motorizado');
        $fields = $method === 'shalom'
          ? ['ship_full_name', 'ship_dni', 'ship_phone', 'ship_shalom_branch']
          : ['ship_full_name', 'ship_phone', 'ship_address', 'ship_location'];

        // Pre-rellenar ship_full_name y ship_phone si ya existen en card data
        if (empty($ctx['ship_full_name']) && !empty($ctx['card_full_name'])) {
            $ctx['ship_full_name'] = $ctx['card_full_name'];
        }
        if (empty($ctx['ship_phone']) && !empty($ctx['card_phone'])) {
            $ctx['ship_phone'] = $ctx['card_phone'];
        }

        // Si ya tenemos todos los datos necesarios, saltar directamente a finalizar
        if ($method === 'motorizado' && !empty($ctx['ship_full_name']) && !empty($ctx['ship_phone'])) {
            // Solo necesitamos dirección y ubicación
            $fields = ['ship_address', 'ship_location'];
        } elseif ($method === 'shalom' && !empty($ctx['ship_full_name']) && !empty($ctx['ship_phone'])) {
            // Solo necesitamos DNI y sede
            $fields = ['ship_dni', 'ship_shalom_branch'];
        }

        $step = (int) ($ctx['shipping_data_step'] ?? 0);
        if ($step >= count($fields)) {
            return $this->finalizeOrder($state);
        }

        if (mb_strlen(trim($message)) < 2) {
            return ['text' => $this->business->applyBrandCta('Por favor escríbelo completo hermosa 💕'), 'metadata' => []];
        }

        $ctx[$fields[$step]] = trim($message);
        $step++;
        $ctx['shipping_data_step'] = $step;
        $state->context = $ctx;
        $state->save();

        // Sincronizar datos del cliente después de guardar ship_full_name o ship_phone
        if ($fields[$step - 1] === 'ship_full_name' || $fields[$step - 1] === 'ship_phone') {
            $this->customerSync->syncFromConversationContext($state);
        }

        if ($step >= count($fields)) {
            $ctx['shipping_data_text'] = $this->compileShippingData($ctx, $method);
            $state->context = $ctx;
            $state->save();

            return $this->finalizeOrder($state);
        }

        return ['text' => $this->business->applyBrandCta($this->shippingDataPrompt($method, $step)), 'metadata' => []];
    }

    public function firstShippingDataPrompt(array $ctx): string
    {
        $method = (string) ($ctx['shipping_method'] ?? 'motorizado');

        // Si ya tenemos card_full_name y card_phone, saltar a dirección directamente
        if (!empty($ctx['card_full_name']) && !empty($ctx['card_phone'])) {
            if ($method === 'motorizado') {
                return '¿Tu dirección escrita?';
            }
            // Para Shalom, saltamos nombre y celular, vamos directo a DNI
            return '¿Tu DNI?';
        }

        return $this->shippingDataPrompt($method, 0);
    }

    protected function shippingDataPrompt(string $method, int $step): string
    {
        if ($method === 'shalom') {
            return match ($step) {
                0 => '¿Tu nombre completo?',
                1 => '¿Tu DNI?',
                2 => '¿Tu número de celular?',
                default => '¿La sede exacta de Shalom donde recoges?',
            };
        }

        return match ($step) {
            0 => '¿Tu nombre completo?',
            1 => '¿Tu número de celular?',
            2 => '¿Tu dirección escrita?',
            default => 'Compárteme tu ubicación en tiempo real (o escríbela) 📍',
        };
    }

    protected function compileShippingData(array $ctx, string $method): string
    {
        if ($method === 'shalom') {
            return implode(' | ', array_filter([
                $ctx['ship_full_name'] ?? null,
                'DNI: '.($ctx['ship_dni'] ?? ''),
                'Cel: '.($ctx['ship_phone'] ?? ''),
                'Sede: '.($ctx['ship_shalom_branch'] ?? ''),
            ]));
        }

        return implode(' | ', array_filter([
            $ctx['ship_full_name'] ?? null,
            'Cel: '.($ctx['ship_phone'] ?? ''),
            $ctx['ship_address'] ?? null,
            $ctx['ship_location'] ?? null,
        ]));
    }

    protected function finalizeOrder(ConversationState $state): array
    {
        $ctx = $state->context ?? [];
        $existingOrderId = (int) ($ctx['last_order_id'] ?? 0);
        if ($existingOrderId > 0) {
            return $this->finalizeExistingOrder($state, $existingOrderId, $ctx);
        }

        $productId = (int) ($ctx['current_product_id'] ?? 0);
        $color = (string) ($ctx['current_color'] ?? 'por confirmar');
        
        // Usar talla del contexto si existe, si no, buscar una con stock
        $size = (string) ($ctx['current_size'] ?? null);
        if (!$size || $size === 'M') {
            // Obtener stock por color para encontrar una talla disponible
            $stockCheck = $this->tools->executeCheckStock($state, $productId, $color);
            if (!isset($stockCheck['error']) && isset($stockCheck['stock_by_size'])) {
                $stockBySize = $stockCheck['stock_by_size'];
                // Buscar la primera talla con stock > 0
                foreach ($stockBySize as $sizeKey => $stockQty) {
                    if ($stockQty > 0) {
                        $size = $sizeKey;
                        $ctx['current_size'] = $size;
                        $state->context = $ctx;
                        $state->save();
                        break;
                    }
                }
            }
        }
        
        // Si no hay talla con stock, usar 'M' como fallback
        if (!$size) {
            $size = 'M';
        }

        if ($productId <= 0) {
            return [
                'text' => $this->business->applyBrandCta('Para registrar tu pedido confírmame el vestido exacto 💕'),
                'metadata' => [],
            ];
        }

        $items = [[
            'product_id' => $productId,
            'color' => $color,
            'size' => $size,
            'qty' => 1,
        ]];

        $order = $this->tools->executeCreateOrder(
            $state,
            $items,
            (string) ($ctx['shipping_method'] ?? 'shalom'),
            (string) ($ctx['payment_method'] ?? 'yape'),
            district: $ctx['delivery_district'] ?? $this->extractDistrictFromShippingData($ctx['shipping_data_text'] ?? null),
            address: $ctx['shipping_data_text'] ?? null,
            confirmationBypass: true
        );

        if (! ($order['success'] ?? false)) {
            $this->tools->executeEscalateToHuman($state, 'No se pudo crear pedido automáticamente');
            $settings = BotSetting::first();

            return [
                'text' => $settings?->escalation_message ?: 'Voy a consultar con un asesor especializado y en breve te ayudamos hermosa 💕',
                'metadata' => [],
            ];
        }

        $orderId = (int) ($order['order_id'] ?? 0);
        if ($orderId > 0) {
            $this->syncOrderPaymentFromContext($orderId, $ctx);
        }

        // Si es flujo tarjeta, escalar a humano después de completar el pedido
        $isCardFlow = !empty($ctx['card_flow']);

        $ctx['sales_stage'] = null;
        $ctx['last_order_id'] = $orderId > 0 ? $orderId : null;
        $ctx['card_flow'] = null; // Limpiar flag
        $state->context = $ctx;
        $state->save();

        $hours = config('sales_flow.delivery_hours', 'Entregas L a S (5pm a 9pm).');
        $message = "Perfecto hermosa 💕\nTu pedido quedó registrado correctamente ✨\n\nPedido #{$order['order_id']} | Total S/"
                .number_format((float) ($order['total'] ?? $ctx['order_total'] ?? 0), 0)
                ."\n\n{$hours}";

        if ($isCardFlow) {
            $this->tools->executeEscalateToHuman($state, 'Cliente solicitó link de pago con tarjeta');
            $message .= "\n\nUn asesor te enviará el link de pago en breve 💕";
        }

        return [
            'text' => $this->business->applyBrandCta($message),
            'metadata' => [],
        ];
    }

    /**
     * Crea pedido en pipeline (pendiente) al recibir comprobante — sin marcar pagado.
     *
     * @param  array<string, mixed>  $ctx
     */
    protected function ensurePipelineOrderAwaitingValidation(ConversationState $state, array $ctx): void
    {
        $proofUrl = $ctx['payment_proof_url'] ?? null;
        $existingId = (int) ($ctx['last_order_id'] ?? 0);

        if ($existingId > 0) {
            $updates = ['status' => 'pending'];
            if (! empty($proofUrl)) {
                $updates['payment_proof_url'] = (string) $proofUrl;
            }
            Order::where('id', $existingId)->update($updates);

            return;
        }

        $productId = (int) ($ctx['current_product_id'] ?? 0);
        if ($productId <= 0) {
            return;
        }

        $items = [[
            'product_id' => $productId,
            'color' => (string) ($ctx['current_color'] ?? 'por confirmar'),
            'size' => (string) ($ctx['current_size'] ?? 'M'),
            'qty' => 1,
        ]];

        $order = $this->tools->executeCreateOrder(
            $state,
            $items,
            (string) ($ctx['shipping_method'] ?? 'shalom'),
            (string) ($ctx['payment_method'] ?? 'yape'),
            district: $ctx['delivery_district'] ?? null,
            address: null,
            confirmationBypass: true
        );

        if (! ($order['success'] ?? false)) {
            return;
        }

        $orderId = (int) ($order['order_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        if (! empty($proofUrl)) {
            Order::where('id', $orderId)->update([
                'payment_proof_url' => (string) $proofUrl,
                'status' => 'pending',
            ]);
        }

        $ctx['last_order_id'] = $orderId;
        $state->context = $ctx;
        $state->save();
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function finalizeExistingOrder(ConversationState $state, int $orderId, array $ctx): array
    {
        $order = Order::find($orderId);
        if (! $order) {
            unset($ctx['last_order_id']);
            $state->context = $ctx;
            $state->save();

            return $this->finalizeOrder($state);
        }

        $method = (string) ($ctx['shipping_method'] ?? 'shalom');
        $shippingText = $ctx['shipping_data_text'] ?? $this->compileShippingData($ctx, $method);

        $order->update([
            'district' => $ctx['delivery_district'] ?? $this->extractDistrictFromShippingData($shippingText),
            'full_address' => $shippingText,
            'shipping_method' => $method,
        ]);

        $ctx['sales_stage'] = null;
        $state->context = $ctx;
        $state->save();

        $hours = config('sales_flow.delivery_hours', 'Entregas L a S (5pm a 9pm).');

        return [
            'text' => $this->business->applyBrandCta(
                "Perfecto hermosa 💕\nTu pedido quedó registrado correctamente ✨\n\nPedido #{$orderId} | Total S/"
                .number_format((float) $order->amount_total, 0)
                ."\n\n{$hours}"
            ),
            'metadata' => [],
        ];
    }

    /**
     * Si el cliente ya confirmó pago (comprobante Yape), marca el pedido como pagado en el pipeline.
     *
     * @param  array<string, mixed>  $ctx
     */
    protected function syncOrderPaymentFromContext(int $orderId, array $ctx): void
    {
        if (empty($ctx['payment_confirmed'])) {
            return;
        }

        $updates = [
            'status' => 'paid',
            'paid_at' => now(),
        ];

        if (! empty($ctx['payment_proof_url'])) {
            $updates['payment_proof_url'] = (string) $ctx['payment_proof_url'];
        }

        Order::where('id', $orderId)->update($updates);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{product: float, delivery: float, total: float}
     */
    public function calculateTotals(array $ctx): array
    {
        $productId = (int) ($ctx['current_product_id'] ?? 0);
        $productPrice = 0.0;
        if ($productId > 0) {
            $product = Product::find($productId);
            if ($product) {
                $validation = PriceValidatorService::validateProductPrice($product);
                $productPrice = (float) ($validation['final_price'] ?? 0);
            }
        }

        $delivery = (float) ($ctx['delivery_cost'] ?? 0);

        return [
            'product' => $productPrice,
            'delivery' => $delivery,
            'total' => $productPrice + $delivery,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array{product: float, delivery: float, total: float}  $totals
     */
    protected function buildOrderSummaryText(array $ctx, array $totals): string
    {
        $name = (string) ($ctx['current_product_name'] ?? 'Tu pedido');
        $color = (string) ($ctx['current_color'] ?? '');
        $size = (string) ($ctx['current_size'] ?? '');
        $line = trim("{$name}".($color ? " {$color}" : '').($size ? " T{$size}" : ''));

        $deliveryLabel = (string) ($ctx['shipping_method'] ?? '') === 'shalom'
          ? 'Shalom'
          : ((string) ($ctx['delivery_district'] ?? 'Motorizado'));

        return "🛍️ RESUMEN DE PEDIDO\n\n"
          ."✨ {$line}\n"
          .'💰 Producto: S/'.number_format($totals['product'], 0)."\n"
          ."🛵 Delivery ({$deliveryLabel}): S/".number_format($totals['delivery'], 0)."\n\n"
          .'💵 TOTAL: S/'.number_format($totals['total'], 0);
    }

    public function resolveDeliveryZone(string $input): ?DeliveryZone
    {
        $needle = mb_strtolower(trim($input));
        if ($needle === '') {
            return null;
        }

        $aliases = config('sales_flow.district_aliases', []);
        if (isset($aliases[$needle])) {
            $needle = mb_strtolower($aliases[$needle]);
        }

        $zones = DeliveryZone::query()->orderBy('district')->get();
        foreach ($zones as $zone) {
            $district = mb_strtolower($zone->district);
            if ($district === $needle || str_contains($district, $needle) || str_contains($needle, $district)) {
                return $zone;
            }
        }

        return null;
    }

    protected function extractDistrictFromShippingData(?string $text): ?string
    {
        if (! $text) {
            return null;
        }
        $zone = $this->resolveDeliveryZone($text);

        return $zone?->district;
    }

    public function isPositivePublic(string $msg): bool
    {
        return $this->isPositive(mb_strtolower(trim($msg)));
    }

    public function isNegativePublic(string $msg): bool
    {
        return $this->isNegative(mb_strtolower(trim($msg)));
    }

    protected function isPositive(string $msg): bool
    {
        return (bool) preg_match('/\b(si|sí|confirmo|quiero|dale|ok|listo|procede|proceder|confirmar pedido|continuar|me lo llevo|separar|separalo|s[eé]paralo)\b/u', $msg);
    }

    protected function isNegative(string $msg): bool
    {
        return (bool) preg_match('/\b(no quiero|no gracias|solo viendo|solo mirando)\b/u', $msg);
    }
}
