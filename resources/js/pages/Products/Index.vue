<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Productos', href: '/products' }];

interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number | string | null;
    discount: number | string | null;
    category_id: number | null;
    tags_ia: string[] | null;
    category: { id: number; name: string } | null;
    variants: ProductVariant[];
}

interface ProductVariant {
    id: number;
    color: string;
    image_url: string | null;
    sizes_stock: Record<string, number>;
}

const products = ref<Product[]>([]);
const loading = ref(true);

const fetchProducts = async () => {
    try {
        const response = await fetch('/api/products', { headers: { Accept: 'application/json' } });
        products.value = await response.json();
    } catch (error) {
        console.error('Error fetching products:', error);
    } finally {
        loading.value = false;
    }
};

const getCsrfToken = (): string => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const formatPrice = (value: number | string | null): string => {
    if (value === null || value === undefined) return '—';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num) || num <= 0) return 'Sin precio';
    return `S/ ${num.toFixed(2)}`;
};

const deleteProduct = async (id: number) => {
    if (!confirm('¿Eliminar este producto?')) return;
    try {
        const response = await fetch(`/api/products/${id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Error');
        products.value = products.value.filter((p) => p.id !== id);
    } catch (error) {
        console.error('Error deleting product:', error);
        alert('Error al eliminar el producto');
    }
};

onMounted(() => {
    fetchProducts();
});
</script>

<template>
    <Head title="Productos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader title="Productos" description="Inventario, variantes por color y precios del catálogo.">
                <template #actions>
                    <Button as-child>
                        <Link href="/products/create" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Nuevo producto
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <CrmPanel no-padding>
                <div v-if="loading" class="py-16 text-center text-sm text-muted-foreground">Cargando productos…</div>
                <div v-else-if="products.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                    No hay productos registrados. Crea el primero para el bot.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Variantes</th>
                                <th>Precio</th>
                                <th>Descuento</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="product in products" :key="product.id">
                                <td class="font-medium">{{ product.name }}</td>
                                <td class="text-muted-foreground">{{ product.category?.name || 'Sin categoría' }}</td>
                                <td class="text-muted-foreground">{{ product.variants.length }}</td>
                                <td
                                    :class="
                                        !product.price || Number(product.price) <= 0
                                            ? 'font-medium text-destructive'
                                            : 'font-medium text-emerald-600 dark:text-emerald-400'
                                    "
                                >
                                    {{ formatPrice(product.price) }}
                                </td>
                                <td class="text-muted-foreground">
                                    {{
                                        product.discount && Number(product.discount) > 0
                                            ? `S/ ${Number(product.discount).toFixed(2)}`
                                            : '—'
                                    }}
                                </td>
                                <td class="text-right">
                                    <Link
                                        :href="`/products/${product.id}/edit`"
                                        class="mr-3 text-sm font-medium text-primary hover:underline"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-destructive hover:underline"
                                        @click="deleteProduct(product.id)"
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
    </AppLayout>
</template>
