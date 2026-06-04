<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { apiJson, ApiError } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { 
    Truck, 
    CheckCircle2, 
    AlertCircle, 
    ShoppingBag, 
    XCircle, 
    ArrowRight, 
    ArrowLeft, 
    Search, 
    ChevronLeft, 
    ChevronRight, 
    List, 
    Kanban,
    FileText
} from 'lucide-vue-next';

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

// View Mode State
const viewMode = ref<'kanban' | 'list'>('kanban');

// Search and Pagination State for List View
const searchQuery = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);
const itemsPerPage = 10;

// Order Details Modal State
const selectedOrder = ref<Order | null>(null);
const openOrderDetails = (order: Order) => {
    selectedOrder.value = order;
};
const closeOrderDetails = () => {
    selectedOrder.value = null;
};
const openUrl = (url: string) => {
    window.open(url, '_blank');
};

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

// Filtered orders for the List view
const filteredOrders = computed(() => {
    let list = orders.value;

    // 1. Filter by Status
    if (statusFilter.value !== 'all') {
        list = list.filter((order) => order.status === statusFilter.value);
    }

    // 2. Filter by Search Query
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter((order) => {
            const matchesId = String(order.id).includes(q);
            const matchesCustomerName = order.customer?.name?.toLowerCase().includes(q) ?? false;
            const matchesPhone = order.customer?.phone_number.includes(q) ?? false;
            const matchesDistrict = order.district?.toLowerCase().includes(q) ?? false;
            const matchesProducts = order.items.some((item) =>
                item.product.name.toLowerCase().includes(q)
            );
            return matchesId || matchesCustomerName || matchesPhone || matchesDistrict || matchesProducts;
        });
    }

    // Sort by created_at descending (newest first)
    return [...list].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
});

// Calculate total pages for list view
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredOrders.value.length / itemsPerPage));
});

// Paginated orders for list view
const paginatedOrders = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredOrders.value.slice(start, start + itemsPerPage);
});

