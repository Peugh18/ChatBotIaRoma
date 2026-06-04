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
    Search, 
    Plus, 
    MapPin, 
    TrendingUp, 
    Truck, 
    Edit, 
    Trash2, 
    ChevronLeft, 
    ChevronRight, 
    Sparkles 
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tarifas de Delivery',
        href: '/delivery-zones',
    },
];

interface DeliveryZone {
    id: number;
    district: string;
    cost_motorizado: number;
    cost_shalom: number;
}

const deliveryZones = ref<DeliveryZone[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showCreateModal = ref(false);
const editingZone = ref<DeliveryZone | null>(null);
const form = ref({
    district: '',
    cost_motorizado: 0,
    cost_shalom: 0,
});

// Search and Pagination State
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const initialDistricts = [
    'Cercado de Lima', 'Breña', 'Jesús María', 'La Victoria', 'Lince',
    'Magdalena del Mar', 'Miraflores', 'Pueblo Libre', 'Rímac', 'San Borja',
    'San Isidro', 'San Miguel', 'Santiago de Surco', 'Surquillo', 'Carabayllo',
    'Comas', 'Independencia', 'Los Olivos', 'Puente Piedra', 'San Martín de Porres',
    'Santa Rosa', 'Ancón', 'Ate', 'El Agustino', 'Lurigancho-Chosica',
    'San Juan de Lurigancho', 'Santa Anita', 'Chaclacayo', 'Cieneguilla'
];

const fetchDeliveryZones = async () => {
    loading.value = true;
    error.value = null;
    try {
        deliveryZones.value = await apiJson<DeliveryZone[]>('/api/delivery-zones');
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar las zonas.';
        deliveryZones.value = [];
    } finally {
        loading.value = false;
    }
};

// Filter zones based on search query
const filteredZones = computed(() => {
    if (!searchQuery.value) return deliveryZones.value;
    return deliveryZones.value.filter((zone) =>
        zone.district.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Calculate total pages
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredZones.value.length / itemsPerPage));
});

// Paginate filtered zones
const paginatedZones = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredZones.value.slice(start, start + itemsPerPage);
});

// Reset page to 1 when search query changes
watch(searchQuery, () => {
    currentPage.value = 1;
});

const openCreateModal = () => {
    editingZone.value = null;
    form.value = {
        district: '',
        cost_motorizado: 0,
        cost_shalom: 0,
    };
    showCreateModal.value = true;
};

const openEditModal = (zone: DeliveryZone) => {
    editingZone.value = zone;
    form.value = {
        district: zone.district,
        cost_motorizado: zone.cost_motorizado,
        cost_shalom: zone.cost_shalom,
    };
    showCreateModal.value = true;
};

const saveZone = async () => {
    try {
        const url = editingZone.value
            ? `/api/delivery-zones/${editingZone.value.id}`
            : '/api/delivery-zones';
        const method = editingZone.value ? 'PUT' : 'POST';
        await apiJson(url, {
            method,
            body: JSON.stringify(form.value),
        });
        showCreateModal.value = false;
        await fetchDeliveryZones();
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al guardar la zona';
        alert(msg);
    }
};

const deleteZone = async (id: number) => {
    if (!confirm('¿Estás seguro de eliminar esta zona de delivery?')) return;
    try {
        await apiJson(`/api/delivery-zones/${id}`, { method: 'DELETE' });
        deliveryZones.value = deliveryZones.value.filter((z) => z.id !== id);
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al eliminar la zona';
        alert(msg);
    }
};

const importDefaultZones = async () => {
    if (!confirm('¿Importar las zonas de delivery por defecto?')) return;

    try {
        for (const district of initialDistricts) {
            await apiJson('/api/delivery-zones', {
                method: 'POST',
                body: JSON.stringify({
                    district,
                    cost_motorizado: 12,
                    cost_shalom: 15,
                }),
            });
        }
        await fetchDeliveryZones();
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al importar zonas';
        alert(msg);
    }
};

onMounted(() => {
    fetchDeliveryZones();
});
</script>

