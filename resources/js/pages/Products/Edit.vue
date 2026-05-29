<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps<{
    product: {
        id: number;
        name: string;
        description: string | null;
        price: number | null;
        discount: number | null;
        category_id: number | null;
        tags_ia: string[] | null;
        category: { id: number; name: string } | null;
        variants: {
            id: number;
            color: string;
            image_url: string | null;
            sizes_stock: Record<string, number>;
        }[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Productos',
        href: '/products',
    },
    {
        title: 'Editar Producto',
        href: `/products/${props.product.id}/edit`,
    },
];

interface Category {
    id: number;
    name: string;
}

interface ProductVariant {
    id?: number;
    color: string;
    image_url: string;
    image_path?: string | null;
    public_image_url?: string | null;
    sizes_stock: Record<string, number>;
    pendingFile?: File | null;
    uploading?: boolean;
}

const form = ref({
    name: props.product.name,
    description: props.product.description || '',
    price: props.product.price,
    discount: props.product.discount,
    category_id: props.product.category_id,
    tags_ia: props.product.tags_ia || [],
    variants: props.product.variants.map(v => ({
        id: v.id,
        color: v.color,
        image_url: v.image_url || '',
        image_path: (v as any).image_path || null,
        public_image_url: (v as any).public_image_url || v.image_url || null,
        sizes_stock: v.sizes_stock,
        pendingFile: null,
        uploading: false,
    })),
});

const categories = ref<Category[]>([]);
const loading = ref(false);
const newTag = ref('');

const fetchCategories = async () => {
    try {
        const response = await fetch('/api/categories', {
            headers: {
                'Accept': 'application/json',
            },
        });
        categories.value = await response.json();
    } catch (error) {
        console.error('Error fetching categories:', error);
    }
};

const variantPreviewUrl = (variant: ProductVariant): string | null => {
    if (variant.public_image_url) return variant.public_image_url;
    if (variant.image_path) return `/storage/${variant.image_path}`;
    return variant.image_url || null;
};

const onVariantPhotoSelected = (variant: ProductVariant, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    variant.pendingFile = file;
    if (variant.id) {
        uploadVariantPhoto(variant);
    }
};

const uploadVariantPhoto = async (variant: ProductVariant) => {
    if (!variant.id || !variant.pendingFile) return;
    variant.uploading = true;
    try {
        const body = new FormData();
        body.append('photo', variant.pendingFile);
        const response = await fetch(`/api/product-variants/${variant.id}/photo`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body,
        });
        if (!response.ok) throw new Error('No se pudo subir la foto');
        const data = await response.json();
        variant.image_path = data.image_path;
        variant.public_image_url = data.public_url;
        variant.image_url = '';
        variant.pendingFile = null;
    } catch (error) {
        console.error(error);
        alert('Error al subir foto del color');
    } finally {
        variant.uploading = false;
    }
};

const addVariant = () => {
    form.value.variants.push({
        color: '',
        image_url: '',
        sizes_stock: {},
        pendingFile: null,
        uploading: false,
    });
};

const removeVariant = (index: number) => {
    form.value.variants.splice(index, 1);
};

const addSizeToVariant = (variant: ProductVariant) => {
    const size = prompt('Ingrese la talla (ej: S, M, L):');
    if (size) {
        const stock = prompt('Ingrese el stock:');
        if (stock) {
            variant.sizes_stock[size] = parseInt(stock);
        }
    }
};

const removeSizeFromVariant = (variant: ProductVariant, size: string) => {
    delete variant.sizes_stock[size];
};

const addTag = () => {
    if (newTag.value.trim()) {
        form.value.tags_ia.push(newTag.value.trim());
        newTag.value = '';
    }
};

const removeTag = (index: number) => {
    form.value.tags_ia.splice(index, 1);
};

