<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { apiJson, ApiError } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Truck, CheckCircle2, AlertCircle, ShoppingBag, XCircle, ArrowRight, ArrowLeft } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Ventas (Pipeline)',
        href: '/pipeline',
    },
];

interface OrderItem {
    id: number;
    product: { id: number; name: string };
    color: string | null;
    size: string | null;
    qty: number;
    unit_price: string;
    total: string;
}

interface Order {
    id: number;
    customer_id: number;
    status: 'pending' | 'paid' | 'shipped' | 'delivered' | 'cancelled';
    shipping_method: 'shalom' | 'motorizado' | 'none';
    shipping_cost: string;
    payment_method: 'yape' | 'card' | 'link' | 'cash';
    payment_proof_url: string | null;
    district: string | null;
    full_address: string | null;
    amount_subtotal: string;
    amount_total: string;
    notes: string | null;
    created_at: string;
    customer: { id: number; name: string | null; phone_number: string } | null;
    items: OrderItem[];
}

const orders = ref<Order[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const updatingId = ref<number | null>(null);

const fetchOrders = async () => {
    loading.value = true;
    error.value = null;
    try {
        orders.value = await apiJson<Order[]>('/api/orders');
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar los pedidos.';
        orders.value = [];
    } finally {
        loading.value = false;
    }
};

const updateOrderStatus = async (orderId: number, newStatus: Order['status']) => {
    updatingId.value = orderId;
    try {
        await apiJson(`/api/orders/${orderId}`, {
            method: 'PUT',
            body: JSON.stringify({ status: newStatus }),
        });
        await fetchOrders();
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al actualizar el estado del pedido';
        alert(msg);
    } finally {
        updatingId.value = null;
    }
};

// Columnas del Kanban
const columns = [
    { key: 'pending', title: 'Pendiente', color: 'border-t-4 border-t-yellow-500' },
    { key: 'paid', title: 'Pagado', color: 'border-t-4 border-t-blue-500' },
    { key: 'shipped', title: 'Enviado', color: 'border-t-4 border-t-primary' },
    { key: 'delivered', title: 'Entregado', color: 'border-t-4 border-t-green-500' },
    { key: 'cancelled', title: 'Cancelado', color: 'border-t-4 border-t-red-400' },
] as const;

type OrderStatus = Order['status'];

// Agrupar órdenes por columna
const ordersByColumn = computed(() => {
    const map: Record<OrderStatus, Order[]> = {
        pending: [],
        paid: [],
        shipped: [],
        delivered: [],
        cancelled: [],
    };
    for (const order of orders.value) {
        if (map[order.status]) {
            map[order.status].push(order);
        }
    }
    return map;
});

// Sumas totales por columna
const columnTotals = computed(() => {
    const map: Record<OrderStatus, number> = {
        pending: 0,
        paid: 0,
        shipped: 0,
        delivered: 0,
        cancelled: 0,
    };
    for (const order of orders.value) {
        map[order.status] += parseFloat(order.amount_total);
    }
    return map;
});

const nextStatus = (current: OrderStatus): OrderStatus | null => {
    const idx = columns.findIndex((c) => c.key === current);
    if (idx < 0 || idx >= columns.length - 2) {
        return null;
    }
    return columns[idx + 1].key as OrderStatus;
};

const prevStatus = (current: OrderStatus): OrderStatus | null => {
    const idx = columns.findIndex((c) => c.key === current);
    if (idx <= 0) {
        return null;
    }
    return columns[idx - 1].key as OrderStatus;
};

onMounted(() => {
    fetchOrders();
});
</script>

<template>
    <Head title="Pipeline de Ventas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader
                title="Pipeline de ventas"
                description="Ciclo de pedidos desde cotización del bot hasta entrega final."
            />

            <div
                v-if="error"
                class="mt-4 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                role="alert"
            >
                {{ error }}
            </div>

            <div v-if="loading" class="py-24 text-center text-lg text-muted-foreground">Cargando pipeline…</div>

            <!-- Kanban Board -->
            <div v-else class="mt-8 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 overflow-x-auto pb-4">
                <div
                    v-for="col in columns"
                    :key="col.key"
                    :class="[
                        'flex min-h-[600px] min-w-[250px] flex-col rounded-xl border border-border bg-muted/30 p-4 shadow-sm',
                        col.color
                    ]"
                >
                    <!-- Col Title -->
                    <div class="mb-4 flex items-center justify-between border-b border-border pb-3">
                        <div>
                            <h3 class="text-base font-semibold text-foreground">
                                {{ col.title }}
                            </h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Total: S/ {{ columnTotals[col.key].toFixed(2) }}
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 border border-gray-200/50 dark:border-gray-700/50">
                            {{ ordersByColumn[col.key].length }}
                        </span>
                    </div>

                    <!-- Cards -->
                    <div class="flex-1 space-y-4 overflow-y-auto max-h-[700px] pr-1">
                        <div
                            v-for="order in ordersByColumn[col.key]"
                            :key="order.id"
                            class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-100 dark:border-gray-700/80 shadow-sm hover:shadow-md transition duration-200 relative group"
                            :class="{ 'opacity-60 pointer-events-none': updatingId === order.id }"
                        >
                            <!-- Card Header -->
                            <div class="flex justify-between items-start gap-1">
                                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                    Pedido #{{ order.id }}
                                </span>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500">
                                    {{ new Date(order.created_at).toLocaleDateString([], { month: 'short', day: 'numeric' }) }}
                                </span>
                            </div>

                            <!-- Customer Profile -->
                            <div class="mt-2">
                                <div class="font-medium text-gray-900 dark:text-white text-sm truncate">
                                    {{ order.customer?.name || 'Cliente sin nombre' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ order.customer?.phone_number }}
                                </div>
                            </div>

                            <!-- Items Details -->
                            <div class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-2 space-y-1">
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="text-xs text-gray-600 dark:text-gray-300 flex justify-between"
                                >
                                    <span class="truncate pr-2">
                                        {{ item.product.name }} ({{ item.color }}, {{ item.size }})
                                    </span>
                                    <span class="font-medium shrink-0">x{{ item.qty }}</span>
                                </div>
                            </div>

                            <!-- District & Shipping method -->
                            <div v-if="order.district" class="mt-2 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 p-1.5 rounded flex items-center gap-1">
                                <Truck class="h-3 w-3 text-indigo-500" />
                                <span class="truncate">
                                    {{ order.district }} ({{ order.shipping_method }})
                                </span>
                            </div>

                            <div
                                v-if="order.payment_proof_url"
                                class="mt-2 text-xs text-emerald-600 dark:text-emerald-400"
                            >
                                Comprobante registrado
                            </div>

                            <!-- Total Price -->
                            <div class="mt-4 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 block uppercase tracking-wider">Total</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">
                                        S/ {{ parseFloat(order.amount_total).toFixed(2) }}
                                    </span>
                                </div>

                                <!-- Move status buttons -->
                                <div class="flex items-center gap-1 opacity-80 group-hover:opacity-100 transition duration-150">
                                    <button
                                        v-if="prevStatus(col.key)"
                                        type="button"
                                        :disabled="updatingId === order.id"
                                        @click="updateOrderStatus(order.id, prevStatus(col.key)!)"
                                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400"
                                        title="Retroceder estado"
                                    >
                                        <ArrowLeft class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="nextStatus(col.key)"
                                        type="button"
                                        :disabled="updatingId === order.id"
                                        @click="updateOrderStatus(order.id, nextStatus(col.key)!)"
                                        class="p-1 rounded bg-indigo-50 dark:bg-indigo-950/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400"
                                        title="Avanzar estado"
                                    >
                                        <ArrowRight class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="col.key !== 'cancelled' && col.key !== 'delivered'"
                                        type="button"
                                        :disabled="updatingId === order.id"
                                        @click="updateOrderStatus(order.id, 'cancelled')"
                                        class="p-1 rounded hover:bg-red-50 dark:hover:bg-red-950/20 text-red-500 dark:text-red-400"
                                        title="Cancelar pedido"
                                    >
                                        <XCircle class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Column State -->
                        <div
                            v-if="ordersByColumn[col.key].length === 0"
                            class="border border-dashed border-gray-200 dark:border-gray-800 rounded-lg p-6 text-center text-gray-400 dark:text-gray-600 text-sm"
                        >
                            No hay pedidos
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
