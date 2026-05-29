<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Building2, MessageCircle, CreditCard, Clock, Share2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useCompanySettings } from '@/composables/useCompanySettings';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Configuración de empresa', href: '/company-settings' }];

const { form, loading, saving, error, success, saveSettings } = useCompanySettings();
</script>

<template>
    <Head title="Configuración de empresa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page mx-auto max-w-3xl space-y-6">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                    Configuración de empresa
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Datos reales de tu tienda que el bot usa al <strong>cerrar venta</strong> y al <strong>hablarle al cliente</strong>. No cambia el menú ni los pasos del flujo (eso va en código).
                </p>
            </div>

            <div v-if="loading" class="py-12 text-center text-gray-500">Cargando...</div>

            <form v-else-if="form" class="space-y-6" @submit.prevent="saveSettings">
                <!-- Voz comercial -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <MessageCircle class="h-5 w-5 text-primary" />
                            Cómo cierra y se expresa el bot
                        </CardTitle>
                        <CardDescription>
                            Estos dos campos se mezclan en las respuestas automáticas (reglas + IA si está activa).
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-6 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="sales-tone">Tono de marca</Label>
                            <Input
                                id="sales-tone"
                                v-model="form.sales_tone"
                                placeholder='Ej: cálido, coqueto y vendedor'
                            />
                            <p class="text-xs text-muted-foreground">
                                Describe el estilo: cercano, elegante, divertido… El bot intentará sonar así al final de mensajes.
                            </p>
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="sales-cta">Frase de cierre (CTA)</Label>
                            <Input
                                id="sales-cta"
                                v-model="form.sales_closing_cta"
                                placeholder='Ej: ¿Te lo separo ahora?'
                            />
                            <p class="text-xs text-muted-foreground">
                                Pregunta o llamada a la acción que aparece al invitar a comprar (ej. «¿Te lo separo hoy?»).
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Pagos -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CreditCard class="h-5 w-5 text-emerald-500" />
                            Pago (Yape)
                        </CardTitle>
                        <CardDescription>
                            Cuando el cliente confirma pedido, el bot le indica a qué número y nombre hacer el Yape.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-6 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="yape-number">Número de Yape</Label>
                            <Input id="yape-number" v-model="form.yape_number" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="yape-name">Nombre del titular</Label>
                            <Input id="yape-name" v-model="form.yape_name" required />
                        </div>
                    </CardContent>
                </Card>

                <!-- Horario y tienda -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="h-5 w-5 text-amber-500" />
                            Tienda y horario
                        </CardTitle>
                        <CardDescription>
                            Si preguntan «¿dónde están?» o «¿a qué hora abren?», el bot puede usar estos datos (sobre todo con IA activa).
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-6 sm:grid-cols-6">
                        <div class="space-y-2 sm:col-span-4">
                            <Label for="company-name">Nombre de la tienda</Label>
                            <Input id="company-name" v-model="form.company_name" required />
                        </div>
                        <div class="space-y-2 sm:col-span-6">
                            <Label for="address">Dirección (opcional)</Label>
                            <Input id="address" v-model="form.address" placeholder="Ej: Av. Principal 123, Lima" />
                        </div>
                        <div class="space-y-2 sm:col-span-3">
                            <Label for="open-time">Hora de apertura</Label>
                            <Input id="open-time" v-model="form.business_hours.open" type="time" />
                        </div>
                        <div class="space-y-2 sm:col-span-3">
                            <Label for="close-time">Hora de cierre</Label>
                            <Input id="close-time" v-model="form.business_hours.close" type="time" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Redes -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Share2 class="h-5 w-5 text-pink-500" />
                            Redes sociales (opcional)
                        </CardTitle>
                        <CardDescription>
                            Referencia para el equipo; el bot aún no las menciona automáticamente en todos los flujos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="instagram">Instagram</Label>
                            <Input id="instagram" v-model="form.social_networks.instagram" placeholder="@vestidos_roma" />
                        </div>
                        <div class="space-y-2">
                            <Label for="facebook">Facebook</Label>
                            <Input id="facebook" v-model="form.social_networks.facebook" />
                        </div>
                        <div class="space-y-2">
                            <Label for="tiktok">TikTok</Label>
                            <Input id="tiktok" v-model="form.social_networks.tiktok" placeholder="@vestidosroma" />
                        </div>
                    </CardContent>
                </Card>

                <div
                    class="flex gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400"
                >
                    <Building2 class="h-4 w-4 shrink-0 text-gray-500" />
                    <p>
                        <strong>Resumen:</strong> «Tono + CTA» = personalidad comercial. «Yape» = instrucciones de pago en el flujo de pedido.
                        «Horario/dirección» = datos de la tienda. La amabilidad del saludo («Hola linda») se configura en
                        <a href="/bot-settings" class="text-indigo-600 underline dark:text-indigo-400">Personalidad del bot</a>.
                    </p>
                </div>

                <div
                    v-if="success"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
                    role="status"
                >
                    {{ success }}
                </div>
                <div
                    v-if="error"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
                    role="alert"
                >
                    {{ error }}
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="saving">
                        {{ saving ? 'Guardando...' : 'Guardar cambios' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
