<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { apiJson, ApiError } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Plus, Trash2, FolderOpen, AlertCircle } from 'lucide-vue-next';
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
const isCreating = ref(false);

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
    if (!newCategoryName.value.trim() || isCreating.value) return;

    isCreating.value = true;
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
    } finally {
        isCreating.value = false;
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
                class="mb-6 flex items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                role="alert"
            >
                <AlertCircle class="h-4 w-4" />
                <p>{{ error }}</p>
            </div>

            <CrmPanel no-padding class="overflow-hidden border shadow-sm">
                <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-muted-foreground">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                    <p class="mt-4 text-sm font-medium">Cargando categorías...</p>
                </div>
                
                <div v-else-if="categories.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 mb-4">
                        <FolderOpen class="h-8 w-8 text-primary" />
                    </div>
                    <h3 class="text-lg font-medium text-foreground">No hay categorías registradas</h3>
                    <p class="mt-1 text-sm text-muted-foreground max-w-sm">
                        Crea tu primera categoría para empezar a organizar los productos de tu catálogo.
                    </p>
                    <Button variant="outline" class="mt-6 gap-2" @click="showCreateModal = true">
                        <Plus class="h-4 w-4" />
                        Crear categoría
                    </Button>
                </div>
                
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-muted/50 text-muted-foreground text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4 font-medium">Nombre</th>
                                <th class="px-6 py-4 font-medium">Slug</th>
                                <th class="px-6 py-4 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-background">
                            <tr v-for="category in categories" :key="category.id" class="transition-colors hover:bg-muted/30 group">
                                <td class="px-6 py-4 font-medium text-foreground">
                                    {{ category.name }}
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">
                                    <code class="rounded bg-muted px-2 py-1 text-xs">{{ category.slug }}</code>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive opacity-0 group-hover:opacity-100 transition-opacity focus:opacity-100"
                                        @click="deleteCategory(category.id)"
                                        title="Eliminar categoría"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CrmPanel>
        </div>

        <!-- Modal para crear categoría -->
        <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Nueva Categoría</DialogTitle>
                    <DialogDescription>
                        Ingresa el nombre de la nueva categoría para tu catálogo.
                    </DialogDescription>
                </DialogHeader>
                
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="category-name">Nombre</Label>
                        <Input
                            id="category-name"
                            v-model="newCategoryName"
                            placeholder="Ej. Pizzas, Bebidas..."
                            @keyup.enter="createCategory"
                            :disabled="isCreating"
                            autocomplete="off"
                        />
                    </div>
                </div>
                
                <DialogFooter>
                    <Button variant="outline" @click="showCreateModal = false" :disabled="isCreating">
                        Cancelar
                    </Button>
                    <Button @click="createCategory" :disabled="!newCategoryName.trim() || isCreating">
                        {{ isCreating ? 'Creando...' : 'Crear categoría' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