const getCsrfToken = (): string => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const submit = async () => {
    // Validación local antes de enviar
    if (!form.value.price || Number(form.value.price) <= 0) {
        alert('El precio del producto debe ser mayor a 0');
        return;
    }

    loading.value = true;
    try {
        const response = await fetch(`/api/products/${props.product.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        });
        if (!response.ok) {
            if (response.status === 419) {
                alert('Sesión expirada. Recarga la página (F5) e intenta de nuevo.');
                return;
            }
            const err = await response.json();
            throw new Error(err.message || 'Error al actualizar');
        }
        router.visit('/products');
    } catch (error: any) {
        console.error('Error updating product:', error);
        alert(error.message || 'Error al actualizar producto');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchCategories();
});
</script>

<template>
    <Head title="Editar Producto" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between">
                <div class="min-w-0 flex-1">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:truncate sm:text-3xl sm:tracking-tight">
                        Editar Producto
                    </h2>
                </div>
            </div>

            <form @submit.prevent="submit" class="mt-8 space-y-6">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-4">
                        <label for="name" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Nombre del producto</label>
                        <div class="mt-2">
                            <input
                                type="text"
                                id="name"
                                v-model="form.name"
                                required
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-800 sm:text-sm sm:leading-6"
                            />
                        </div>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="category" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Categoría</label>
                        <div class="mt-2">
                            <select
                                id="category"
                                v-model="form.category_id"
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-800 sm:text-sm sm:leading-6"
                            >
                                <option :value="null">Sin categoría</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="sm:col-span-6">
                        <label for="description" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Descripción</label>
                        <div class="mt-2">
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-800 sm:text-sm sm:leading-6"
                            />
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="price" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Precio (S/)</label>
                        <div class="mt-2">
                            <input
                                type="number"
                                id="price"
                                v-model="form.price"
                                step="0.01"
                                min="0.01"
                                required
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-800 sm:text-sm sm:leading-6"
                            />
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="discount" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Descuento (S/)</label>
                        <div class="mt-2">
                            <input
                                type="number"
                                id="discount"
                                v-model="form.discount"
                                step="0.01"
                                min="0"
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-800 sm:text-sm sm:leading-6"
                            />
                        </div>
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Tags para IA</label>
                        <div class="mt-2 flex gap-2">
                            <input
                                type="text"
                                v-model="newTag"
                                @keyup.enter="addTag"
                                placeholder="Agregar tag y presionar Enter"
                                class="block flex-1 rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-800 sm:text-sm sm:leading-6"
                            />
                            <button
                                type="button"
                                @click="addTag"
                                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                            >
                                Agregar
                            </button>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="(tag, index) in form.tags_ia"
                                :key="index"
                                class="inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 text-sm font-medium text-indigo-700 dark:text-indigo-300 ring-1 ring-inset ring-indigo-700/10 dark:ring-indigo-400/30"
                            >
                                {{ tag }}
                                <button type="button" @click="removeTag(index)" class="ml-1 text-indigo-500 hover:text-indigo-700">×</button>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-900/10 dark:border-gray-700 pt-8">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white">Variantes</h3>
                        <button
                            type="button"
                            @click="addVariant"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        >
                            Agregar Variante
                        </button>
                    </div>

                    <div v-if="form.variants.length === 0" class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        No hay variantes. Agrega al menos una.
                    </div>

                    <div v-for="(variant, index) in form.variants" :key="index" class="mt-4 border border-gray-300 dark:border-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Variante {{ index + 1 }}</h4>
                            <button
                                type="button"
                                @click="removeVariant(index)"
                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 text-sm"
                            >
                                Eliminar
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Color</label>
                                <input
                                    type="text"
                                    v-model="variant.color"
                                    required
                                    class="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-800 sm:text-sm sm:leading-6"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Foto por color</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-200"
                                    @change="onVariantPhotoSelected(variant, $event)"
                                />
                                <p v-if="!variant.id" class="mt-1 text-xs text-amber-600">Guarda el producto primero para subir foto de esta variante.</p>
                                <p v-if="variant.uploading" class="mt-1 text-xs text-gray-500">Subiendo foto...</p>
                                <img
                                    v-if="variantPreviewUrl(variant)"
                                    :src="variantPreviewUrl(variant)!"
                                    alt="Vista previa"
                                    class="mt-2 h-28 w-28 rounded-md object-cover border border-gray-200 dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Stock por talla</label>
                                <button
                                    type="button"
                                    @click="addSizeToVariant(variant)"
                                    class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    + Agregar talla
                                </button>
                            </div>
                            <div v-if="Object.keys(variant.sizes_stock).length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                                No hay tallas configuradas
                            </div>
                            <div v-else class="flex flex-wrap gap-2">
                                <span
                                    v-for="(stock, size) in variant.sizes_stock"
                                    :key="size"
                                    class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-1 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ size }}: {{ stock }}
                                    <button
                                        type="button"
                                        @click="removeSizeFromVariant(variant, size)"
                                        class="ml-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-100"
                                    >
                                        ×
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-x-6">
                    <Link
                        href="/products"
                        class="text-sm font-semibold leading-6 text-gray-900 dark:text-white hover:text-gray-700 dark:hover:text-gray-300"
                    >
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ loading ? 'Guardando...' : 'Guardar cambios' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
