<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { apiJson, ApiError } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Categorías',
        href: '/categories',
    },
];

interface Category {
    id: number;
    name: string;
    slug: string;
}

const categories = ref<Category[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showCreateModal = ref(false);
const newCategoryName = ref('');

const fetchCategories = async () => {
    loading.value = true;
    error.value = null;
    try {
        categories.value = await apiJson<Category[]>('/api/categories');
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar las categorías.';
        categories.value = [];
    } finally {
        loading.value = false;
    }
};

const createCategory = async () => {
    if (!newCategoryName.value.trim()) return;

    try {
        await apiJson('/api/categories', {
            method: 'POST',
            body: JSON.stringify({ name: newCategoryName.value }),
        });
        newCategoryName.value = '';
        showCreateModal.value = false;
        await fetchCategories();
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al crear la categoría';
        alert(msg);
    }
};

const deleteCategory = async (id: number) => {
    if (!confirm('¿Estás seguro de eliminar esta categoría?')) return;
    try {
        await apiJson(`/api/categories/${id}`, { method: 'DELETE' });
        categories.value = categories.value.filter((c) => c.id !== id);
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al eliminar la categoría';
        alert(msg);
    }
};

onMounted(() => {
    fetchCategories();
});
</script>

<template>
    <Head title="Categorías" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader title="Categorías" description="Organiza el catálogo que el bot muestra al cliente.">
                <template #actions>
                    <Button class="gap-2" @click="showCreateModal = true">
                        <Plus class="h-4 w-4" />
                        Nueva categoría
                    </Button>
                </template>
            </PageHeader>

            <div
                v-if="error"
                class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                role="alert"
            >
                {{ error }}
            </div>

            <CrmPanel no-padding>
                <div v-if="loading" class="py-16 text-center text-sm text-muted-foreground">Cargando…</div>
                <div v-else-if="categories.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                    No hay categorías registradas
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Slug</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="category in categories" :key="category.id">
                                <td class="font-medium">{{ category.name }}</td>
                                <td class="text-muted-foreground">{{ category.slug }}</td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-destructive hover:underline"
                                        @click="deleteCategory(category.id)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CrmPanel>
        </div>

        <!-- Modal para crear categoría -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">Nueva Categoría</h3>
                        <div class="mt-4">
                            <label for="category-name" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Nombre</label>
                            <input
                                type="text"
                                id="category-name"
                                v-model="newCategoryName"
                                @keyup.enter="createCategory"
                                class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-700 sm:text-sm sm:leading-6"
                                placeholder="Nombre de la categoría"
                            />
                        </div>
                    </div>
                    <div class="px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button
                            type="button"
                            @click="createCategory"
                            class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto"
                        >
                            Crear
                        </button>
                        <button
                            type="button"
                            @click="showCreateModal = false; newCategoryName = ''"
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
