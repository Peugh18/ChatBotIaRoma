<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Order;
use App\Models\Product;
use App\Support\EtapaVenta;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Repositorios\RepositorioCatalogo;

/**
 * Contexto de venta para el panel del asesor en Chat CRM (alineado con flujo V1).
 */
class ServicioContextoVentaChat
{
    public function __construct(
        protected ServicioMediaProducto $media,
        protected RepositorioCatalogo $catalogo,
        protected MaquinaEstadosVentas $maquina,
    ) {}

    public function forPhone(string $phone): array
    {
        $state = ConversationState::where('phone_number', $phone)->first();
        $ctx = $state?->context ?? [];

        $asesorPostPedido = $state && app(ServicioModoConversacionPedido::class)->tieneAsesorPostPedido($state);

        $productId = $asesorPostPedido ? 0 : (int) ($ctx['producto_actual_id'] ?? $ctx['current_product_id'] ?? 0);
        $colors = [];
        $currentProduct = null;
        $stockPorColor = [];

        if ($productId > 0) {
            $product = Product::with('variants')->find($productId);
            if ($product) {
                $price = $product->precioFinal();
                $colorSel = $ctx['color_actual'] ?? $ctx['current_color'] ?? null;
                $tallaSel = $ctx['talla_actual'] ?? $ctx['current_size'] ?? null;

                $currentProduct = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $price,
                    'status' => $product->status ?? Product::ESTADO_DISPONIBLE,
                    'selected_color' => $colorSel,
                    'selected_size' => $tallaSel,
                ];

                foreach ($this->catalogo->stockPorColor($product) as $fila) {
                    $stockPorColor[] = [
                        'color' => $fila['color'],
                        'tallas' => $fila['tallas'],
                        'agotado' => $fila['agotado'],
                    ];
                }

                $colors = $this->enrichColors($product);
            }
        }

        $carrito = [];
        $subtotalCarrito = 0.0;
        if (! $asesorPostPedido) {
            foreach ($ctx['carrito'] ?? [] as $linea) {
                $precio = (float) ($linea['precio'] ?? 0);
                $subtotalCarrito += $precio;
                $carrito[] = [
                    'producto' => $linea['nombre'] ?? '',
                    'color' => $linea['color'] ?? '',
                    'talla' => $linea['talla'] ?? '',
                    'precio' => $precio,
                ];
            }
        }

        $orderId = (int) ($ctx['ultimo_pedido_id'] ?? $ctx['last_order_id'] ?? 0);
        $pendingOrder = $orderId > 0 ? Order::with('items.product')->find($orderId) : null;

        $pedidoConfirmadoItems = [];
        if ($asesorPostPedido && $pendingOrder) {
            $pedidoConfirmadoItems = $pendingOrder->items?->map(fn ($item) => [
                'product' => $item->product?->name,
                'color' => $item->color,
                'size' => $item->size,
                'qty' => $item->qty,
                'total' => (float) $item->total,
            ])->values()->all() ?? [];
        }

        $etapa = $this->maquina->obtener($state) ?? EtapaVenta::obtener($state);

        $datosEnvio = $ctx['datos_envio'] ?? null;

