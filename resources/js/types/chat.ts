export interface ChatMessage {
    id: number;
    message_id: string;
    phone_number: string;
    customer_id: number | null;
    conversation_state_id: number | null;
    customer_name: string | null;
    content: string;
    direction: 'incoming' | 'outgoing';
    status: string;
    created_at: string;
    metadata?: Record<string, unknown> | null;
    customer: { id: number; name: string | null } | null;
    conversation_state: {
        id: number;
        requires_human: boolean;
        is_auto_escalated?: boolean;
        asesor_post_pedido?: boolean;
    } | null;
    whatsapp_timestamp?: string | null;
}

export interface ChatConversation {
    phone: string;
    name: string | null;
    lastMessage: string;
    lastTime: string;
    count: number;
    requires_human: boolean;
    is_auto_escalated: boolean;
    asesor_post_pedido: boolean;
    customer_id: number | null;
}

export type ChatFilterType = 'all' | 'human' | 'ai';

export type ChatInboxTab = 'active' | 'shipping';

export interface OrderItem {
    id: number;
    product: { name: string };
    color: string | null;
    size: string | null;
    qty: number;
    unit_price: string;
}

export interface CustomerOrder {
    id: number;
    status: string;
    amount_total: string;
    shipping_method: string;
    created_at: string;
    items: OrderItem[];
}

export interface CustomerDetails {
    id: number;
    phone_number: string;
    name: string | null;
    email: string | null;
    segment: string | null;
    lifetime_value: string;
    notes: string | null;
    tags: string[] | null;
    orders: CustomerOrder[];
    conversation_state: { requires_human: boolean; is_auto_escalated?: boolean } | null;
}

export interface BotMetrics {
    summary: {
        intent_total: number;
        route_total: number;
        error_total: number;
    };
    intents: Record<string, number>;
    routes: Record<string, number>;
    errors: Record<string, number>;
    updated_at: string;
}

export interface EscalationAlert {
    phone_number: string;
    summary?: string;
    at: string;
}

export interface ColorGalleryItem {
    color: string;
    image_url: string | null;
    has_stock: boolean;
    stock_summary: string;
}

export interface SalesContextProduct {
    id: number;
    name: string;
    final_price: number;
    thumbnail: string | null;
    colors?: ColorGalleryItem[];
}

export interface PaymentValidationContext {
    pending: boolean;
    order_id: number | null;
    order_status: string | null;
    order_total: number | null;
    payment_proof_url: string | null;
}

export interface CartLineContext {
    producto: string;
    color: string;
    talla: string;
    precio: number;
}

export interface StockPorColorRow {
    color: string;
    tallas: string[];
    agotado: boolean;
}

export interface PedidoConfirmadoLine {
    product: string | null;
    color: string | null;
    size: string | null;
    qty: number;
    total: number;
}

export interface SalesContext {
    phone: string;
    sales_stage: string | null;
    etapa_venta?: string | null;
    etapa_venta_label?: string | null;
    asesor_post_pedido?: boolean;
    pedido_confirmado_items?: PedidoConfirmadoLine[];
    payment_validation?: PaymentValidationContext;
    handoff: { summary?: string; reason?: string } | null;
    current_product: {
        id: number;
        name: string;
        price: number;
        status?: string;
        selected_color: string | null;
        selected_size: string | null;
    } | null;
    stock_por_color?: StockPorColorRow[];
    carrito?: CartLineContext[];
    carrito_subtotal?: number;
    datos_envio?: Record<string, string | number | null> | null;
    colors: ColorGalleryItem[];
    recent_products: SalesContextProduct[];
    featured_products: Array<{
        id: number;
        name: string;
        final_price: number;
        thumbnail: string | null;
    }>;
}
