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
        status?: string;
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
    status: props.product.status || 'disponible',
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
const allProducts = ref<{ id: number; name: string }[]>([]);
const similarIds = ref<number[]>([]);
const savingSimilares = ref(false);
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
    console.log('onVariantPhotoSelected called');
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    console.log('File selected:', file);
    if (!file) {
        console.log('No file selected, returning');
        return;
    }
    variant.pendingFile = file;
    console.log('File set as pending, variant ID:', variant.id);
};

const uploadVariantPhotoForId = async (variantId: number, file: File): Promise<{ image_path: string; public_url: string }> => {
    const body = new FormData();
    body.append('photo', file);

    const response = await fetch(`/api/product-variants/${variantId}/photo`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body,
    });

    if (!response.ok) {
        let message = `No se pudo subir la foto (HTTP ${response.status})`;
        try {
            const err = await response.json();
            message = err.message || err.errors?.photo?.[0] || message;
        } catch {
            // respuesta no JSON
        }
        throw new Error(message);
    }

    return response.json();
};

const uploadVariantPhoto = async (variant: ProductVariant) => {
    if (!variant.id) {
        alert('Guarda el producto primero (botón Guardar abajo) y luego sube la foto, o guarda con la foto ya seleccionada.');
        return;
    }
    if (!variant.pendingFile) {
        alert('Selecciona una imagen primero.');
        return;
    }

    variant.uploading = true;
    try {
        const data = await uploadVariantPhotoForId(variant.id, variant.pendingFile);
        variant.image_path = data.image_path;
        variant.public_image_url = data.public_url;
        variant.image_url = '';
        variant.pendingFile = null;
        alert('Foto guardada exitosamente');
    } catch (error: any) {
        console.error('Error al subir foto:', error);
        alert(`Error al subir foto (${variant.color || 'variante'}): ${error.message}`);
    } finally {
        variant.uploading = false;
    }
};

const uploadPendingVariantPhotos = async (savedVariants: { id: number }[]) => {
    const errors: string[] = [];

    for (let i = 0; i < form.value.variants.length; i++) {
        const pending = form.value.variants[i].pendingFile;
        const saved = savedVariants[i];
        if (!pending || !saved?.id) {
            continue;
        }

        try {
            const data = await uploadVariantPhotoForId(saved.id, pending);
            form.value.variants[i].image_path = data.image_path;
            form.value.variants[i].public_image_url = data.public_url;
            form.value.variants[i].image_url = '';
            form.value.variants[i].pendingFile = null;
        } catch (error: any) {
            errors.push(`${form.value.variants[i].color || `Variante ${i + 1}`}: ${error.message}`);
        }
    }

    return errors;
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

        const updated = await response.json();
        const photoErrors = await uploadPendingVariantPhotos(updated.variants ?? []);

        if (photoErrors.length > 0) {
            alert(`Producto guardado, pero falló la subida de fotos:\n\n${photoErrors.join('\n')}`);
            return;
        }

        router.visit('/products');
    } catch (error: any) {
        console.error('Error updating product:', error);
        alert(error.message || 'Error al actualizar producto');
    } finally {
        loading.value = false;
    }
};

const fetchAllProducts = async () => {
    try {
        const list = await fetch('/api/products', { headers: { Accept: 'application/json' } }).then((r) => r.json());
        allProducts.value = (list as { id: number; name: string }[]).filter((p) => p.id !== props.product.id);
    } catch {
        allProducts.value = [];
    }
};

const fetchSimilares = async () => {
    try {
        const res = await fetch(`/api/products/${props.product.id}/similares`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        similarIds.value = (data.manual ?? []).map((m: { id: number }) => m.id);
    } catch {
        similarIds.value = [];
    }
};

const saveSimilares = async () => {
    savingSimilares.value = true;
    try {
        await fetch(`/api/products/${props.product.id}/similares`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ similar_product_ids: similarIds.value }),
        });
        alert('Similares guardados');
    } catch {
        alert('No se pudieron guardar los similares');
    } finally {
        savingSimilares.value = false;
    }
};

const toggleSimilar = (id: number) => {
    const idx = similarIds.value.indexOf(id);
    if (idx >= 0) {
        similarIds.value.splice(idx, 1);
    } else if (similarIds.value.length < 5) {
        similarIds.value.push(id);
    }
};

onMounted(() => {
    fetchCategories();
    fetchAllProducts();
    fetchSimilares();
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
                        <label for="status" class="block text-sm font-medium leading-6 text-gray-900 dark:text-white">Estado en bot</label>
                        <div class="mt-2">
                            <select
                                id="status"
                                v-model="form.status"
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 dark:bg-gray-800 sm:text-sm"
                            >
                                <option value="disponible">Disponible</option>
                                <option value="agotado">Agotado</option>
                                <option value="oculto">Oculto (no se muestra)</option>
                            </select>
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
                                <div class="mt-1 flex gap-2">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        ref="fileInput"
                                        class="block w-full text-sm text-gray-700 dark:text-gray-200"
                                        @change="onVariantPhotoSelected(variant, $event)"
                                    />
                                    <button
                                        type="button"
                                        @click="uploadVariantPhoto(variant)"
                                        :disabled="!variant.pendingFile || variant.uploading"
                                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ variant.uploading ? 'Subiendo...' : 'Subir Foto' }}
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Selecciona la foto y pulsa «Subir Foto», o guarda el producto y se subirá automáticamente.</p>
                                <p v-if="!variant.id" class="mt-1 text-xs text-amber-600">Esta variante es nueva: al guardar el producto se creará y podrás subir la foto.</p>
                                <p v-if="variant.pendingFile" class="mt-1 text-xs text-green-600">Archivo seleccionado: {{ variant.pendingFile.name }}</p>
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

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Productos similares (bot)</h3>
                    <p class="text-xs text-gray-500">
                        Si este modelo se agota, el bot ofrece estos primero (máx. 5). Si no eliges ninguno, usa la misma categoría automáticamente.
                    </p>
                    <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto">
                        <button
                            v-for="p in allProducts"
                            :key="p.id"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs border transition-colors"
                            :class="
                                similarIds.includes(p.id)
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600'
                            "
                            @click="toggleSimilar(p.id)"
                        >
                            {{ p.name }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-indigo-600 dark:text-indigo-400"
                        :disabled="savingSimilares"
                        @click="saveSimilares"
                    >
                        {{ savingSimilares ? 'Guardando...' : 'Guardar similares' }}
                    </button>
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