        return [
            'phone' => $phone,
            'sales_stage' => $etapa,
            'etapa_venta' => $etapa,
            'etapa_venta_label' => $this->etiquetaEtapa($etapa),
            'handoff' => $ctx['handoff'] ?? null,
            'current_product' => $currentProduct,
            'stock_por_color' => $stockPorColor,
            'colors' => $colors,
            'carrito' => $carrito,
            'carrito_subtotal' => $subtotalCarrito,
            'datos_envio' => is_array($datosEnvio) ? $datosEnvio : null,
            'recent_products' => [],
            'featured_products' => Product::with('variants')
                ->where('status', '!=', Product::ESTADO_OCULTO)
                ->whereHas('variants')
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'final_price' => $p->precioFinal(),
                    'thumbnail' => $this->firstVariantImage($p),
                ])
                ->values()
                ->all(),
            'asesor_post_pedido' => $asesorPostPedido,
            'pedido_confirmado_items' => $pedidoConfirmadoItems,
            'payment_validation' => [
                'pending' => EtapaVenta::esValidacionPago($state),
                'order_id' => $orderId > 0 ? $orderId : null,
                'order_status' => $pendingOrder?->status,
                'order_total' => $pendingOrder ? (float) $pendingOrder->amount_total : null,
                'payment_proof_url' => $pendingOrder?->payment_proof_url,
                'items' => $pendingOrder?->items?->map(fn ($item) => [
                    'product' => $item->product?->name,
                    'color' => $item->color,
                    'size' => $item->size,
                    'qty' => $item->qty,
                ])->values()->all() ?? [],
            ],
            'card_payment_link' => [
                'pending' => $state && app(ServicioLinkPagoTarjeta::class)->estaPendiente($state),
                'order_id' => $orderId > 0 ? $orderId : null,
                'order_total' => $pendingOrder ? (float) $pendingOrder->amount_total : null,
                'waiting_since' => $ctx[ServicioLinkPagoTarjeta::CTX_SOLICITADO_AT] ?? null,
                'items' => $pendingOrder?->items?->map(fn ($item) => [
                    'product' => $item->product?->name,
                    'color' => $item->color,
                    'size' => $item->size,
                    'qty' => $item->qty,
                ])->values()->all() ?? [],
            ],
        ];
    }

    protected function etiquetaEtapa(?string $etapa): ?string
    {
        return match ($etapa) {
            EtapaVentas::INICIO, null => 'Inicio',
            EtapaVentas::CATEGORIA => 'Eligiendo categoría',
            EtapaVentas::PRODUCTOS => 'Lista de productos',
            EtapaVentas::PRODUCTO => 'Ficha de producto',
            EtapaVentas::COLOR => 'Eligiendo color',
            EtapaVentas::TALLA => 'Eligiendo talla',
            EtapaVentas::MAS_O_CONFIRMAR => 'Carrito / confirmar',
            EtapaVentas::ENVIO_METODO, EtapaVentas::ENVIO_DATOS, EtapaVentas::DATOS_REUTILIZAR => 'Datos de envío',
            EtapaVentas::RESUMEN => 'Resumen del pedido',
            EtapaVentas::PAGO => 'Método de pago',
            EtapaVentas::COMPROBANTE => 'Esperando comprobante',
            EtapaVentas::TARJETA_DATOS => 'Datos tarjeta',
            EtapaVentas::ESPERANDO_LINK_TARJETA => 'Link tarjeta pendiente',
            EtapaVentas::VALIDACION_PAGO, 'awaiting_payment_validation' => 'Validando pago',
            default => $etapa,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function enrichColors(Product $product): array
    {
        $rows = [];
        foreach ($this->media->colorsForProduct($product) as $row) {
            $variant = $product->variants->first(
                fn ($v) => mb_strtolower($v->color) === mb_strtolower($row['color'])
            );
            $stock = $variant?->sizes_stock ?? [];
            $parts = [];
            if (is_array($stock)) {
                foreach ($stock as $size => $qty) {
                    if ((int) $qty > 0) {
                        $parts[] = "{$size}:{$qty}";
                    }
                }
            }

            $rows[] = [
                'color' => $row['color'],
                'image_url' => $row['image_url'],
                'has_stock' => $row['has_stock'],
                'stock_summary' => empty($parts) ? 'Sin stock' : implode(', ', $parts),
            ];
        }

        return $rows;
    }

    protected function firstVariantImage(Product $product): ?string
    {
        foreach ($product->variants as $variant) {
            $url = $this->media->resolvePublicUrl($variant);
            if ($url) {
                return $url;
            }
        }

        return null;
    }
}