// Reset page to 1 when search or filter changes
watch([searchQuery, statusFilter], () => {
    currentPage.value = 1;
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
            >
                <template #actions>
                    <div class="flex items-center gap-1 border border-border bg-background p-1 rounded-lg shadow-sm">
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            class="h-8 gap-1.5 px-3" 
                            :class="viewMode === 'kanban' ? 'bg-muted text-foreground font-medium' : 'text-muted-foreground'"
                            @click="viewMode = 'kanban'"
                        >
                            <Kanban class="h-4 w-4" />
                            <span>Kanban</span>
                        </Button>
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            class="h-8 gap-1.5 px-3" 
                            :class="viewMode === 'list' ? 'bg-muted text-foreground font-medium' : 'text-muted-foreground'"
                            @click="viewMode = 'list'"
                        >
                            <List class="h-4 w-4" />
                            <span>Lista</span>
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <div
                v-if="error"
                class="mt-4 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                role="alert"
            >
                {{ error }}
            </div>

            <div v-if="loading" class="py-24 text-center text-lg text-muted-foreground">Cargando pipeline…</div>

            <div v-else>
                <!-- Kanban Board View -->
                <div v-if="viewMode === 'kanban'" class="mt-8 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 overflow-x-auto pb-4">
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

                                <!-- Items Details Preview -->
                                <div 
                                    class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-2 cursor-pointer group/item"
                                    @click="openOrderDetails(order)"
                                    title="Ver todos los detalles"
                                >
                                    <div class="text-xs text-indigo-600 dark:text-indigo-400 font-medium group-hover/item:underline flex justify-between">
                                        <span class="truncate pr-2">
                                            {{ order.items[0].product.name }}
                                            <span v-if="order.items.length > 1" class="text-muted-foreground font-normal ml-0.5">
                                                (+{{ order.items.length - 1 }} más)
                                            </span>
                                        </span>
                                        <span class="font-semibold shrink-0">x{{ order.items[0].qty }}</span>
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

                <!-- Paginated List View -->
                <div v-else class="mt-8 space-y-4">
                    <!-- Filters and Search Toolbar -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="relative w-full max-w-md">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Buscar por cliente, pedido, producto o distrito..."
                                class="pl-9"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-muted-foreground shrink-0">Filtrar por:</span>
                            <select
                                v-model="statusFilter"
                                class="flex h-9 w-full min-w-[160px] rounded-md border border-input bg-background px-3 py-1 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="all">Todos los estados</option>
                                <option value="pending">Pendiente</option>
                                <option value="paid">Pagado</option>
                                <option value="shipped">Enviado</option>
                                <option value="delivered">Entregado</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>
                    </div>

                    <!-- List Table Panel -->
                    <CrmPanel no-padding>
                        <div v-if="filteredOrders.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                            No se encontraron pedidos que coincidan con la búsqueda
                        </div>
                        <div v-else>
                            <div class="overflow-x-auto">
                                <table class="crm-table">
                                    <thead>
                                        <tr>
                                            <th>Pedido</th>
                                            <th>Cliente</th>
                                            <th>Detalles</th>
                                            <th>Envío</th>
                                            <th class="text-right !text-right">Total</th>
                                            <th class="text-center" style="text-align: center;">Estado</th>
                                            <th class="text-center" style="text-align: center;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr v-for="order in paginatedOrders" :key="order.id" :class="{ 'opacity-65 pointer-events-none': updatingId === order.id }">
                                            <td class="font-medium text-foreground">
                                                <div class="font-semibold text-indigo-600 dark:text-indigo-400">
                                                    Pedido #{{ order.id }}
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    {{ new Date(order.created_at).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="font-medium text-foreground">
                                                    {{ order.customer?.name || 'Cliente sin nombre' }}
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    {{ order.customer?.phone_number }}
                                                </div>
                                            </td>
                                            <td>
                                                <div 
                                                    class="cursor-pointer hover:underline text-indigo-600 dark:text-indigo-400 font-medium text-xs flex items-center" 
                                                    @click="openOrderDetails(order)"
                                                    title="Ver detalles del pedido"
                                                >
                                                    <span>{{ order.items[0].product.name }}</span>
                                                    <span v-if="order.items.length > 1" class="text-[10px] text-muted-foreground font-normal ml-1 shrink-0">
                                                        (+{{ order.items.length - 1 }} más)
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div v-if="order.district" class="text-xs">
                                                    <div class="font-medium text-foreground flex items-center gap-1">
                                                        <Truck class="h-3.5 w-3.5 text-indigo-500" />
                                                        <span class="capitalize">{{ order.shipping_method }}</span>
                                                    </div>
                                                    <div class="text-muted-foreground truncate max-w-[180px]" :title="order.district + ' - ' + order.full_address">
                                                        {{ order.district }} - {{ order.full_address || 'Sin dirección' }}
                                                    </div>
                                                </div>
                                                <span v-else class="text-xs text-muted-foreground">—</span>
                                            </td>
                                            <td class="text-right font-medium">
                                                <div class="text-sm font-bold text-foreground">
                                                    S/ {{ parseFloat(order.amount_total).toFixed(2) }}
                                                </div>
                                                <div class="text-[10px] text-muted-foreground capitalize">
                                                    {{ order.payment_method }}
                                                </div>
                                            </td>
                                            <td class="text-center" style="text-align: center;">
                                                <span 
                                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold animate-fade-in"
                                                    :class="{
                                                        'bg-yellow-50 text-yellow-700 border border-yellow-200 dark:bg-yellow-950/30 dark:text-yellow-400 dark:border-yellow-900/40': order.status === 'pending',
                                                        'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-900/40': order.status === 'paid',
                                                        'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/30 dark:text-indigo-400 dark:border-indigo-900/40': order.status === 'shipped',
                                                        'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/40': order.status === 'delivered',
                                                        'bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/30 dark:text-red-400 dark:border-red-900/40': order.status === 'cancelled',
                                                    }"
                                                >
                                                    {{ 
                                                        order.status === 'pending' ? 'Pendiente' : 
                                                        order.status === 'paid' ? 'Pagado' : 
                                                        order.status === 'shipped' ? 'Enviado' : 
                                                        order.status === 'delivered' ? 'Entregado' : 'Cancelado' 
                                                    }}
                                                </span>
                                            </td>
                                            <td class="text-center" style="text-align: center;">
                                                <div class="flex items-center justify-center gap-1">
                                                    <Button
                                                        v-if="prevStatus(order.status)"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground hover:bg-muted"
                                                        :disabled="updatingId === order.id"
                                                        @click="updateOrderStatus(order.id, prevStatus(order.status)!)"
                                                        title="Retroceder estado"
                                                    >
                                                        <ArrowLeft class="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        v-if="nextStatus(order.status)"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 w-8 p-0 text-primary hover:text-primary hover:bg-primary/10"
                                                        :disabled="updatingId === order.id"
                                                        @click="updateOrderStatus(order.id, nextStatus(order.status)!)"
                                                        title="Avanzar estado"
                                                    >
                                                        <ArrowRight class="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        v-if="order.status !== 'cancelled' && order.status !== 'delivered'"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 w-8 p-0 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                        :disabled="updatingId === order.id"
                                                        @click="updateOrderStatus(order.id, 'cancelled')"
                                                        title="Cancelar pedido"
                                                    >
                                                        <XCircle class="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Footer -->
                            <div class="flex items-center justify-between border-t border-border px-5 py-4 bg-muted/10 flex-wrap gap-4">
                                <div class="text-xs text-muted-foreground">
                                    Mostrando <span class="font-semibold">{{ Math.min(filteredOrders.length, (currentPage - 1) * itemsPerPage + 1) }}</span> a <span class="font-semibold">{{ Math.min(filteredOrders.length, currentPage * itemsPerPage) }}</span> de <span class="font-semibold">{{ filteredOrders.length }}</span> pedidos
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button 
                                        variant="outline" 
                                        size="icon"
                                        class="h-8 w-8"
                                        :disabled="currentPage === 1" 
                                        @click="currentPage--"
                                    >
                                        <ChevronLeft class="h-4 w-4" />
                                        <span class="sr-only">Anterior</span>
                                    </Button>
                                    
                                    <Button 
                                        v-for="page in totalPages" 
                                        :key="page" 
                                        size="sm" 
                                        class="h-8 w-8 p-0"
                                        :variant="currentPage === page ? 'default' : 'outline'"
                                        @click="currentPage = page"
                                    >
                                        {{ page }}
                                    </Button>
                                    
                                    <Button 
                                        variant="outline" 
                                        size="icon"
                                        class="h-8 w-8"
                                        :disabled="currentPage === totalPages" 
                                        @click="currentPage++"
                                    >
                                        <ChevronRight class="h-4 w-4" />
                                        <span class="sr-only">Siguiente</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CrmPanel>
                </div>
            </div>
        </div>

        <!-- Modal de Detalles del Pedido -->
        <div v-if="selectedOrder" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm" @click="closeOrderDetails">
            <div class="w-full max-w-2xl overflow-hidden rounded-xl border border-border bg-card shadow-lg animate-in fade-in zoom-in-95 duration-200" @click.stop>
                <!-- Header -->
                <div class="border-b border-border bg-muted/30 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <ShoppingBag class="h-5 w-5 text-indigo-600" />
                        <h3 class="text-lg font-semibold text-foreground">
                            Detalles del Pedido #{{ selectedOrder.id }}
                        </h3>
                    </div>
                    <span class="text-xs text-muted-foreground">
                        {{ new Date(selectedOrder.created_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }}
                    </span>
                </div>
                
                <!-- Body -->
                <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                    <!-- Grid: Cliente & Envío -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Cliente info -->
                        <div class="space-y-3 bg-muted/20 p-4 rounded-lg border border-border/50">
                            <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Información del Cliente</h4>
                            <div class="space-y-1.5 text-sm">
                                <div class="font-semibold text-foreground">{{ selectedOrder.customer?.name || 'Cliente sin nombre' }}</div>
                                <div class="text-muted-foreground font-mono">{{ selectedOrder.customer?.phone_number }}</div>
                            </div>
                        </div>

                        <!-- Envío info -->
                        <div class="space-y-3 bg-muted/20 p-4 rounded-lg border border-border/50">
                            <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Información de Envío</h4>
                            <div class="space-y-1.5 text-sm">
                                <div v-if="selectedOrder.district" class="space-y-1">
                                    <div class="font-semibold text-foreground flex items-center gap-1">
                                        <Truck class="h-4 w-4 text-indigo-500" />
                                        <span class="capitalize">{{ selectedOrder.shipping_method }}</span>
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{ selectedOrder.district }}
                                    </div>
                                    <div class="text-xs text-muted-foreground/80 break-words">
                                        {{ selectedOrder.full_address || 'Sin dirección detallada' }}
                                    </div>
                                </div>
                                <div v-else class="text-muted-foreground">No requiere envío (Retiro / Ninguno)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Detalle de Productos</h4>
                        <div class="border border-border rounded-lg overflow-hidden">
                            <table class="w-full text-sm text-left font-sans">
                                <thead class="bg-muted/40 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th class="px-4 py-2">Producto</th>
                                        <th class="px-4 py-2 text-center">Variante</th>
                                        <th class="px-4 py-2 text-center">Cantidad</th>
                                        <th class="px-4 py-2 text-right">Unitario</th>
                                        <th class="px-4 py-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="item in selectedOrder.items" :key="item.id" class="text-foreground">
                                        <td class="px-4 py-3 font-medium">{{ item.product.name }}</td>
                                        <td class="px-4 py-3 text-center text-muted-foreground">
                                            {{ [item.color, item.size].filter(Boolean).join(', ') || '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-center font-mono">{{ item.qty }}</td>
                                        <td class="px-4 py-3 text-right font-mono">S/ {{ parseFloat(item.unit_price).toFixed(2) }}</td>
                                        <td class="px-4 py-3 text-right font-mono font-medium">S/ {{ parseFloat(item.total).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payment Proof & Financials -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        <!-- Comprobante / Pago -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Método de Pago</h4>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold capitalize bg-indigo-50 dark:bg-indigo-950/30 text-indigo-700 dark:text-indigo-400 px-2.5 py-1 rounded border border-indigo-100 dark:border-indigo-900/30">
                                    {{ selectedOrder.payment_method }}
                                </span>
                            </div>
                            
                            <!-- Proof Image -->
                            <div v-if="selectedOrder.payment_proof_url" class="mt-3">
                                <div class="text-xs text-muted-foreground mb-1">Comprobante de pago:</div>
                                <a 
                                    :href="selectedOrder.payment_proof_url" 
                                    target="_blank" 
                                    class="inline-flex items-center gap-1.5 text-xs text-primary hover:underline cursor-pointer"
                                >
                                    <FileText class="h-3.5 w-3.5" />
                                    <span>Ver comprobante original</span>
                                </a>
                                <div class="mt-2 border border-border rounded-lg overflow-hidden max-w-[200px] bg-muted/20">
                                    <img 
                                        :src="selectedOrder.payment_proof_url" 
                                        alt="Comprobante" 
                                        class="max-h-[120px] w-auto mx-auto object-contain cursor-zoom-in" 
                                        @click="openUrl(selectedOrder.payment_proof_url)"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Financial details -->
                        <div class="space-y-2 bg-muted/10 p-4 rounded-lg border border-border/30">
                            <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">Resumen</h4>
                            <div class="space-y-1.5 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Subtotal:</span>
                                    <span class="font-mono text-foreground">S/ {{ parseFloat(selectedOrder.amount_subtotal).toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Envío:</span>
                                    <span class="font-mono text-foreground">S/ {{ parseFloat(selectedOrder.shipping_cost).toFixed(2) }}</span>
                                </div>
                                <div class="border-t border-border/50 my-1 pt-1.5 flex justify-between text-base font-bold">
                                    <span class="text-foreground">Total:</span>
                                    <span class="font-mono text-indigo-600 dark:text-indigo-400">S/ {{ parseFloat(selectedOrder.amount_total).toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="selectedOrder.notes" class="space-y-1.5 bg-yellow-50/50 dark:bg-yellow-950/10 border border-yellow-100 dark:border-yellow-900/30 p-3.5 rounded-lg">
                        <h4 class="text-xs font-bold text-yellow-800 dark:text-yellow-400 uppercase tracking-wider">Notas del Pedido</h4>
                        <p class="text-sm text-yellow-900 dark:text-yellow-300 break-words leading-relaxed whitespace-pre-line">{{ selectedOrder.notes }}</p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-border bg-muted/10 px-6 py-4">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs text-muted-foreground">Estado actual:</span>
                        <span 
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="{
                                'bg-yellow-50 text-yellow-700 border border-yellow-200 dark:bg-yellow-950/30 dark:text-yellow-400 dark:border-yellow-900/40': selectedOrder.status === 'pending',
                                'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-900/40': selectedOrder.status === 'paid',
                                'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/30 dark:text-indigo-400 dark:border-indigo-900/40': selectedOrder.status === 'shipped',
                                'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/40': selectedOrder.status === 'delivered',
                                'bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/30 dark:text-red-400 dark:border-red-900/40': selectedOrder.status === 'cancelled',
                            }"
                        >
                            {{ 
                                selectedOrder.status === 'pending' ? 'Pendiente' : 
                                selectedOrder.status === 'paid' ? 'Pagado' : 
                                selectedOrder.status === 'shipped' ? 'Enviado' : 
                                selectedOrder.status === 'delivered' ? 'Entregado' : 'Cancelado' 
                            }}
                        </span>
                    </div>
                    <Button variant="outline" @click="closeOrderDetails">
                        Cerrar
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
