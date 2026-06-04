<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { apiJson, ApiError } from '@/composables/useApi';
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import { 
    MapPin, 
    Plus, 
    Search, 
    Edit, 
    Trash2, 
    ChevronLeft, 
    ChevronRight, 
    CheckCircle2, 
    DollarSign 
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sedes Shalom', href: '/sedes-shalom' }];

interface SedeShalom {
    id: number;
    nombre: string;
    ciudad: string | null;
    region: string;
    costo: number;
    activo: boolean;
}

const sedes = ref<SedeShalom[]>([]);
const loading = ref(true);
const showModal = ref(false);
const editing = ref<SedeShalom | null>(null);
const form = ref({
    nombre: '',
    ciudad: '',
    region: 'provincia',
    costo: 12,
    activo: true,
});

// Search and Pagination State
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const fetchSedes = async () => {
    loading.value = true;
    try {
        sedes.value = await apiJson<SedeShalom[]>('/api/sedes-shalom');
    } catch {
        sedes.value = [];
    } finally {
        loading.value = false;
    }
};

// Filter sedes based on search query
const filteredSedes = computed(() => {
    if (!searchQuery.value) return sedes.value;
    return sedes.value.filter((sede) =>
        sede.nombre.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        (sede.ciudad && sede.ciudad.toLowerCase().includes(searchQuery.value.toLowerCase()))
    );
});

// Calculate total pages
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredSedes.value.length / itemsPerPage));
});

// Paginate filtered sedes
const paginatedSedes = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredSedes.value.slice(start, start + itemsPerPage);
});

// Reset page to 1 when search query changes
watch(searchQuery, () => {
    currentPage.value = 1;
});

const openCreate = () => {
    editing.value = null;
    form.value = { nombre: '', ciudad: '', region: 'provincia', costo: 12, activo: true };
    showModal.value = true;
};

const openEdit = (sede: SedeShalom) => {
    editing.value = sede;
    form.value = {
        nombre: sede.nombre,
        ciudad: sede.ciudad ?? '',
        region: sede.region,
        costo: Number(sede.costo),
        activo: sede.activo,
    };
    showModal.value = true;
};

const save = async () => {
    try {
        const url = editing.value ? `/api/sedes-shalom/${editing.value.id}` : '/api/sedes-shalom';
        await apiJson(url, {
            method: editing.value ? 'PUT' : 'POST',
            body: JSON.stringify(form.value),
        });
        showModal.value = false;
        await fetchSedes();
    } catch (e) {
        alert(e instanceof ApiError ? e.message : 'Error al guardar');
    }
};

const remove = async (id: number) => {
    if (!confirm('¿Eliminar esta sede?')) return;
    await apiJson(`/api/sedes-shalom/${id}`, { method: 'DELETE' });
    sedes.value = sedes.value.filter((s) => s.id !== id);
};

onMounted(fetchSedes);
</script>

<template>
    <Head title="Sedes Shalom" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page space-y-6">
            <PageHeader
                title="Sedes Shalom"
                description="El bot muestra estas opciones cuando el cliente elige envío por Shalom a provincia."
            >
                <template #actions>
                    <Button @click="openCreate"><Plus class="mr-2 h-4 w-4" /> Nueva sede</Button>
                </template>
            </PageHeader>

            <!-- Search and Filter Bar -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar sede o ciudad..." class="pl-9" />
                </div>
            </div>

            <CrmPanel noPadding>
                <div v-if="loading" class="text-center py-20">
                    <p class="text-sm text-muted-foreground">Cargando sedes...</p>
                </div>
                
                <div v-else-if="filteredSedes.length === 0" class="text-center py-20">
                    <MapPin class="mx-auto mb-2 h-8 w-8 opacity-30 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">No se encontraron sedes registradas</p>
                </div>

                <div v-else>
                    <div class="overflow-x-auto">
                        <table class="crm-table">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th>Ciudad</th>
                                    <th>Región</th>
                                    <th>Costo</th>
                                    <th>Activa</th>
                                    <th class="text-right !text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="sede in paginatedSedes" :key="sede.id">
                                    <td class="font-medium text-foreground">
                                        <div class="flex items-center gap-2">
                                            <MapPin class="h-4 w-4 text-primary/75" />
                                            <span>{{ sede.nombre }}</span>
                                        </div>
                                    </td>
                                    <td class="text-muted-foreground">{{ sede.ciudad || '—' }}</td>
                                    <td class="capitalize text-muted-foreground">{{ sede.region }}</td>
                                    <td class="font-semibold text-emerald-600 dark:text-emerald-400">
                                        S/ {{ Number(sede.costo).toFixed(2) }}
                                    </td>
                                    <td>
                                        <span 
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="sede.activo 
                                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/40' 
                                                : 'bg-muted text-muted-foreground border border-border'"
                                        >
                                            {{ sede.activo ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <Button 
                                                variant="ghost" 
                                                size="sm" 
                                                class="h-8 px-2 text-primary hover:text-primary hover:bg-primary/10" 
                                                @click="openEdit(sede)"
                                            >
                                                <Edit class="h-3.5 w-3.5" />
                                                <span class="sr-only">Editar</span>
                                            </Button>
                                            <Button 
                                                variant="ghost" 
                                                size="sm" 
                                                class="h-8 px-2 text-destructive hover:text-destructive hover:bg-destructive/10" 
                                                @click="remove(sede.id)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                <span class="sr-only">Eliminar</span>
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
                            Mostrando <span class="font-semibold">{{ Math.min(filteredSedes.length, (currentPage - 1) * itemsPerPage + 1) }}</span> a <span class="font-semibold">{{ Math.min(filteredSedes.length, currentPage * itemsPerPage) }}</span> de <span class="font-semibold">{{ filteredSedes.length }}</span> sedes
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

        <!-- Modal para crear/editar sede -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm" @click.self="showModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-xl border border-border bg-card shadow-lg animate-in fade-in zoom-in-95 duration-200">
                <div class="border-b border-border bg-muted/30 px-6 py-4">
                    <h3 class="text-lg font-semibold text-foreground">
                        {{ editing ? 'Editar sede' : 'Nueva sede' }}
                    </h3>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label>Nombre sede</Label>
                        <Input v-model="form.nombre" placeholder="Ej. Shalom Cusco" />
                    </div>
                    
                    <div class="space-y-1.5">
                        <Label>Ciudad</Label>
                        <Input v-model="form.ciudad" placeholder="Ej. Cusco" />
                    </div>
                    
                    <div class="space-y-1.5">
                        <Label>Región</Label>
                        <select
                            v-model="form.region"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="lima">Lima</option>
                            <option value="provincia">Provincia</option>
                        </select>
                    </div>
                    
                    <div class="space-y-1.5">
                        <Label>Costo envío (S/)</Label>
                        <Input v-model.number="form.costo" type="number" min="0" step="0.5" />
                    </div>
                    
                    <label class="flex items-center gap-2 text-sm font-medium text-foreground cursor-pointer select-none">
                        <input 
                            v-model="form.activo" 
                            type="checkbox" 
                            class="rounded border-input text-primary focus:ring-primary h-4 w-4 bg-background" 
                        />
                        <span>Activa en el bot</span>
                    </label>
                </div>
                
                <div class="flex items-center justify-end gap-3 border-t border-border bg-muted/10 px-6 py-4">
                    <Button variant="outline" @click="showModal = false">Cancelar</Button>
                    <Button @click="save">Guardar</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
