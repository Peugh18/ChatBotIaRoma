<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Plus, Trash2, Image, Tag, ChevronLeft, Save, Package, X, Sparkles } from 'lucide-vue-next';

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
    tempSize?: string;
    tempStock?: number | null;
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
        tempSize: '',
        tempStock: null as number | null,
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
        tempSize: '',
        tempStock: null,
    });
};

const removeVariant = (index: number) => {
    form.value.variants.splice(index, 1);
};

const addSizeToVariantInline = (variant: ProductVariant) => {
    const size = variant.tempSize?.trim().toUpperCase();
    const stock = variant.tempStock;
    if (!size) return;
    if (stock === undefined || stock === null || stock < 0) return;
    
    variant.sizes_stock[size] = stock;
    variant.tempSize = '';
    variant.tempStock = null;
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
        <div class="crm-page max-w-4xl mx-auto">
            <PageHeader
                title="Editar Producto"
                description="Modifica la información, precio, variantes, stock y similares del artículo."
            >
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link href="/products" class="flex items-center gap-1">
                            <ChevronLeft class="h-4 w-4" />
                            <span>Volver</span>
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <form @submit.prevent="submit" class="mt-8 space-y-6">
                <!-- Información General -->
                <CrmPanel>
                    <div class="mb-4 pb-3 border-b border-border">
                        <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                            <Package class="h-5 w-5 text-primary" />
                            Información General
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-6 sm:gap-x-6">
                        <div class="sm:col-span-4 space-y-1.5">
                            <Label for="name">Nombre del producto</Label>
                            <Input
                                type="text"
                                id="name"
                                v-model="form.name"
                                required
                                placeholder="Ej: Camiseta Oversize Negra"
                            />
                        </div>

                        <div class="sm:col-span-4 space-y-1.5">
                            <Label for="category">Categoría</Label>
                            <select
                                id="category"
                                v-model="form.category_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option :value="null">Sin categoría</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>

                        <div class="sm:col-span-6 space-y-1.5">
                            <Label for="description">Descripción</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Escribe detalles del producto..."
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                    </div>
                </CrmPanel>

                <!-- Estado, Precios y Descuentos -->
                <CrmPanel>
                    <div class="mb-4 pb-3 border-b border-border">
                        <h3 class="text-lg font-semibold text-foreground">
                            Estado, Precios y Descuentos
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-3 sm:gap-x-6">
                        <div class="space-y-1.5">
                            <Label for="status">Estado en bot</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="disponible">Disponible</option>
                                <option value="agotado">Agotado</option>
                                <option value="oculto">Oculto (no se muestra)</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="price">Precio (S/)</Label>
                            <Input
                                type="number"
                                id="price"
                                v-model="form.price"
                                step="0.01"
                                min="0.01"
                                required
                                placeholder="Ej: 89.90"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="discount">Descuento (S/)</Label>
                            <Input
                                type="number"
                                id="discount"
                                v-model="form.discount"
                                step="0.01"
                                min="0"
                                placeholder="Ej: 10.00 (Dejar en 0 o vacío si no aplica)"
                            />
                        </div>
                    </div>
                </CrmPanel>

                <!-- Tags del Producto -->
                <CrmPanel>
                    <div class="mb-4 pb-3 border-b border-border">
                        <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                            <Tag class="h-5 w-5 text-primary" />
                            Etiquetas para Inteligencia Artificial (Tags)
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Ayudan al bot a entender mejor de qué tipo de producto se trata para responder consultas.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex gap-2">
                            <Input
                                type="text"
                                v-model="newTag"
                                @keyup.enter="addTag"
                                placeholder="Ej: elegante, algodon, urbano..."
                                class="flex-1"
                            />
                            <Button
                                type="button"
                                @click="addTag"
                            >
                                <Plus class="h-4 w-4 mr-1.5" />
                                Agregar
                            </Button>
                        </div>
                        <div v-if="form.tags_ia.length === 0" class="text-xs text-muted-foreground italic">
                            No hay etiquetas configuradas.
                        </div>
                        <div v-else class="flex flex-wrap gap-1.5">
                            <span
                                v-for="(tag, index) in form.tags_ia"
                                :key="index"
                                class="inline-flex items-center rounded-full bg-primary/10 border border-primary/20 px-3 py-0.5 text-xs font-semibold text-primary animate-fade-in"
                            >
                                {{ tag }}
                                <button type="button" @click="removeTag(index)" class="ml-1.5 text-primary hover:text-primary/75 focus:outline-none">
                                    <X class="h-3 w-3" />
                                </button>
                            </span>
                        </div>
                    </div>
                </CrmPanel>

                <!-- Variantes y Stock -->
                <CrmPanel>
                    <div class="mb-4 pb-3 border-b border-border flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">
                                Variantes y Control de Stock
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Configura colores, fotos y el stock disponible por cada talla.</p>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            @click="addVariant"
                        >
                            <Plus class="h-4 w-4 mr-1.5" />
                            Agregar Variante
                        </Button>
                    </div>

                    <div v-if="form.variants.length === 0" class="text-center py-8 border border-dashed border-border rounded-xl text-muted-foreground text-sm">
                        No hay variantes configuradas. Agrega al menos una para continuar.
                    </div>

                    <div v-else class="space-y-6">
                        <div 
                            v-for="(variant, index) in form.variants" 
                            :key="index" 
                            class="border border-border bg-muted/15 p-6 rounded-xl relative shadow-sm animate-in fade-in-50 duration-200"
                        >
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-border/50">
                                <h4 class="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                        {{ index + 1 }}
                                    </span>
                                    Variante de Color
                                </h4>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive hover:bg-destructive/10 h-8 px-2"
                                    @click="removeVariant(index)"
                                >
                                    <Trash2 class="h-4 w-4 mr-1" />
                                    <span>Eliminar</span>
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-6">
                                <div class="space-y-1.5">
                                    <Label>Color / Nombre del tono</Label>
                                    <Input
                                        type="text"
                                        v-model="variant.color"
                                        required
                                        placeholder="Ej: Negro, Rojo, Azul Marino"
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <Label>Foto por color</Label>
                                    <div class="flex flex-col gap-3">
                                        <div class="flex items-center gap-3">
                                            <label class="flex flex-col items-center justify-center border border-dashed border-input hover:border-primary bg-background hover:bg-primary/5 rounded-lg p-4 cursor-pointer transition text-center shrink-0 w-28 h-20">
                                                <Image class="h-5 w-5 text-muted-foreground" />
                                                <span class="text-[10px] text-muted-foreground mt-1 font-medium">Subir foto</span>
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    class="hidden"
                                                    @change="onVariantPhotoSelected(variant, $event)"
                                                />
                                            </label>
                                            <div class="text-xs flex-1">
                                                <div v-if="variant.pendingFile" class="font-medium text-foreground truncate max-w-[200px]" :title="variant.pendingFile.name">
                                                    {{ variant.pendingFile.name }}
                                                </div>
                                                <div v-else class="text-muted-foreground">
                                                    Ningún archivo seleccionado
                                                </div>
                                                <div class="mt-2 flex gap-1.5">
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        class="h-7 text-[10px]"
                                                        @click="uploadVariantPhoto(variant)"
                                                        :disabled="!variant.pendingFile || variant.uploading"
                                                    >
                                                        {{ variant.uploading ? 'Subiendo...' : 'Subir Foto Ahora' }}
                                                    </Button>
                                                </div>
                                                <p class="text-[9px] text-muted-foreground/75 mt-1">Selecciona la foto y pulsa «Subir Foto Ahora», o guarda el producto para subirla automáticamente.</p>
                                                <p v-if="!variant.id" class="text-[9px] text-amber-600 mt-1 font-medium">Nueva variante: se creará al guardar el producto.</p>
                                            </div>
                                        </div>

                                        <!-- Vista Previa de Imagen -->
                                        <div v-if="variantPreviewUrl(variant)" class="mt-1">
                                            <div class="text-[10px] text-muted-foreground mb-1">Imagen actual / seleccionada:</div>
                                            <img
                                                :src="variantPreviewUrl(variant)!"
                                                alt="Vista previa"
                                                class="h-28 w-28 rounded-md object-cover border border-border bg-muted/20"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stock por Talla Inline Form -->
                            <div class="mt-6 pt-4 border-t border-border/50">
                                <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block mb-2">Stock por talla</Label>
                                
                                <div v-if="Object.keys(variant.sizes_stock).length === 0" class="text-xs text-muted-foreground italic mb-4">
                                    No hay tallas configuradas. Agrega tallas abajo.
                                </div>
                                <div v-else class="flex flex-wrap gap-2 mb-4">
                                    <span
                                        v-for="(stock, size) in variant.sizes_stock"
                                        :key="size"
                                        class="inline-flex items-center rounded-full bg-muted border border-border px-3 py-0.5 text-xs font-medium text-foreground"
                                    >
                                        <span class="font-bold text-primary mr-1">{{ size }}:</span> {{ stock }} ud
                                        <button
                                            type="button"
                                            @click="removeSizeFromVariant(variant, size)"
                                            class="ml-1.5 text-muted-foreground hover:text-foreground focus:outline-none"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 max-w-md">
                                    <div class="w-32">
                                        <Input
                                            type="text"
                                            v-model="variant.tempSize"
                                            placeholder="Talla (Ej: S, 38)"
                                            class="h-9 text-xs"
                                            @keyup.enter="addSizeToVariantInline(variant)"
                                        />
                                    </div>
                                    <div class="w-32">
                                        <Input
                                            type="number"
                                            v-model.number="variant.tempStock"
                                            placeholder="Stock (Ej: 15)"
                                            class="h-9 text-xs"
                                            min="0"
                                            @keyup.enter="addSizeToVariantInline(variant)"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-9 px-3 gap-1 text-xs"
                                        @click="addSizeToVariantInline(variant)"
                                    >
                                        <Plus class="h-3 w-3" />
                                        <span>Añadir</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CrmPanel>

                <!-- Similares para el Bot -->
                <CrmPanel>
                    <div class="mb-4 pb-3 border-b border-border">
                        <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                            <Sparkles class="h-5 w-5 text-primary" />
                            Productos Similares (Recomendaciones del Bot)
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Si este modelo se agota, el bot ofrecerá estos productos alternativos (máx. 5). Si no eliges ninguno, el bot recomendará productos de la misma categoría.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto p-1 border border-border rounded-lg bg-muted/10">
                            <button
                                v-for="p in allProducts"
                                :key="p.id"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs border transition duration-150 font-medium select-none"
                                :class="
                                    similarIds.includes(p.id)
                                        ? 'bg-primary text-primary-foreground border-primary'
                                        : 'bg-background hover:bg-muted text-foreground border-border'
                                "
                                @click="toggleSimilar(p.id)"
                            >
                                {{ p.name }}
                            </button>
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-xs text-muted-foreground">
                                Seleccionados: {{ similarIds.length }} de 5
                            </span>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                :disabled="savingSimilares"
                                @click="saveSimilares"
                            >
                                {{ savingSimilares ? 'Guardando...' : 'Guardar similares ahora' }}
                            </Button>
                        </div>
                    </div>
                </CrmPanel>

                <!-- Acciones del Formulario -->
                <div class="flex items-center justify-end gap-3 border-t border-border pt-6 mt-8">
                    <Button variant="outline" as-child>
                        <Link href="/products">
                            Cancelar
                        </Link>
                    </Button>
                    <Button
                        type="submit"
                        :disabled="loading"
                        class="gap-1.5"
                    >
                        <Save class="h-4 w-4" />
                        <span>{{ loading ? 'Guardando...' : 'Guardar cambios' }}</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
