<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeterministicBotService
{
    public function __construct(
        protected ToolExecutorService $tools,
        protected IntentTranslatorService $translator,
        protected LlmService $llmService,
        protected BotMetricsService $metrics,
        protected BusinessConfigService $business,
        protected SalesFlowService $salesFlow,
        protected CatalogImageMatcherService $imageMatcher,
        protected ProductPresentationService $presentation,
        protected CategoryBrowseService $categoryBrowse
    ) {}

    /**
     * Procesa el mensaje en modo determinístico y devuelve texto + metadata opcional.
     */
    public function process(string $phoneNumber, string $message, ?string $imageUrl = null, ?array $metadata = null): array
    {
        $settings = $this->business->bot();
        if (! $settings?->auto_reply_enabled) {
            $this->metrics->incrementRoute('disabled');

            return ['text' => '', 'metadata' => []];
        }

        $customer = Customer::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['first_seen_at' => now(), 'last_seen_at' => now(), 'segment' => 'lead']
        );
        $customer->update(['last_seen_at' => now()]);

        $state = ConversationState::firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'customer_id' => $customer->id,
                'current_state' => 'greeting',
                'context' => [],
                'last_activity_at' => now(),
            ]
        );

        if (empty($state->customer_id)) {
            $state->update(['customer_id' => $customer->id]);
        }

        if ($state->requires_human) {
            $this->metrics->incrementRoute('human_mode_skip');

            return ['text' => '', 'metadata' => []];
        }

        $state->update(['last_activity_at' => now()]);

        try {
            [$imageUrl, $hasInboundImage] = $this->resolveInboundImage($imageUrl, $metadata);
            $normalized = $this->translator->translate($message, $metadata);

            if ($hasInboundImage && $this->shouldTreatAsPaymentProof($state)) {
                $stageResponse = $this->salesFlow->handleStage($state, 'comprobante de pago enviado', true, $imageUrl);
                if ($stageResponse !== null) {
                    $this->metrics->incrementIntent('payment_proof_image');
                    $this->metrics->resetFailureStreak($phoneNumber);

                    return $stageResponse;
                }
            }

            $colorResponse = $this->presentation->handleColorSelection($state, $normalized);
            if ($colorResponse !== null) {
                $this->metrics->incrementIntent('color_selection');
                $this->metrics->resetFailureStreak($phoneNumber);

                return $colorResponse;
            }

            $sizeResponse = $this->presentation->handleSizeSelection($state, $normalized);
            if ($sizeResponse !== null) {
                $this->metrics->incrementIntent('size_selection');
                $this->metrics->resetFailureStreak($phoneNumber);

                return $sizeResponse;
            }

            $hasImage = $hasInboundImage;
            $stageResponse = $this->salesFlow->handleStage($state, $normalized, $hasImage, $imageUrl);
            if ($stageResponse !== null) {
                $this->metrics->incrementIntent('stage_flow');
                $this->metrics->resetFailureStreak($phoneNumber);

                return $stageResponse;
            }

            $styleResponse = $this->categoryBrowse->handleStyleSelection($state, $normalized);
            if ($styleResponse !== null) {
                $this->metrics->incrementIntent('style_selection');
                $this->metrics->resetFailureStreak($phoneNumber);

                return $styleResponse;
            }

            if (preg_match('/^pick_product_(\d+)$/i', $normalized, $m)) {
                $this->metrics->incrementIntent('pick_product');

                return $this->presentation->presentProductPick($state, (int) $m[1]);
            }

            if (preg_match('/^pick_category_(\d+)$/i', $normalized, $m)) {
                $this->metrics->incrementIntent('pick_category');

                return $this->categoryBrowse->presentCategoryProducts($state, (int) $m[1]);
            }

            if ($normalized === 'show_all_products') {
                $this->metrics->incrementIntent('show_all_products');

                return $this->categoryBrowse->presentProductCatalog($state);
            }

            $productPick = $this->categoryBrowse->handleProductSelection($state, $normalized);
            if ($productPick !== null) {
                $this->metrics->incrementIntent('product_selection');

                return $productPick;
            }

            $categoryPick = $this->categoryBrowse->handleCategorySelection($state, $normalized);
            if ($categoryPick !== null) {
                $this->metrics->incrementIntent('category_selection');

                return $categoryPick;
            }

            $intent = $this->detectIntent($normalized, $imageUrl, $hasInboundImage);
            $this->metrics->incrementIntent($intent);

            $response = match ($intent) {
                'menu', 'browse' => $this->categoryBrowse->presentCategorySelection($state),
                'category_filter' => $this->categoryBrowse->presentCategorySelection($state),
                'human', 'complaint' => $this->escalateToHuman($state, $intent === 'complaint' ? 'Reclamo del cliente' : 'Cliente solicitó asesor humano'),
                'delivery' => $this->handleDelivery($state, $normalized),
                'payment' => $this->handlePaymentIntent($state),
                'stock' => $this->handleStock($state, $normalized),
                'price' => $this->handlePriceQuery($state, $normalized),
                'order_status' => $this->handleOrderStatus($state, $normalized),
                'catalog_gate', 'catalog' => $this->handleCatalog($state, $normalized),
                'live_image' => $this->handleLiveImage($state, $imageUrl, $normalized),
                'buy_now' => $this->handleBuyNow($state),
                default => $this->handleFallback($state, $normalized, $imageUrl),
            };

            $this->metrics->resetFailureStreak($phoneNumber);

            return $response;
        } catch (\Throwable $e) {
            $this->metrics->incrementError('process_exception');
            $streak = $this->metrics->registerFailureStreak($phoneNumber);

            $context = $state->context ?? [];
            if ($streak >= 3) {
                // Circuit breaker: forzar modo estricto temporalmente (sin fallback LLM)
                $context['strict_mode_until'] = now()->addMinutes(30)->toDateTimeString();
                $state->context = $context;
                $state->save();
                $this->metrics->incrementError('circuit_breaker_activated');
            }

            Log::error('DeterministicBotService: process exception', [
                'phone' => $phoneNumber,
                'streak' => $streak,
                'error' => $e->getMessage(),
            ]);

            $this->tools->executeEscalateToHuman($state, 'Error en procesamiento IA/determinístico');
            $settings = $this->business->bot();

            return [
                'text' => $settings?->escalation_message ?: 'Voy a realizar la consulta a un agente especializado y en breve le brindamos una respuesta.',
                'metadata' => [],
            ];
        }
    }

    protected function detectIntent(string $message, ?string $imageUrl = null, bool $hasInboundImage = false): string
    {
        if ($imageUrl || $hasInboundImage) {
            return 'live_image';
        }

        $msg = mb_strtolower(trim($message));
        if ($msg === '' || in_array($msg, ['hola', 'holi', 'inicio', 'menu', 'menú', 'info', 'informacion', 'información'], true)) {
            return 'menu';
        }

        if (preg_match('/\b(qu[eé]\s+(productos?|vendes|tienen|hay)|qu[eé]\s+tienes|que\s+vendes|muestrame|muéstrame|ver\s+(catalogo|catálogo|vestidos?|opciones|modelos)|tienen\s+vestidos?|opciones\s+reales|para\s+ver\s+si|curioseando)\b/u', $msg)) {
            return 'browse';
        }

        if (preg_match('/\b(bro|hermana|amiga)\b/u', $msg) && preg_match('/\b(que|qué|producto|venden|tienen)\b/u', $msg)) {
            return 'browse';
        }

        if (preg_match('/\b(asesor|humano|persona real|hablar con)\b/u', $msg)) {
            return 'human';
        }

        if (preg_match('/\b(reclamo|queja|devoluci[oó]n|problema con)\b/u', $msg)) {
            return 'complaint';
        }

        if (preg_match('/\b(envio|envío|delivery|distrito|shalom|motorizado)\b/u', $msg)) {
            return 'delivery';
        }

        if (preg_match('/\b(yape|pago|pag[oó]|tarjeta|comprobante|captura)\b/u', $msg)) {
            return 'payment';
        }

        if (preg_match('/\b(precio|cu[aá]nto cuesta|cuanto sale|costo)\b/u', $msg)) {
            return 'price';
        }

        if (preg_match('/\b(stock|talla|disponible|disponibilidad)\b/u', $msg)) {
            return 'stock';
        }

        if (preg_match('/\b(colores?|color)\b/u', $msg)) {
            return 'stock';
        }

        if (preg_match('/\b(pedido|orden|order|estado)\b/u', $msg)) {
            return 'order_status';
        }

        if (preg_match('/\b(categor[ií]as?|filtro|filtrar)\b/u', $msg)) {
            return 'category_filter';
        }

        if (preg_match('/\b(catalogo|catálogo)\b/u', $msg)) {
            return 'browse';
        }

        if (preg_match('/\b(vestido|vestidos|precio|busco|quiero ver|modelo)\b/u', $msg)) {
            return 'catalog';
        }

        if (preg_match('/\b(quiero|comprar|separar|llevame|llévame|de frente)\b/u', $msg)) {
            return 'buy_now';
        }

        return 'fallback';
    }

    protected function handleCatalog(ConversationState $state, string $message): array
    {
        $this->metrics->incrementRoute('catalog');

        $category = $this->categoryBrowse->findCategoryByName($message);
        if ($category) {
            return $this->categoryBrowse->presentCategoryProducts($state, $category->id);
        }

        $result = $this->tools->executeGetProducts($state, $message, null, false);
        $count = (int) ($result['count'] ?? 0);
        if ($count === 0) {
            return [
                'text' => $this->business->applyBrandCta(
                    "No encontré \"{$message}\" en catálogo 😊\nMira los vestidos disponibles o escribe *categorías* para filtrar."
                ),
                'metadata' => [],
            ];
        }

        if ($count === 1) {
            return $this->presentation->presentProductPick($state, (int) $result['products'][0]['id']);
        }

        return $this->categoryBrowse->presentationFromProductSearch($state, $result);
    }

    protected function handleStock(ConversationState $state, string $message): array
    {
        $this->metrics->incrementRoute('stock');
        $ctx = $state->context ?? [];
        $productId = (int) ($ctx['current_product_id'] ?? 0);
        $color = (string) ($ctx['current_color'] ?? '');

        if (preg_match('/id\s*(\d+)/i', $message, $m)) {
            $productId = (int) $m[1];
        }
        if (preg_match('/color\s+([a-záéíóúñ]+)/iu', $message, $m)) {
            $color = $m[1];
        }

        if ($productId <= 0 || $color === '') {
            return ['text' => $this->business->applyBrandCta('Para validar stock indícame vestido y color (ej: stock vestido 12 color rojo).'), 'metadata' => []];
        }

        $stock = $this->tools->executeCheckStock($state, $productId, $color);
        if (! empty($stock['error'])) {
            return ['text' => $this->business->applyBrandCta((string) $stock['error']), 'metadata' => []];
        }

        $sizes = [];
        foreach (($stock['stock_by_size'] ?? []) as $size => $qty) {
            if ((int) $qty > 0) {
                $sizes[] = $size.':'.$qty;
            }
        }
        $sizeText = empty($sizes) ? 'Sin stock en tallas.' : implode(', ', $sizes);

        return ['text' => $this->business->applyBrandCta("Stock {$stock['product']} ({$stock['color']}): {$sizeText}"), 'metadata' => []];
    }

    protected function handleDelivery(ConversationState $state, string $message): array
    {
        $this->metrics->incrementRoute('delivery');
        $zone = $this->salesFlow->resolveDeliveryZone($message);
        if (! $zone) {
            $lima = config('sales_flow.shalom_lima_cost', 10);
            $prov = config('sales_flow.shalom_provincia_cost', 12);

            return [
                'text' => $this->business->applyBrandCta(
                    "Dime tu distrito para cotizar motorizado 💕\nShalom referencial: Lima S/{$lima} | Provincia S/{$prov}"
                ),
                'metadata' => [],
            ];
        }

        return [
            'text' => $this->business->applyBrandCta(
                "Envío a {$zone->district}:\n🛵 Motorizado S/".number_format((float) $zone->cost_motorizado, 0)
                ."\n📦 Shalom S/".number_format((float) ($zone->cost_shalom ?? config('sales_flow.shalom_lima_cost', 10)), 0)
            ),
            'metadata' => [],
        ];
    }

    protected function handlePaymentIntent(ConversationState $state): array
    {
        $this->metrics->incrementRoute('payment');
        $stage = $state->context['sales_stage'] ?? null;
        if ($stage === 'awaiting_payment_proof') {
            return ['text' => $this->business->applyBrandCta($this->business->yapePaymentMessage()), 'metadata' => []];
        }
        if (in_array($stage, ['awaiting_order_summary', 'awaiting_payment_method'], true)) {
            return $this->salesFlow->handleStage($state, 'pagar por yape', false)
                ?? ['text' => $this->business->applyBrandCta($this->business->yapePaymentMessage()), 'metadata' => []];
        }

        return ['text' => $this->business->applyBrandCta('Primero elige tu vestido y te guío paso a paso hasta el pago 💕'), 'metadata' => []];
    }

    protected function handlePriceQuery(ConversationState $state, string $message): array
    {
        $this->metrics->incrementRoute('price');
        $productId = (int) ($state->context['current_product_id'] ?? 0);
        if ($productId > 0) {
            return $this->presentation->presentProductPick($state, $productId);
        }

        return $this->handleCatalog($state, $message);
    }

    protected function handleOrderStatus(ConversationState $state, string $message): array
    {
        $this->metrics->incrementRoute('order_status');
        $orderId = null;
        if (preg_match('/\b(\d{1,8})\b/', $message, $m)) {
            $orderId = (int) $m[1];
        }
        $status = $this->tools->executeGetOrderStatus($state, $orderId);
        if (! ($status['found'] ?? false)) {
            return ['text' => $this->business->applyBrandCta('No encuentro pedidos registrados para este chat.'), 'metadata' => []];
        }

        $o = $status['order'];

        return ['text' => $this->business->applyBrandCta("Pedido #{$o['id']} está {$o['status']} | Total S/".number_format((float) $o['amount_total'], 2)), 'metadata' => []];
    }

    protected function handleLiveImage(ConversationState $state, ?string $imageUrl = null, ?string $userText = null): array
    {
        $this->metrics->incrementRoute('live_image');

        if ($imageUrl) {
            $matched = $this->imageMatcher->matchFromImage($state, $imageUrl, $userText);
            if ($matched['matched']) {
                return ['text' => $matched['text'], 'metadata' => $matched['metadata']];
            }
        }

        return [
            'text' => $this->business->applyBrandCta(
                "Recibí tu foto 📸\nNo encontré un modelo igual en catálogo. Escríbeme el nombre o el color que buscas (ej: borgoña) 💕"
            ),
            'metadata' => [],
        ];
    }

    protected function handleBuyNow(ConversationState $state): array
    {
        $this->metrics->incrementRoute('buy_now');
        $productId = (int) ($state->context['current_product_id'] ?? 0);
        if ($productId > 0) {
            $ctx = $state->context ?? [];
            $ctx['sales_stage'] = 'awaiting_order_confirmation';
            $state->context = $ctx;
            $state->save();

            return $this->salesFlow->handleStage($state, 'sí confirmo mi pedido', false)
                ?? ['text' => $this->business->applyBrandCta($this->business->orderConfirmationPrompt()), 'metadata' => []];
        }

        return $this->categoryBrowse->presentCategorySelection($state);
    }

    protected function handleFallback(ConversationState $state, string $message, ?string $imageUrl): array
    {
        $this->metrics->incrementRoute('fallback');
        // Circuit breaker por conversación: modo estricto temporal.
        $strictUntil = $state->context['strict_mode_until'] ?? null;
        $strictActive = $strictUntil ? now()->lt(Carbon::parse($strictUntil)) : false;

        // LLM solo para redacción en casos ambiguos si está habilitado y no hay breaker activo.
        if (! config('bot.enable_llm_fallback', false) || $strictActive) {
            if ($strictActive) {
                $this->metrics->incrementError('strict_mode_active');
            }
            if ($imageUrl) {
                return $this->handleLiveImage($state, $imageUrl, $message);
            }

            $stage = $state->context['sales_stage'] ?? null;

            $productPick = $this->categoryBrowse->handleProductSelection($state, $message);
            if ($productPick !== null) {
                return $productPick;
            }

            if ($stage === 'awaiting_product_selection') {
                $categoryId = (int) ($state->context['current_category_id'] ?? 0);
                if ($categoryId > 0) {
                    return $this->categoryBrowse->presentCategoryProducts($state, $categoryId);
                }

                return $this->categoryBrowse->presentProductCatalog($state);
            }

            if (in_array($stage, [
                'awaiting_category_filter',
                'awaiting_category_selection',
                'awaiting_style_selection',
                'awaiting_color_selection',
                'awaiting_size_selection',
            ], true)) {
                return ['text' => $this->business->applyBrandCta('Elige una opción de la lista o escríbeme el número 💕'), 'metadata' => []];
            }

            return $this->categoryBrowse->presentCategorySelection($state);
        }

        $context = [
            'state' => $state,
            'user_message' => $message,
            'conversation_context' => $state->context ?? [],
            'customer_context' => [],
            'has_called_tools_this_turn' => false,
        ];
        try {
            $text = $this->llmService->callGroq($context, $imageUrl, [], 0, 'none');
            $text = ResponseSanitizer::sanitize($text);
            if ($text === '') {
                $text = '';
            }
        } catch (\Throwable $e) {
            $this->metrics->incrementError('llm_fallback_exception');
            Log::warning('DeterministicBotService: LLM fallback exception', ['error' => $e->getMessage()]);
            $text = '';
        }

        if ($text === '') {
            return $this->categoryBrowse->presentCategorySelection($state);
        }

        return ['text' => $text, 'metadata' => []];
    }

    protected function escalateToHuman(ConversationState $state, string $reason): array
    {
        $this->metrics->incrementRoute('escalate_human');
        $this->tools->executeEscalateToHuman($state, $reason);
        $settings = $this->business->bot();

        return ['text' => $settings?->escalation_message ?: 'Te derivamos con un asesor humano.', 'metadata' => []];
    }

    protected function extractDistrict(string $message): ?string
    {
        $msg = trim($message);
        $parts = preg_split('/\s+/', $msg);
        if (count($parts) === 0) {
            return null;
        }
        // Heurística simple: última palabra con letras.
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $token = trim($parts[$i], " \t\n\r\0\x0B,.;:!?");
            if (preg_match('/^[a-záéíóúñ]{3,}$/iu', $token)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{0: ?string, 1: bool}
     */
    protected function resolveInboundImage(?string $imageUrl, ?array $metadata): array
    {
        if (($imageUrl === null || $imageUrl === '') && is_array($metadata)) {
            $imageUrl = $metadata['image_url'] ?? null;
        }

        $hasInboundImage = ($imageUrl !== null && $imageUrl !== '')
            || (is_array($metadata) && (
                ($metadata['type'] ?? null) === 'image'
                || ($metadata['whatsapp_message_type'] ?? null) === 'image'
            ));

        return [$imageUrl, $hasInboundImage];
    }

    protected function shouldTreatAsPaymentProof(ConversationState $state): bool
    {
        $ctx = $state->context ?? [];
        $stage = $ctx['sales_stage'] ?? null;

        if ($stage === 'awaiting_payment_proof') {
            return true;
        }

        if ($stage === 'awaiting_payment_validation') {
            return false;
        }

        // Recuperación: eligió Yape pero la etapa se perdió o llegó imagen sin URL
        if (($ctx['payment_method'] ?? '') === 'yape' && empty($ctx['payment_confirmed'])) {
            return in_array($stage, [
                'awaiting_payment_proof',
                'awaiting_payment_method',
                'awaiting_order_summary',
                null,
            ], true);
        }

        return false;
    }
}
