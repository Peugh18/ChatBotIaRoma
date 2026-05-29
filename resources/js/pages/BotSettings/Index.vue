<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Bot, Save, Info, ChevronDown, ChevronUp } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useBotSettings } from '@/composables/useBotSettings';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Personalidad del bot', href: '/bot-settings' }];

const { settings, loading, saving, error, success, saveSettings } = useBotSettings();
const showAdvanced = ref(false);
</script>

<template>
    <Head title="Personalidad del bot" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page mx-auto max-w-4xl space-y-6">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                    <Bot class="h-7 w-7 text-primary" />
                    Personalidad del bot
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Complemento para que el bot suene amable. <strong>No define el flujo de ventas</strong> (menú, catálogo, pedidos): eso está en el código.
                </p>
            </div>

            <div
                class="flex gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100"
                role="note"
            >
                <Info class="mt-0.5 h-5 w-5 shrink-0" />
                <div class="space-y-1">
                    <p class="font-semibold">¿Qué configuras aquí?</p>
                    <ul class="list-inside list-disc space-y-0.5 text-blue-800/90 dark:text-blue-200/90">
                        <li><strong>Amabilidad:</strong> cómo se expresa (ej. «Hola linda», tono cercano).</li>
                        <li><strong>Textos de apoyo:</strong> si pasa a humano o recordatorios de carrito.</li>
                        <li><strong>Encendido/apagado</strong> del bot automático.</li>
                    </ul>
                    <p class="pt-1 text-xs opacity-90">
                        Tono de cierre y datos de pago →
                        <a href="/company-settings" class="font-medium underline">Configuración de empresa</a>.
                    </p>
                </div>
            </div>

            <div v-if="loading" class="py-12 text-center text-gray-500">Cargando...</div>

            <template v-else-if="settings">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <div>
                            <CardTitle>Bot automático</CardTitle>
                            <CardDescription>Si está apagado, no responde mensajes entrantes</CardDescription>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="settings.auto_reply_enabled"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="settings.auto_reply_enabled ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'"
                            @click="settings.auto_reply_enabled = !settings.auto_reply_enabled"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                :class="settings.auto_reply_enabled ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </button>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Amabilidad y forma de hablar</CardTitle>
                        <CardDescription>
                            Define el trato (nombre, saludos, cercanía). Se usa cuando el motor IA está activo como respaldo; el flujo principal sigue siendo por reglas en código.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <Label for="system-prompt">Instrucciones de personalidad</Label>
                        <textarea
                            id="system-prompt"
                            v-model="settings.system_prompt"
                            rows="4"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            placeholder="Ej: Eres Leidi. Responde siempre amable: Hola linda, buenas tardes según la hora..."
                        />
                        <p class="text-xs text-muted-foreground">
                            Ejemplo: «Eres Leidi, responde amable, di Hola linda o buenas tardes según la hora.»
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Textos de apoyo (sí se usan)</CardTitle>
                        <CardDescription>Mensajes cuando escala a humano o recordatorios automáticos</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="escalation">Al pasar a asesor humano</Label>
                            <textarea
                                id="escalation"
                                v-model="settings.escalation_message"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="reminder-3">Recordatorio ~3 min (pedido sin confirmar)</Label>
                            <textarea
                                id="reminder-3"
                                v-model="settings.reminder_3min_message"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="reminder-15">Recordatorio ~15 min</Label>
                            <textarea
                                id="reminder-15"
                                v-model="settings.reminder_15min_message"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <p class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:border-gray-600 dark:bg-gray-900/30 dark:text-gray-400">
                            <strong>Saludo inicial:</strong> lo define el código (menú de opciones al decir «hola»), no un campo de esta pantalla.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tiempos de recordatorio</CardTitle>
                        <CardDescription>Segundos sin respuesta del cliente antes de enviar cada recordatorio</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="sec-3">Primer recordatorio (seg)</Label>
                            <Input id="sec-3" v-model.number="settings.reminder_3min_seconds" type="number" min="60" />
                        </div>
                        <div class="space-y-2">
                            <Label for="sec-15">Segundo recordatorio (seg)</Label>
                            <Input id="sec-15" v-model.number="settings.reminder_15min_seconds" type="number" min="60" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-6 py-4 text-left"
                        @click="showAdvanced = !showAdvanced"
                    >
                        <div>
                            <CardTitle class="text-base">Opcional: motor IA (Groq)</CardTitle>
                            <CardDescription class="mt-1">Solo si en código está activo el fallback con LLM</CardDescription>
                        </div>
                        <component :is="showAdvanced ? ChevronUp : ChevronDown" class="h-5 w-5 text-gray-500" />
                    </button>
                    <CardContent v-show="showAdvanced" class="space-y-4 border-t pt-4">
                        <div class="space-y-2">
                            <Label for="groq-key">API Key Groq</Label>
                            <Input id="groq-key" v-model="settings.groq_api_key" type="password" placeholder="gsk_..." />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="model-chat">Modelo chat</Label>
                                <Input id="model-chat" v-model="settings.model_chat" />
                            </div>
                            <div class="space-y-2">
                                <Label for="model-vision">Modelo visión (fotos)</Label>
                                <Input id="model-vision" v-model="settings.model_vision" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

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
                    <Button :disabled="saving" @click="saveSettings">
                        <Save class="mr-2 h-4 w-4" />
                        {{ saving ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
