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
import { 
    Plus, 
    Trash2, 
    FolderOpen, 
    AlertCircle, 
    Search, 
    ChevronLeft, 
    ChevronRight 
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';

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

// Search and Pagination State
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

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

// Filter categories based on search query
const filteredCategories = computed(() => {
    if (!searchQuery.value) return categories.value;
    return categories.value.filter((category) =>
        category.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Calculate total pages
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredCategories.value.length / itemsPerPage));
});

// Paginate filtered categories
const paginatedCategories = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredCategories.value.slice(start, start + itemsPerPage);
});

// Reset page to 1 when search query changes
watch(searchQuery, () => {
    currentPage.value = 1;
});

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
                class="mb-6 flex items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive animate-fade-in"
                role="alert"
            >
                <AlertCircle class="h-4 w-4" />
                <p>{{ error }}</p>
            </div>

            <!-- Search and Filter Bar -->
            <div class="mb-4 flex items-center justify-between gap-4 flex-wrap">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar categoría..." class="pl-9" />
                </div>
            </div>

            <CrmPanel no-padding class="overflow-hidden border shadow-sm">
                <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-muted-foreground">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                    <p class="mt-4 text-sm font-medium">Cargando categorías...</p>
                </div>
                
                <div v-else-if="filteredCategories.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 mb-4">
                        <FolderOpen class="h-8 w-8 text-primary" />
                    </div>
                    <h3 class="text-lg font-medium text-foreground">No se encontraron categorías</h3>
                    <p class="mt-1 text-sm text-muted-foreground max-w-sm">
                        Prueba ajustando tu búsqueda o crea una nueva categoría.
                    </p>
                    <Button variant="outline" class="mt-6 gap-2" @click="showCreateModal = true">
                        <Plus class="h-4 w-4" />
                        Crear categoría
                    </Button>
                </div>
                
                <div v-else>
                    <div class="overflow-x-auto">
                        <table class="crm-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Slug</th>
                                    <th class="text-right !text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="category in paginatedCategories" :key="category.id" class="group">
                                    <td class="font-medium text-foreground">
                                        <div class="flex items-center gap-2">
                                            <FolderOpen class="h-4 w-4 text-primary/70" />
                                            <span>{{ category.name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-muted-foreground">
                                        <code class="rounded bg-muted px-2 py-1 text-xs">{{ category.slug }}</code>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 px-2 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                @click="deleteCategory(category.id)"
                                                title="Eliminar categoría"
                                            >
                                                <Trash2 class="h-4 w-4" />
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
                            Mostrando <span class="font-semibold">{{ Math.min(filteredCategories.length, (currentPage - 1) * itemsPerPage + 1) }}</span> a <span class="font-semibold">{{ Math.min(filteredCategories.length, currentPage * itemsPerPage) }}</span> de <span class="font-semibold">{{ filteredCategories.length }}</span> categorías
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
                            placeholder="Ej. Vestidos, Conjuntos..."
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
