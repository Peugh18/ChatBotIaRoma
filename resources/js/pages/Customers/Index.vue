<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiJson, ApiError } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { MessageSquare, Search, User, Edit, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clientes', href: '/customers' }];

interface ConversationState {
    id: number;
    requires_human: boolean;
    current_state: string | null;
}

interface Customer {
    id: number;
    phone_number: string;
    name: string | null;
    email: string | null;
    segment: string | null;
    notes: string | null;
    tags: string[] | null;
    last_seen_at: string | null;
    first_seen_at: string | null;
    orders_count: number;
    total_spent: string | number | null;
    conversation_state: ConversationState | null;
}

const customers = ref<Customer[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const search = ref('');
const editing = ref<Customer | null>(null);
const saving = ref(false);
const editForm = ref({
    name: '',
    email: '',
    segment: '',
    notes: '',
});

// Pagination State
const currentPage = ref(1);
const itemsPerPage = 10;

const filteredCustomers = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return customers.value;
    }
    return customers.value.filter(
        (c) =>
            c.phone_number.includes(q) ||
            (c.name?.toLowerCase().includes(q) ?? false) ||
            (c.email?.toLowerCase().includes(q) ?? false),
    );
});

// Calculate total pages
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredCustomers.value.length / itemsPerPage));
});

// Paginate filtered customers
const paginatedCustomers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredCustomers.value.slice(start, start + itemsPerPage);
});

// Reset page to 1 when search query changes
watch(search, () => {
    currentPage.value = 1;
});

const fetchCustomers = async () => {
    loading.value = true;
    error.value = null;
    try {
        customers.value = await apiJson<Customer[]>('/api/customers');
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar los clientes.';
        customers.value = [];
    } finally {
        loading.value = false;
    }
};

const openEdit = (customer: Customer) => {
    editing.value = customer;
    editForm.value = {
        name: customer.name ?? '',
        email: customer.email ?? '',
        segment: customer.segment ?? '',
        notes: customer.notes ?? '',
    };
};

const saveCustomer = async () => {
    if (!editing.value) return;
    saving.value = true;
    try {
        const response = await apiJson<{ data: Customer }>(`/api/customers/${editing.value.id}`, {
            method: 'PUT',
            body: JSON.stringify({
                name: editForm.value.name || null,
                email: editForm.value.email || null,
                segment: editForm.value.segment || null,
                notes: editForm.value.notes || null,
            }),
        });
        const updated = response.data;
        const idx = customers.value.findIndex((c) => c.id === updated.id);
        if (idx !== -1) {
            customers.value[idx] = { ...customers.value[idx], ...updated };
        }
        editing.value = null;
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al guardar el cliente';
        alert(msg);
    } finally {
        saving.value = false;
    }
};

const formatMoney = (value: string | number | null) => {
    const n = parseFloat(String(value ?? 0));
    return `S/ ${n.toFixed(2)}`;
};

const formatDate = (iso: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
};

const chatUrl = (phone: string) => `/chat?phone=${encodeURIComponent(phone)}`;

onMounted(fetchCustomers);
</script>

