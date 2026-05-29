<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { apiJson, ApiError } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { MessageSquare, Search, User } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
                class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                role="alert"
            >
                {{ error }}
            </div>

            <div class="mb-4 relative max-w-md">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Buscar por nombre, teléfono o email…"
                    class="w-full rounded-lg border border-border bg-card py-2 pl-10 pr-3 text-sm text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                />
            </div>

            <CrmPanel no-padding>
                <div v-if="loading" class="py-16 text-center text-sm text-muted-foreground">Cargando clientes…</div>
                <div v-else-if="filteredCustomers.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                    No hay clientes que coincidan con la búsqueda
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Segmento</th>
                                <th class="text-center">Pedidos</th>
                                <th class="text-right">Total comprado</th>
                                <th>Última actividad</th>
                                <th class="text-center">Estado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="customer in filteredCustomers" :key="customer.id">
                                <td>
                                    <div class="font-medium text-foreground">
                                        {{ customer.name || 'Sin nombre' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">{{ customer.phone_number }}</div>
                                </td>
                                <td class="text-sm text-muted-foreground capitalize">
                                    {{ customer.segment || 'lead' }}
                                </td>
                                <td class="text-center text-sm">{{ customer.orders_count }}</td>
                                <td class="text-right text-sm font-medium">
                                    {{ formatMoney(customer.total_spent) }}
                                </td>
                                <td class="text-sm text-muted-foreground">{{ formatDate(customer.last_seen_at) }}</td>
                                <td class="text-center">
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
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="ghost" size="sm" as-child>
                                            <Link :href="chatUrl(customer.phone_number)" class="gap-1">
                                                <MessageSquare class="h-3.5 w-3.5" />
                                                Chat
                                            </Link>
                                        </Button>
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-primary hover:underline"
                                            @click="openEdit(customer)"
                                        >
                                            Editar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CrmPanel>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div
                    class="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-xl"
                    @click.stop
                >
                    <div class="mb-4 flex items-center gap-2">
                        <User class="h-5 w-5 text-primary" />
                        <h3 class="text-lg font-semibold text-foreground">Editar cliente</h3>
                    </div>
                    <p class="mb-4 text-sm text-muted-foreground">{{ editing.phone_number }}</p>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Nombre</label>
                            <input
                                v-model="editForm.name"
                                type="text"
                                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Email</label>
                            <input
                                v-model="editForm.email"
                                type="email"
                                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Segmento</label>
                            <input
                                v-model="editForm.segment"
                                type="text"
                                placeholder="lead, repeat_customer…"
                                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Notas</label>
                            <textarea
                                v-model="editForm.notes"
                                rows="3"
                                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button variant="outline" :disabled="saving" @click="editing = null">Cancelar</Button>
                        <Button :disabled="saving" @click="saveCustomer">{{ saving ? 'Guardando…' : 'Guardar' }}</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