<template>
    <Head title="Tarifas de Delivery" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader
                title="Zonas de delivery"
                description="Costos por distrito para motorizado y Shalom (usados en el flujo de ventas)."
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button variant="secondary" @click="importDefaultZones">Importar por defecto</Button>
                        <Button @click="openCreateModal">
                            <Plus class="mr-1.5 h-4 w-4" />
                            Nueva zona
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <div
                v-if="error"
                class="mb-6 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive animate-fade-in"
                role="alert"
            >
                {{ error }}
            </div>

            <!-- Search and Table Container -->
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="relative w-full max-w-xs">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="searchQuery" placeholder="Buscar distrito..." class="pl-9" />
                    </div>
                </div>

                <CrmPanel noPadding>
                    <div v-if="loading" class="text-center py-20">
                        <p class="text-sm text-muted-foreground">Cargando tarifas de delivery...</p>
                    </div>
                    
                    <div v-else-if="filteredZones.length === 0" class="text-center py-20">
                        <p class="text-sm text-muted-foreground">No se encontraron zonas de delivery</p>
                    </div>

                    <div v-else>
                        <div class="overflow-x-auto">
                            <table class="crm-table">
                                <thead>
                                    <tr>
                                        <th>Distrito</th>
                                        <th>Costo Motorizado</th>
                                        <th>Costo Shalom</th>
                                        <th class="text-right !text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="zone in paginatedZones" :key="zone.id">
                                        <td class="font-medium text-foreground">
                                            <div class="flex items-center gap-2">
                                                <MapPin class="h-4 w-4 text-primary" />
                                                <span>{{ zone.district }}</span>
                                            </div>
                                        </td>
                                        <td class="text-muted-foreground">
                                            <div class="flex items-center gap-1.5">
                                                <Truck class="h-4 w-4 text-muted-foreground/75" />
                                                <span>S/ {{ Number(zone.cost_motorizado).toFixed(2) }}</span>
                                            </div>
                                        </td>
                                        <td class="text-muted-foreground">
                                            <div class="flex items-center gap-1.5">
                                                <TrendingUp class="h-4 w-4 text-muted-foreground/75" />
                                                <span>S/ {{ Number(zone.cost_shalom).toFixed(2) }}</span>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-1.5">
                                                <Button 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    class="h-8 px-2 text-primary hover:text-primary hover:bg-primary/10" 
                                                    @click="openEditModal(zone)"
                                                >
                                                    <Edit class="h-3.5 w-3.5" />
                                                    <span class="sr-only">Editar</span>
                                                </Button>
                                                <Button 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    class="h-8 px-2 text-destructive hover:text-destructive hover:bg-destructive/10" 
                                                    @click="deleteZone(zone.id)"
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
                                Mostrando <span class="font-semibold">{{ Math.min(filteredZones.length, (currentPage - 1) * itemsPerPage + 1) }}</span> a <span class="font-semibold">{{ Math.min(filteredZones.length, currentPage * itemsPerPage) }}</span> de <span class="font-semibold">{{ filteredZones.length }}</span> distritos
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

        <!-- Modal para crear/editar zona -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden rounded-xl border border-border bg-card shadow-lg animate-in fade-in zoom-in-95 duration-200">
                <div class="border-b border-border bg-muted/30 px-6 py-4">
                    <h3 class="text-lg font-semibold text-foreground">
                        {{ editingZone ? 'Editar zona de delivery' : 'Nueva zona de delivery' }}
                    </h3>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="district">Distrito</Label>
                        <Input
                            id="district"
                            v-model="form.district"
                            list="districts-list"
                            placeholder="Ej: Miraflores"
                        />
                        <datalist id="districts-list">
                            <option v-for="district in initialDistricts" :key="district" :value="district" />
                        </datalist>
                    </div>
                    
                    <div class="space-y-1.5">
                        <Label for="cost-motorizado">Costo Motorizado (S/)</Label>
                        <Input
                            type="number"
                            id="cost-motorizado"
                            v-model="form.cost_motorizado"
                            step="0.01"
                            min="0"
                        />
                    </div>
                    
                    <div class="space-y-1.5">
                        <Label for="cost-shalom">Costo Shalom (S/)</Label>
                        <Input
                            type="number"
                            id="cost-shalom"
                            v-model="form.cost_shalom"
                            step="0.01"
                            min="0"
                        />
                    </div>
                </div>
                
                <div class="flex items-center justify-end gap-3 border-t border-border bg-muted/10 px-6 py-4">
                    <Button variant="outline" @click="showCreateModal = false; editingZone = null">
                        Cancelar
                    </Button>
                    <Button @click="saveZone">
                        {{ editingZone ? 'Actualizar' : 'Crear' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
