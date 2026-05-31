<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ConversationState;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\AgentHandoff;
use App\Models\Customer;
use App\Models\Message;
use App\Services\HandoffContextService;
use Illuminate\Support\Facades\Log;

class ToolExecutorService
{
    public function __construct(
        protected ProductMediaService $media
    ) {
    }

    /**
     * Busca vestidos en el catálogo utilizando tokenización y filtros de color.
     * Devuelve una respuesta de visualización muy compacta.
     */
    public function executeGetProducts(
        ConversationState $state,
        string $query,
        ?string $color = null,
        bool $allowGlobalFallback = true
    ): array {
        Log::info('ToolExecutorService: executeGetProducts called', ['query' => $query, 'color' => $color]);

        $cleanQuery = trim($query);
        $category = $this->findCategoryByName($cleanQuery);

        $searchQuery = Product::with(['category', 'variants', 'images'])->whereHas('variants');

        if ($category) {
            $searchQuery->where('category_id', $category->id);
            $products = $searchQuery->orderByDesc('updated_at')->limit(8)->get();
        } else {
            $stopWords = ['de', 'con', 'para', 'una', 'las', 'los', 'que', 'uno', 'por', 'del', 'el', 'la', 'un', 'y', 'en', 'a', 'o', 'vestido', 'vestidos', 'modelo', 'modelos', 'categoria', 'categoría', 'categorias', 'categorías', 'ver', 'todo', 'filtro', 'filtrar'];

            $words = array_filter(explode(' ', $cleanQuery), function ($word) use ($stopWords) {
                $lw = mb_strtolower(trim($word));
                return mb_strlen($lw) > 1 && !in_array($lw, $stopWords);
            });

            if (empty($words) && $cleanQuery !== '') {
                $words = [$cleanQuery];
            }

            if (!empty($words)) {
                $searchQuery->where(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $q->orWhere('name', 'like', "%{$word}%")
                            ->orWhere('description', 'like', "%{$word}%")
                            ->orWhereJsonContains('tags_ia', $word)
                            ->orWhereHas('variants', function ($vQ) use ($word) {
                                $vQ->where('color', 'like', "%{$word}%");
                            })
                            ->orWhereHas('category', function ($cQ) use ($word) {
                                $cQ->where('name', 'like', "%{$word}%");
                            });
                    }
                });
            }

