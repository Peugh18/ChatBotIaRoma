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
import { Plus, Trash2, Image, Tag, ChevronLeft, Save, Package, X } from 'lucide-vue-next';

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
    tempSize?: string;
    tempStock?: number | null;
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
        <div class="crm-page max-w-4xl mx-auto">
            <PageHeader
                title="Nuevo Producto"
                description="Crea un nuevo artículo en el catálogo con sus variantes y stock por talla."
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

                <!-- Precios y Descuentos -->
                <CrmPanel>
                    <div class="mb-4 pb-3 border-b border-border">
                        <h3 class="text-lg font-semibold text-foreground">
                            Precio y Descuentos
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-6">
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
                                        <div class="text-xs">
                                            <div v-if="variant.pendingFile" class="font-medium text-foreground truncate max-w-[200px]" :title="variant.pendingFile.name">
                                                {{ variant.pendingFile.name }}
                                            </div>
                                            <div v-else class="text-muted-foreground">
                                                Ningún archivo seleccionado
                                            </div>
                                            <p class="text-[10px] text-muted-foreground/75 mt-0.5">Formatos: JPG, PNG. Máx 2MB.</p>
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
                        <span>{{ loading ? 'Creando...' : 'Crear Producto' }}</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
