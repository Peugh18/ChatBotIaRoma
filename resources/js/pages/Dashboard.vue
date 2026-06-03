<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import StatCard from '@/components/crm/StatCard.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    Package,
    MessageSquare,
    DollarSign,
    Percent,
    AlertCircle,
    ShoppingBag,
    ArrowRight,
    Building2,
    Bot,
    Users,
    Receipt,
} from 'lucide-vue-next';
import { useDashboardStats } from '@/composables/useDashboardStats';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const { stats, loading, error } = useDashboardStats();

const quickActions = [
    {
        title: 'Chat en vivo',
        description: 'Conversaciones y handoff humano',
        href: '/chat',
        icon: MessageSquare,
    },
    {
        title: 'Pipeline de ventas',
        description: 'Pedidos y estados de envío',
        href: '/pipeline',
        icon: ShoppingBag,
    },
    {
        title: 'Clientes',
        description: 'Contactos y historial de compras',
        href: '/customers',
        icon: Users,
    },
    {
        title: 'Inventario',
        description: 'Productos y variantes',
        href: '/products',
        icon: Package,
    },
    {
        title: 'Personalidad del bot',
        description: 'Tono y textos de apoyo',
        href: '/bot-settings',
        icon: Bot,
    },
    {
        title: 'Empresa y voz',
        description: 'Yape, CTA y horarios',
        href: '/company-settings',
        icon: Building2,
    },
];

const statusBadge = (active: boolean) =>
    active
        ? 'inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-400'
        : 'inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader
                title="Dashboard"
                description="Rendimiento de ventas, estado del bot de IA y conversaciones que requieren atención."
            />

            <div v-if="loading" class="py-20 text-center text-sm text-muted-foreground">Cargando estadísticas…</div>

            <div
                v-else-if="error"
                class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                role="alert"
            >
                {{ error }}
            </div>

            <div v-else class="space-y-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total ventas" :value="`S/ ${stats.total_sales.toFixed(2)}`" :icon="DollarSign" tone="primary" />
                    <StatCard
                        label="Ticket promedio"
                        :value="`S/ ${stats.average_ticket.toFixed(2)}`"
                        :icon="ShoppingBag"
                        tone="success"
                    />
                    <StatCard
                        label="Requiere asesor"
                        :value="stats.open_conversations"
                        :icon="AlertCircle"
                        :tone="stats.open_conversations > 0 ? 'warning' : 'default'"
                    />
                    <StatCard label="Tasa de cierre" :value="`${stats.conversion_rate}%`" :icon="Percent" />
                </div>

                <div>
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Accesos rápidos</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <Link
                            v-for="action in quickActions"
                            :key="action.href"
                            :href="action.href"
                            class="group flex items-start gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:border-primary/40 hover:shadow-md"
                        >
                            <div
                                class="rounded-lg bg-primary/10 p-2.5 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
                            >
                                <component :is="action.icon" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold text-foreground">{{ action.title }}</h3>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ action.description }}</p>
                            </div>
                        </Link>
                    </div>
                </div>

                <CrmPanel
                    v-if="stats.payment_validation_count > 0"
                    class="border-amber-200/60 dark:border-amber-900/40"
                    no-padding
                >
                    <template #header>
                        <div class="flex w-full items-center justify-between px-5 py-4">
                            <div class="flex items-center gap-2">
                                <Receipt class="h-4 w-4 text-amber-600" />
                                <h3 class="text-sm font-semibold text-foreground">
                                    Pagos por validar ({{ stats.payment_validation_count }})
                                </h3>
                            </div>
                            <Button variant="ghost" size="sm" as-child>
                                <Link href="/chat" class="gap-1 text-primary">Ir al chat</Link>
                            </Button>
                        </div>
                    </template>
                    <div class="divide-y divide-border">
                        <div
                            v-for="item in stats.payment_validation_queue"
                            :key="item.phone_number"
                            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ item.customer_name || item.phone_number }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Pedido #{{ item.order_id ?? '—' }}
                                    <span v-if="item.order_total != null"> · S/ {{ item.order_total.toFixed(0) }}</span>
                                </p>
                            </div>
                            <Button size="sm" variant="outline" as-child>
                                <Link :href="`/chat?phone=${encodeURIComponent(item.phone_number)}`">
                                    Validar en chat
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CrmPanel>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <CrmPanel class="lg:col-span-2" no-padding>
                        <template #header>
                            <div class="flex w-full items-center justify-between px-5 py-4">
                                <h3 class="text-sm font-semibold text-foreground">Últimos pedidos</h3>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link href="/pipeline" class="gap-1 text-primary">
                                        Ver pipeline
                                        <ArrowRight class="h-3.5 w-3.5" />
                                    </Link>
                                </Button>
                            </div>
                        </template>
                        <div class="overflow-x-auto">
                            <table class="crm-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Zona</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="order in stats.recent_orders" :key="order.id">
                                        <td class="font-semibold">#{{ order.id }}</td>
                                        <td>
                                            <div>{{ order.customer?.name || 'S/N' }}</div>
                                            <div class="text-xs text-muted-foreground">{{ order.customer?.phone_number }}</div>
                                        </td>
                                        <td class="text-muted-foreground">
                                            {{ order.district || '—' }}
                                            <span v-if="order.shipping_method !== 'none'" class="text-xs">
                                                ({{ order.shipping_method }})
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase"
                                                :class="{
                                                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-400':
                                                        order.status === 'delivered',
                                                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-400':
                                                        order.status === 'pending',
                                                    'border-primary/20 bg-primary/10 text-primary': !['delivered', 'pending'].includes(
                                                        order.status
                                                    ),
                                                }"
                                            >
                                                {{ order.status }}
                                            </span>
                                        </td>
                                        <td class="text-right font-semibold">
                                            S/ {{ parseFloat(order.amount_total).toFixed(2) }}
                                        </td>
                                    </tr>
                                    <tr v-if="stats.recent_orders.length === 0">
                                        <td colspan="5" class="py-10 text-center text-muted-foreground">No hay pedidos registrados</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CrmPanel>

                    <CrmPanel title="Integraciones" description="Estado de servicios conectados">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-border pb-3">
                                <div>
                                    <p class="text-sm font-medium text-foreground">WhatsApp Roma API</p>
                                    <p class="text-xs text-muted-foreground">Webhook de entrada</p>
                                </div>
                                <span :class="statusBadge(true)">Activo</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-border pb-3">
                                <div>
                                    <p class="text-sm font-medium text-foreground">Groq (IA)</p>
                                    <p class="text-xs text-muted-foreground">Fallback y visión</p>
                                </div>
                                <span :class="statusBadge(true)">Conectado</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-foreground">Pusher</p>
                                    <p class="text-xs text-muted-foreground">Tiempo real en chat</p>
                                </div>
                                <span :class="statusBadge(true)">Activo</span>
                            </div>
                        </div>
                    </CrmPanel>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