            $products = $searchQuery->orderByDesc('updated_at')->limit(8)->get();
        }

        if ($products->isEmpty() && $allowGlobalFallback) {
            $products = Product::with(['category', 'variants', 'images'])
                ->whereHas('variants')
                ->whereIn('id', function ($q) {
                    $q->select('product_id')
                        ->from('order_items')
                        ->groupBy('product_id')
                        ->orderByRaw('COUNT(*) DESC')
                        ->limit(5);
                })
                ->limit(3)
                ->get();
        }

        if ($products->isEmpty() && $allowGlobalFallback) {
            $products = Product::with(['category', 'variants', 'images'])
                ->whereHas('variants')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
        }

        $result = [];
        foreach ($products as $product) {
            $priceValidation = PriceValidatorService::validateProductPrice($product);
            if (!$priceValidation['valid']) {
                continue;
            }

            $colors = [];
            $hasStock = false;
            $firstImageUrl = null;

            foreach ($product->variants as $variant) {
                if ($color && mb_strtolower($variant->color) !== mb_strtolower($color)) {
                    continue;
                }
                
                $stock = $variant->sizes_stock ?? [];
                $totalStock = is_array($stock) ? array_sum($stock) : 0;
                if ($totalStock > 0) {
                    $hasStock = true;
                }

                $colors[] = $variant->color;
                $variantUrl = $this->media->resolvePublicUrl($variant);
                if (!$firstImageUrl && $variantUrl) {
                    $firstImageUrl = $variantUrl;
                }
            }

            if (!$firstImageUrl && $product->images->isNotEmpty()) {
                $firstImageUrl = $product->images->first()->image_url;
            }

            $result[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $priceValidation['base_price'],
                'discount' => $priceValidation['discount'],
                'final_price' => $priceValidation['final_price'],
                'has_stock' => $hasStock,
                'colors' => array_unique($colors),
                'image_url' => $firstImageUrl,
            ];
        }

        // Guardar la lista de productos mostrados en el contexto conversacional del estado
        if (count($result) > 0) {
            $context = $state->context;
            $context['current_product_id'] = $result[0]['id'];
            $context['current_product_name'] = $result[0]['name'];
            if ($color) {
                $context['current_color'] = $color;
            }

            $context['last_shown_products'] = array_map(function ($p) {
                return [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'final_price' => $p['final_price'],
                    'has_stock' => $p['has_stock'],
                ];
            }, $result);

            $state->context = $context;
            $state->save();
        }

        return [
            'count' => count($result),
            'products' => $result,
        ];
    }

    /**
     * Verifica la disponibilidad de stock por color y talla para un ID de producto.
     * Actualiza el carrito mental.
     */
    public function executeCheckStock(ConversationState $state, int $productId, string $color): array
    {
        Log::info('ToolExecutorService: executeCheckStock called', ['product_id' => $productId, 'color' => $color]);

        $product = Product::find($productId);
        if (!$product) {
            return ['error' => 'Producto no encontrado'];
        }

        // Memorizar en el carrito mental
        $context = $state->context;
        $context['current_product_id'] = $productId;
        $context['current_product_name'] = $product->name;
        $context['current_color'] = $color;
        $state->context = $context;
        $state->save();

        $normalizedColor = mb_strtolower(trim($color));

        $variant = ProductVariant::where('product_id', $productId)
            ->whereRaw('LOWER(TRIM(color)) = ?', [$normalizedColor])
            ->first();

        if (! $variant) {
            $variant = ProductVariant::where('product_id', $productId)
                ->whereRaw('LOWER(TRIM(color)) LIKE ?', ["%{$normalizedColor}%"])
                ->first();
        }

        if (! $variant) {
            return [
                'product' => $product->name,
                'color' => $color,
                'available' => false,
                'error' => "El color '{$color}' no está registrado para este vestido.",
            ];
        }

        $context['current_color'] = $variant->color;
        $state->context = $context;
        $state->save();

        return [
            'product' => $product->name,
            'color' => $variant->color,
            'available' => true,
            'stock_by_size' => \App\Support\SizeStockNormalizer::normalize($variant->sizes_stock ?? []),
        ];
    }

    /**
     * Obtiene los costos de envío para un distrito de Lima.
     */
    public function executeGetDeliveryCost(string $district): array
    {
        Log::info('ToolExecutorService: executeGetDeliveryCost called', ['district' => $district]);

        $zone = DeliveryZone::where('district', 'like', "%{$district}%")->first();

        if (!$zone) {
            return [
                'district' => $district,
                'found' => false,
                'shalom_cost' => 10.00,
                'motorizado_cost' => 'Variable según distrito. Solicitar dirección para cotizar.',
                'eta_hours' => 24,
            ];
        }

        return [
            'district' => $zone->district,
            'found' => true,
            'shalom_cost' => $zone->cost_shalom ?? 10.00,
            'motorizado_cost' => $zone->cost_motorizado ?? 15.00,
            'eta_hours' => $zone->eta_hours ?? 24,
        ];
    }

    /**
     * Envía la imagen de un vestido por WhatsApp guardándola como pendiente en el contexto.
     */
    public function executeSendProductImage(ConversationState $state, int $productId, ?string $color = null, ?string $caption = null): array
    {
        Log::info('ToolExecutorService: executeSendProductImage', ['product_id' => $productId, 'color' => $color]);

        $resolved = $this->resolveProductImage($productId, $color);
        if (! ($resolved['success'] ?? false)) {
            return $resolved;
        }

        $context = $state->context;
        $context['pending_image_url'] = $resolved['image_url'];
        $context['pending_image_caption'] = $resolved['caption'];
        $context['current_product_id'] = $productId;
        $context['current_product_name'] = $resolved['product_name'];
        if ($color) {
            $context['current_color'] = $resolved['color'] ?? $color;
        }
        $state->context = $context;
        $state->save();

        return [
            'success' => true,
            'image_url' => $resolved['image_url'],
            'caption' => $resolved['caption'],
            'message' => "La imagen de {$resolved['product_name']} ha sido encolada para ser enviada adjunta al mensaje final de texto.",
        ];
    }

    /**
     * Encola foto de producto para envío antes del texto (varias opciones en live).
     */
    public function enqueueProductImage(ConversationState $state, int $productId, ?string $color = null, ?string $caption = null): array
    {
        $resolved = $this->resolveProductImage($productId, $color);
        if (! ($resolved['success'] ?? false)) {
            Log::info('ToolExecutorService: enqueueProductImage skipped (no image)', [
                'product_id' => $productId,
                'error' => $resolved['error'] ?? null,
            ]);

            return $resolved;
        }

        $context = $state->context ?? [];
        $queue = $context['pending_image_queue'] ?? [];
        $queue[] = [
            'url' => $resolved['image_url'],
            'caption' => $resolved['caption'],
        ];
        $context['pending_image_queue'] = $queue;
        $state->context = $context;
        $state->save();

        return [
            'success' => true,
            'image_url' => $resolved['image_url'],
            'caption' => $resolved['caption'],
        ];
    }

    /**
     * @return array{success: bool, image_url?: string, caption?: string, product_name?: string, color?: string, error?: string}
     */
    protected function resolveProductImage(int $productId, ?string $color = null, ?string $caption = null): array
    {
        $product = Product::with(['variants', 'images'])->find($productId);
        if (! $product) {
            return ['success' => false, 'error' => 'Producto no encontrado'];
        }

        $imageUrl = null;
        $variant = null;

        if ($color) {
            $normalizedColor = mb_strtolower(trim($color));

            $variant = ProductVariant::where('product_id', $productId)
                ->whereRaw('LOWER(color) = ?', [$normalizedColor])
                ->first();

            if (! $variant) {
                $variant = ProductVariant::where('product_id', $productId)
                    ->whereRaw('LOWER(color) LIKE ?', ["%{$normalizedColor}%"])
                    ->first();
            }

            if ($variant) {
                $imageUrl = $this->media->resolvePublicUrl($variant);

                if (! $imageUrl) {
                    return [
                        'success' => false,
                        'error' => "El color '{$variant->color}' no tiene foto cargada.",
                        'color' => $variant->color,
                    ];
                }
            }
        }

        if (! $imageUrl && (! $color || ! $variant)) {
            foreach ($product->variants as $v) {
                $imageUrl = $this->media->resolvePublicUrl($v);
                if ($imageUrl) {
                    $variant = $v;
                    break;
                }
            }
        }

        if (! $imageUrl && (! $color || ! $variant) && $product->images->isNotEmpty()) {
            $imageUrl = $product->images->first()->image_url;
        }

        if (! $imageUrl) {
            return [
                'success' => false,
                'error' => 'No se encontró una imagen cargada para este vestido.',
            ];
        }

        $resolvedCaption = $caption
            ?? ($color
                ? 'Color '.($variant?->color ?? $color).' 📸'
                : "✨ {$product->name} 📸");

        return [
            'success' => true,
            'image_url' => $imageUrl,
            'caption' => $resolvedCaption,
            'product_name' => $product->name,
            'color' => $variant?->color,
        ];
    }

    /**
     * Crea un pedido pendiente en base de datos tras verificar la confirmación explícita del cliente.
     */
    public function executeCreateOrder(
        ConversationState $state,
        array $items,
        string $shippingMethod,
        string $paymentMethod,
        ?string $district = null,
        ?string $address = null,
        bool $confirmationBypass = false
    ): array {
        Log::info('ToolExecutorService: executeCreateOrder', [
            'phone' => $state->phone_number,
            'items' => $items,
            'shipping' => $shippingMethod,
            'payment' => $paymentMethod
        ]);

        $context = $state->context ?? [];
        $lastUserMessage = '';
        $lastIncoming = Message::where('phone_number', $state->phone_number)
            ->where('direction', 'incoming')
            ->orderByDesc('id')
            ->first();
        if ($lastIncoming) {
            $lastUserMessage = mb_strtolower((string) $lastIncoming->content);
        }

        $hasConfirmation = $confirmationBypass || !empty($context['order_confirmed']);
        if (!$hasConfirmation) {
            $confirmationKeywords = ['sí', 'si', 'confirmo', 'confirmar', 'lo quiero', 'lo tomo', 'comprar', 'ok', 'dale', 'adelante', 'proceder', 'está bien', 'esta bien'];
            foreach ($confirmationKeywords as $keyword) {
                if (str_contains($lastUserMessage, $keyword)) {
                    $hasConfirmation = true;
                    break;
                }
            }
        }

        if (!$hasConfirmation) {
            Log::info('ToolExecutorService: Orden rechazada por falta de confirmación explícita', ['msg' => $lastUserMessage]);
            return [
                'success' => false,
                'requires_confirmation' => true,
                'message' => 'Para proceder con tu pedido, confírmame con un "sí", "confirmo" o "lo quiero". ¿Deseas confirmar la compra?',
                'order_summary' => [
                    'items' => $items,
                    'shipping_method' => $shippingMethod,
                    'payment_method' => $paymentMethod,
                ],
            ];
        }

        try {
            if (empty($items)) {
                return ['success' => false, 'error' => 'No hay ítems en la orden.'];
            }

            // Validar stock de cada item antes de crear el pedido
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $color = $item['color'] ?? null;
                $size = $item['size'] ?? null;
                $qty = (int) ($item['qty'] ?? 1);

                if (!$productId || !$color || !$size) {
                    return ['success' => false, 'error' => 'Cada item debe tener product_id, color y size.'];
                }

                // Validar que color y talla existan en la variante
                $variant = ProductVariant::where('product_id', $productId)
                    ->where('color', 'like', "%{$color}%")
                    ->first();

                if (!$variant) {
                    Log::warning('ToolExecutorService: Color no disponible', [
                        'product_id' => $productId,
                        'color' => $color,
                    ]);
                    return [
                        'success' => false,
                        'error' => "Color '{$color}' no disponible para este producto.",
                    ];
                }

                if (!isset($variant->sizes_stock[$size])) {
                    Log::warning('ToolExecutorService: Talla no disponible', [
                        'product_id' => $productId,
                        'color' => $color,
                        'size' => $size,
                    ]);
                    return [
                        'success' => false,
                        'error' => "Talla '{$size}' no disponible en color '{$color}'.",
                    ];
                }

                $stockValidation = PriceValidatorService::validateStock($productId, $color, $size, $qty);
                if (!$stockValidation['available']) {
                    Log::warning('ToolExecutorService: Stock insuficiente', [
                        'product_id' => $productId,
                        'color' => $color,
                        'size' => $size,
                        'requested' => $qty,
                        'available' => $stockValidation['stock'],
                    ]);
                    return [
                        'success' => false,
                        'error' => $stockValidation['error'],
                        'stock_available' => $stockValidation['stock'],
                    ];
                }
            }

            // Validar precios e integridad usando PriceValidatorService
            $priceValidation = PriceValidatorService::validateOrderItems($items);
            if (!$priceValidation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validación de precios fallida: ' . implode(', ', $priceValidation['errors']),
                ];
            }

            $subtotal = $priceValidation['subtotal'];
            if ($subtotal <= 0) {
                return ['success' => false, 'error' => 'El monto del pedido debe ser mayor a 0.'];
            }

            // Crear la orden principal
            $order = Order::create([
                'customer_id' => $state->customer_id,
                'conversation_state_id' => $state->id,
                'status' => 'pending',
                'shipping_method' => $shippingMethod,
                'payment_method' => $paymentMethod,
                'district' => $district,
                'full_address' => $address,
                'shipping_cost' => 0.00,
                'amount_subtotal' => $subtotal,
                'amount_total' => $subtotal,
            ]);

            // Registrar los ítems del pedido
            $itemsSummary = [];
            foreach ($priceValidation['items'] as $validatedItem) {
                $product = $validatedItem['product'];
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('color', 'like', "%{$validatedItem['color']}%")
                    ->first();

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'color' => $validatedItem['color'],
                    'size' => $validatedItem['size'],
                    'qty' => $validatedItem['qty'],
                    'unit_price' => $validatedItem['unit_price'],
                    'total' => $validatedItem['total'],
                ]);

                $itemsSummary[] = [
                    'product' => $product->name,
                    'color' => $validatedItem['color'],
                    'size' => $validatedItem['size'],
                    'qty' => $validatedItem['qty'],
                    'unit_price' => $validatedItem['unit_price'],
                    'total' => $validatedItem['total'],
                ];
            }

            // Calcular costos de envío
            $shippingCost = 0.00;
            if ($shippingMethod === 'shalom') {
                $shippingCost = 10.00;
            } elseif ($shippingMethod === 'motorizado' && $district) {
                $zone = DeliveryZone::where('district', 'like', "%{$district}%")->first();
                $shippingCost = $zone ? ($zone->cost_motorizado ?? 15.00) : 15.00;
            }

            $order->update([
                'shipping_cost' => $shippingCost,
                'amount_total' => $subtotal + $shippingCost,
            ]);

            return [
                'success' => true,
                'order_id' => $order->id,
                'status' => 'pending',
                'items' => $itemsSummary,
                'shipping_cost' => $shippingCost,
                'subtotal' => $subtotal,
                'total' => $subtotal + $shippingCost,
                'message' => 'El pedido se ha creado con éxito en estado Pendiente. Se solicita captura de pago Yape.',
            ];

        } catch (\Exception $e) {
            Log::error('ToolExecutorService: Falló executeCreateOrder', ['error' => $e->getMessage()]);
            return ['error' => 'Error al crear pedido en base de datos: ' . $e->getMessage()];
        }
    }

    /**
     * Escala el chat derivándolo a un asesor humano.
     */
    public function executeEscalateToHuman(ConversationState $state, string $reason): array
    {
        Log::info('ToolExecutorService: executeEscalateToHuman', ['phone' => $state->phone_number, 'reason' => $reason]);

        $handoffPayload = app(HandoffContextService::class)->build($state, $reason);
        $context = $state->context ?? [];
        $context['handoff'] = $handoffPayload;
        $state->context = $context;

        $state->update([
            'requires_human' => true,
            'is_auto_escalated' => true,
            'last_human_activity_at' => now(),
        ]);

        AgentHandoff::create([
            'conversation_state_id' => $state->id,
            'reason' => $reason,
            'requested_at' => now(),
        ]);

        return [
            'escalated' => true,
            'message' => 'Se ha asignado la conversación a un asesor humano. El bot dejará de responder automáticamente.',
        ];
    }

    /**
     * Encola botones interactivos de WhatsApp en el contexto de la conversación.
     */
    public function executeSendInteractiveButtons(ConversationState $state, string $body, array $buttons, ?string $footer = null): array
    {
        Log::info('ToolExecutorService: executeSendInteractiveButtons', ['phone' => $state->phone_number]);

        $normalizedButtons = [];
        foreach (array_slice($buttons, 0, 3) as $btn) {
            $id = mb_substr((string) ($btn['id'] ?? ''), 0, 200);
            $title = mb_substr((string) ($btn['title'] ?? ''), 0, 20);
            if ($id === '' || $title === '') {
                continue;
            }
            $normalizedButtons[] = ['id' => $id, 'title' => $title];
        }

        if (empty($normalizedButtons)) {
            return [
                'success' => false,
                'error' => 'No se pudieron construir botones válidos para WhatsApp.',
            ];
        }

        $context = $state->context;
        $context['pending_interactive'] = [
            'type' => 'interactive',
            'interactive' => [
                'kind' => 'button',
                'body' => ['text' => mb_substr($body, 0, 1024)],
                'buttons' => $normalizedButtons, // WhatsApp solo permite máximo 3 botones
            ]
        ];

        if ($footer) {
            $context['pending_interactive']['interactive']['footer'] = ['text' => mb_substr($footer, 0, 60)];
        }

        $state->context = $context;
        $state->save();

        return [
            'success' => true,
            'message' => 'Botones encolados exitosamente. Se adjuntarán nativamente a tu respuesta final.',
        ];
    }

    /**
     * Encola una lista interactiva de WhatsApp (máx 10 filas) en el contexto conversacional.
     */
    public function executeSendInteractiveList(
        ConversationState $state,
        string $body,
        string $buttonText,
        array $sections,
        ?string $footer = null
    ): array {
        Log::info('ToolExecutorService: executeSendInteractiveList', ['phone' => $state->phone_number]);

        $context = $state->context;
        $context['pending_interactive'] = [
            'type' => 'interactive',
            'interactive' => [
                'kind' => 'list',
                'body' => ['text' => mb_substr($body, 0, 1024)],
                'button' => mb_substr($buttonText, 0, 20),
                'sections' => $sections,
            ]
        ];

        if ($footer) {
            $context['pending_interactive']['interactive']['footer'] = ['text' => mb_substr($footer, 0, 60)];
        }

        $state->context = $context;
        $state->save();

        return [
            'success' => true,
            'message' => 'Lista de selección encolada exitosamente. Se adjuntará nativamente a tu respuesta final.',
        ];
    }

    /**
     * Devuelve un resumen del perfil del cliente para respuestas basadas en CRM.
     */
    public function executeGetCustomerProfile(ConversationState $state): array
    {
        $customer = Customer::with(['orders.items.product'])->find($state->customer_id);
        if (!$customer) {
            return [
                'found' => false,
                'message' => 'Cliente no encontrado.',
            ];
        }

        $lastOrder = $customer->orders->sortByDesc('created_at')->first();

        return [
            'found' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'segment' => $customer->segment,
                'lifetime_value' => (float) ($customer->lifetime_value ?? 0),
                'notes' => $customer->notes,
                'orders_count' => $customer->orders->count(),
                'last_order_id' => $lastOrder?->id,
                'last_order_status' => $lastOrder?->status,
            ],
        ];
    }

    /**
     * Obtiene el estado resumido de un pedido para seguimiento.
     */
    public function executeGetOrderStatus(ConversationState $state, ?int $orderId = null): array
    {
        $query = Order::where('customer_id', $state->customer_id)->with('items.product');
        if ($orderId) {
            $query->where('id', $orderId);
        }

        $order = $query->orderByDesc('created_at')->first();
        if (!$order) {
            return [
                'found' => false,
                'message' => 'No hay pedidos registrados para esta conversación.',
            ];
        }

        return [
            'found' => true,
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'created_at' => $order->created_at?->toDateTimeString(),
                'amount_total' => (float) $order->amount_total,
                'shipping_method' => $order->shipping_method,
                'payment_method' => $order->payment_method,
                'district' => $order->district,
                'items' => $order->items->map(fn ($item) => [
                    'product' => $item->product?->name ?? 'Producto',
                    'color' => $item->color,
                    'size' => $item->size,
                    'qty' => $item->qty,
                ])->values()->all(),
            ],
        ];
    }

    protected function findCategoryByName(string $name): ?Category
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === '' || mb_strlen($needle) < 2) {
            return null;
        }

        $categories = Category::query()
            ->whereHas('products', fn ($q) => $q->whereHas('variants'))
            ->get();

        foreach ($categories as $cat) {
            if (mb_strtolower($cat->name) === $needle) {
                return $cat;
            }
        }

        foreach ($categories as $cat) {
            $catName = mb_strtolower($cat->name);
            if (str_contains($catName, $needle) || str_contains($needle, $catName)) {
                return $cat;
            }
        }

        return null;
    }
}
