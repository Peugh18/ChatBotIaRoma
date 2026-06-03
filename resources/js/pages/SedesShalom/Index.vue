<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { apiJson, ApiError } from '@/composables/useApi';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { MapPin, Plus } from 'lucide-vue-next';

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

            <div v-if="loading" class="text-muted-foreground">Cargando...</div>

            <div v-else class="overflow-hidden rounded-lg border">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Sede</th>
                            <th class="px-4 py-3 text-left font-medium">Ciudad</th>
                            <th class="px-4 py-3 text-left font-medium">Región</th>
                            <th class="px-4 py-3 text-left font-medium">Costo</th>
                            <th class="px-4 py-3 text-left font-medium">Activa</th>
                            <th class="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <tr v-for="sede in sedes" :key="sede.id">
                            <td class="px-4 py-3 font-medium">{{ sede.nombre }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ sede.ciudad || '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ sede.region }}</td>
                            <td class="px-4 py-3">S/ {{ Number(sede.costo).toFixed(0) }}</td>
                            <td class="px-4 py-3">{{ sede.activo ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Button variant="outline" size="sm" @click="openEdit(sede)">Editar</Button>
                                <Button variant="ghost" size="sm" @click="remove(sede.id)">Eliminar</Button>
                            </td>
                        </tr>
                        <tr v-if="sedes.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                <MapPin class="mx-auto mb-2 h-8 w-8 opacity-40" />
                                Aún no hay sedes. Créalas para que aparezcan en el bot.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showModal = false"
            >
                <div class="w-full max-w-md rounded-lg border bg-card p-6 shadow-lg">
                    <h3 class="text-lg font-semibold">{{ editing ? 'Editar sede' : 'Nueva sede' }}</h3>
                    <div class="mt-4 space-y-3">
                        <div class="space-y-1">
                            <Label>Nombre sede</Label>
                            <Input v-model="form.nombre" placeholder="Ej. Shalom Cusco" />
                        </div>
                        <div class="space-y-1">
                            <Label>Ciudad</Label>
                            <Input v-model="form.ciudad" />
                        </div>
                        <div class="space-y-1">
                            <Label>Región</Label>
                            <select
                                v-model="form.region"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="lima">Lima</option>
                                <option value="provincia">Provincia</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <Label>Costo envío (S/)</Label>
                            <Input v-model.number="form.costo" type="number" min="0" step="0.5" />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.activo" type="checkbox" class="rounded" />
                            Activa en el bot
                        </label>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button variant="outline" @click="showModal = false">Cancelar</Button>
                        <Button @click="save">Guardar</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
