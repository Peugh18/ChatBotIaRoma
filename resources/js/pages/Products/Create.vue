<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

import { ref, onMounted } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Productos',
        href: '/products',
    },
    {
        title: 'Nuevo Producto',
        href: '/products/create',
    },
];

interface Category {
    id: number;
    name: string;
}

interface ProductVariant {
    color: string;
    image_url: string;
    sizes_stock: Record<string, number>;
    pendingFile?: File | null;
}

const form = ref({
    name: '',
    description: '',
    price: null as number | null,
    discount: null as number | null,
    category_id: null as number | null,
    tags_ia: [] as string[],
    variants: [] as ProductVariant[],
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

const onVariantPhotoSelected = (variant: ProductVariant, event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) variant.pendingFile = file;
};

const addVariant = () => {
    form.value.variants.push({
        color: '',
        image_url: '',
        sizes_stock: {},
        pendingFile: null,
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
    if (!form.value.price || form.value.price <= 0) {
        alert('El precio del producto debe ser mayor a 0');
        return;
    }

    loading.value = true;
    try {
        const response = await fetch('/api/products', {
            method: 'POST',
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
            throw new Error(err.message || 'Error al crear producto');
        }
        const created = await response.json();
        console.log('Producto creado:', created);
        
        const photoErrors: string[] = [];
        for (let i = 0; i < form.value.variants.length; i++) {
            const pending = form.value.variants[i].pendingFile;
            const createdVariant = created.variants?.[i];
            if (pending && createdVariant?.id) {
                console.log('Subiendo foto para variant ID:', createdVariant.id, 'Archivo:', pending.name);
                
                const body = new FormData();
                body.append('photo', pending);
                
                const photoResponse = await fetch(`/api/product-variants/${createdVariant.id}/photo`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body,
                });
                
                console.log('Photo response status:', photoResponse.status);
                
                if (!photoResponse.ok) {
                    let message = `HTTP ${photoResponse.status}`;
                    try {
                        const err = await photoResponse.json();
                        message = err.message || err.errors?.photo?.[0] || message;
                    } catch {
                        message = (await photoResponse.text()) || message;
                    }
                    photoErrors.push(`${form.value.variants[i].color}: ${message}`);
                }
            }
        }

        if (photoErrors.length > 0) {
            alert(`Producto creado, pero falló la subida de fotos:\n\n${photoErrors.join('\n')}`);
            router.visit(`/products/${created.id}/edit`);
            return;
        }

        router.visit('/products');
    } catch (error: any) {
        console.error('Error creating product:', error);
        alert(error.message || 'Error al crear producto');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchCategories();
    addVariant();
});
</script>

<template>
    <Head title="Nuevo Producto" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between">
                <div class="min-w-0 flex-1">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:truncate sm:text-3xl sm:tracking-tight">
                        Nuevo Producto
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
                                <p class="mt-1 text-xs text-gray-500">Se sube al guardar el producto.</p>
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
                        {{ loading ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
