<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { FlaskConical, RotateCcw, Send } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { apiJson, ApiError } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Debug del bot', href: '/bot-debug' }];

const phone = ref('51999999999');
const message = ref('hola');
const imageUrl = ref('');
const loading = ref(false);
const resetting = ref(false);
const error = ref<string | null>(null);
const result = ref<Record<string, unknown> | null>(null);

const simulate = async () => {
    loading.value = true;
    error.value = null;
    result.value = null;
    try {
        result.value = await apiJson<Record<string, unknown>>('/api/bot-debug/simulate', {
            method: 'POST',
            body: JSON.stringify({
                phone: phone.value,
                message: message.value,
                image_url: imageUrl.value || null,
            }),
        });
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Error en la simulación';
    } finally {
        loading.value = false;
    }
};

const resetConversation = async () => {
    if (!confirm('¿Reiniciar carrito y etapa de esta conversación de prueba?')) return;
    resetting.value = true;
    try {
        await apiJson('/api/bot-debug/reset', {
            method: 'POST',
            body: JSON.stringify({ phone: phone.value }),
        });
        result.value = null;
        alert('Conversación reiniciada');
    } catch (e) {
        alert(e instanceof ApiError ? e.message : 'No se pudo reiniciar');
    } finally {
        resetting.value = false;
    }
};
</script>

<template>
    <Head title="Debug del bot" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page mx-auto max-w-3xl space-y-6">
            <PageHeader
                title="Debug del flujo de ventas"
                description="Prueba mensajes sin enviar WhatsApp. Usa un número de prueba distinto al de clientes reales."
            />

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <FlaskConical class="h-5 w-5 text-primary" />
                        Simular mensaje entrante
                    </CardTitle>
                    <CardDescription>Corre el orquestador real y muestra etapa + contexto</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="phone">Teléfono (solo dígitos)</Label>
                        <Input id="phone" v-model="phone" placeholder="51987654321" />
                    </div>
                    <div class="space-y-2">
                        <Label for="message">Mensaje del cliente</Label>
                        <textarea
                            id="message"
                            v-model="message"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="hola, ver categorias, confirmar pedido..."
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="image">URL imagen (opcional, comprobante o foto vestido)</Label>
                        <Input id="image" v-model="imageUrl" placeholder="https://..." />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button :disabled="loading" @click="simulate">
                            <Send class="mr-2 h-4 w-4" />
                            {{ loading ? 'Procesando...' : 'Simular' }}
                        </Button>
                        <Button variant="outline" :disabled="resetting" @click="resetConversation">
                            <RotateCcw class="mr-2 h-4 w-4" />
                            Reiniciar conversación
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <p v-if="error" class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ error }}
            </p>

            <Card v-if="result">
                <CardHeader>
                    <CardTitle class="text-base">Respuesta del bot</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Texto</p>
                        <pre class="mt-1 whitespace-pre-wrap rounded-lg bg-muted/50 p-3 text-sm">{{ (result.response as { text?: string })?.text || '(vacío)' }}</pre>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Etapa</p>
                        <p class="text-sm font-medium">{{ (result.state as { etapa_venta?: string })?.etapa_venta ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Metadata / contexto</p>
                        <pre class="mt-1 max-h-80 overflow-auto rounded-lg bg-muted/50 p-3 text-xs">{{ JSON.stringify(result, null, 2) }}</pre>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