<template>
    <Head title="Clientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader title="Clientes" description="Contactos de WhatsApp, pedidos y acceso rápido al chat." />

            <div
                v-if="error"
                class="mb-6 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive animate-fade-in"
                role="alert"
            >
                {{ error }}
            </div>

            <!-- Search bar -->
            <div class="flex items-center justify-between gap-4 flex-wrap mb-4">
                <div class="relative w-full max-w-md">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Buscar por nombre, teléfono o email…"
                        class="pl-9"
                    />
                </div>
            </div>

            <CrmPanel no-padding>
                <div v-if="loading" class="py-16 text-center text-sm text-muted-foreground">Cargando clientes…</div>
                <div v-else-if="filteredCustomers.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                    No hay clientes que coincidan con la búsqueda
                </div>
                <div v-else>
                    <div class="overflow-x-auto">
                        <table class="crm-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Segmento</th>
                                    <th class="text-center" style="text-align: center;">Pedidos</th>
                                    <th class="text-center" style="text-align: center;">Total comprado</th>
                                    <th class="text-center" style="text-align: center;">Última actividad</th>
                                    <th class="text-center" style="text-align: center;">Estado</th>
                                    <th class="text-center" style="text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="customer in paginatedCustomers" :key="customer.id">
                                    <td>
                                        <div class="font-medium text-foreground">
                                            {{ customer.name || 'Sin nombre' }}
                                        </div>
                                        <div class="text-xs text-muted-foreground">{{ customer.phone_number }}</div>
                                    </td>
                                    <td class="text-sm text-muted-foreground capitalize">
                                        {{ customer.segment || 'lead' }}
                                    </td>
                                    <td class="text-center text-sm" style="text-align: center;">
                                        {{ customer.orders_count }}
                                    </td>
                                    <td class="text-center text-sm font-medium" style="text-align: center;">
                                        {{ formatMoney(customer.total_spent) }}
                                    </td>
                                    <td class="text-center text-sm text-muted-foreground" style="text-align: center;">
                                        {{ formatDate(customer.last_seen_at) }}
                                    </td>
                                    <td class="text-center" style="text-align: center;">
                                        <span
                                            v-if="customer.conversation_state?.requires_human"
                                            class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-400"
                                        >
                                            Asesor
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex rounded-full border border-border bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                        >
                                            Bot
                                        </span>
                                    </td>
                                    <td class="text-center" style="text-align: center;">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <Button 
                                                variant="ghost" 
                                                size="sm" 
                                                class="h-8 px-2.5 text-muted-foreground hover:text-foreground hover:bg-muted"
                                                as-child
                                            >
                                                <Link :href="chatUrl(customer.phone_number)">
                                                    <MessageSquare class="mr-1.5 h-3.5 w-3.5" />
                                                    <span>Chat</span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 px-2 text-primary hover:text-primary hover:bg-primary/10"
                                                @click="openEdit(customer)"
                                                title="Editar"
                                            >
                                                <Edit class="h-3.5 w-3.5" />
                                                <span class="sr-only">Editar</span>
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
                            Mostrando <span class="font-semibold">{{ Math.min(filteredCustomers.length, (currentPage - 1) * itemsPerPage + 1) }}</span> a <span class="font-semibold">{{ Math.min(filteredCustomers.length, currentPage * itemsPerPage) }}</span> de <span class="font-semibold">{{ filteredCustomers.length }}</span> clientes
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

        <!-- Modal para editar cliente -->
        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden rounded-xl border border-border bg-card shadow-lg animate-in fade-in zoom-in-95 duration-200" @click.stop>
                <div class="border-b border-border bg-muted/30 px-6 py-4 flex items-center gap-2">
                    <User class="h-5 w-5 text-primary" />
                    <h3 class="text-lg font-semibold text-foreground">
                        Editar cliente
                    </h3>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="text-sm font-medium text-muted-foreground bg-muted/50 px-3 py-2 rounded-lg border border-border/50">
                        Teléfono: <span class="text-foreground font-mono">{{ editing.phone_number }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="name">Nombre</Label>
                        <Input
                            id="name"
                            v-model="editForm.name"
                            placeholder="Nombre del cliente"
                        />
                    </div>
                    
                    <div class="space-y-1.5">
                        <Label for="email">Email</Label>
                        <Input
                            type="email"
                            id="email"
                            v-model="editForm.email"
                            placeholder="correo@ejemplo.com"
                        />
                    </div>
                    
                    <div class="space-y-1.5">
                        <Label for="segment">Segmento</Label>
                        <Input
                            id="segment"
                            v-model="editForm.segment"
                            placeholder="Ej: lead, cliente frecuente..."
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="notes">Notas</Label>
                        <textarea
                            id="notes"
                            v-model="editForm.notes"
                            rows="3"
                            placeholder="Notas o comentarios sobre el cliente..."
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>
                </div>
                
                <div class="flex items-center justify-end gap-3 border-t border-border bg-muted/10 px-6 py-4">
                    <Button variant="outline" :disabled="saving" @click="editing = null">
                        Cancelar
                    </Button>
                    <Button :disabled="saving" @click="saveCustomer">
                        {{ saving ? 'Guardando…' : 'Guardar' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

