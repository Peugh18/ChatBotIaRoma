<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { apiJson, ApiError } from '@/composables/useApi';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

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
                        <Button @click="openCreateModal">Nueva zona</Button>
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

            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                        <div v-if="loading" class="text-center py-12">
                            <p class="text-gray-500 dark:text-gray-400">Cargando...</p>
                        </div>
                        <div v-else-if="deliveryZones.length === 0" class="text-center py-12">
                            <p class="text-gray-500 dark:text-gray-400">No hay zonas de delivery configuradas</p>
                        </div>
                        <table v-else class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-0">Distrito</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Motorizado (S/)</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Shalom (S/)</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-0">
                                        <span class="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="zone in deliveryZones" :key="zone.id">
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-0">
                                        {{ zone.district }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        S/ {{ zone.cost_motorizado }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        S/ {{ zone.cost_shalom }}
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
                                        <button @click="openEditModal(zone)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-4">Editar</button>
                                        <button @click="deleteZone(zone.id)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Eliminar</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para crear/editar zona -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">
                            {{ editingZone ? 'Editar Zona' : 'Nueva Zona' }}
                        </h3>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label for="district" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Distrito</label>
                                <input
                                    type="text"
                                    id="district"
                                    v-model="form.district"
                                    list="districts-list"
                                    class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-700 sm:text-sm sm:leading-6"
                                    placeholder="Nombre del distrito"
                                />
                                <datalist id="districts-list">
                                    <option v-for="district in initialDistricts" :key="district" :value="district" />
                                </datalist>
                            </div>
                            <div>
                                <label for="cost-motorizado" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Costo Motorizado (S/)</label>
                                <input
                                    type="number"
                                    id="cost-motorizado"
                                    v-model="form.cost_motorizado"
                                    step="0.01"
                                    min="0"
                                    class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-700 sm:text-sm sm:leading-6"
                                />
                            </div>
                            <div>
                                <label for="cost-shalom" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Costo Shalom (S/)</label>
                                <input
                                    type="number"
                                    id="cost-shalom"
                                    v-model="form.cost_shalom"
                                    step="0.01"
                                    min="0"
                                    class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-700 sm:text-sm sm:leading-6"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button
                            type="button"
                            @click="saveZone"
                            class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto"
                        >
                            {{ editingZone ? 'Actualizar' : 'Crear' }}
                        </button>
                        <button
                            type="button"
                            @click="showCreateModal = false; editingZone = null"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 sm:mt-0 sm:w-auto"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
