<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import StatCard from '@/components/crm/StatCard.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    User,
    Tag,
    FileText,
    Sparkles,
    UserX,
    MessageSquare,
    Bot,
    Save,
    History,
    AlertTriangle,
    ShoppingBag,
    Truck,
    Package,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useChatPage } from '@/composables/useChatPage';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chat', href: '/chat' }];

const {
    selectedPhone,
    messagesContainer,
    loading,
    sending,
    retryingMessageIds,
    newMessage,
    filterType,
    inboxTab,
    shippingInboxCount,
    conversations,
    filteredMessages,
    customerDetails,
    savingCustomer,
    profileMessage,
    botMetrics,
    escalationAlerts,
    salesContext,
    loadingSalesContext,
    sendingPhotoColor,
    galleryProductOverride,
    galleryColors,
    galleryProductName,
    photoError,
    currentConversationMode,
    isAutoEscalated,
    sendError,
    saveCustomerProfile,
    addTag,
    removeTag,
    updateConversationMode,
    sendMessage,
    retryMessage,
    sendColorPhotoToCustomer,
    validatePayment,
    validatingPayment,
    paymentValidationError,
    asesorPostPedido,
} = useChatPage();

const enSeguimientoPedido = computed(
    () => asesorPostPedido.value || salesContext.value?.asesor_post_pedido === true,
);

const awaitingPaymentValidation = computed(
    () => salesContext.value?.payment_validation?.pending === true,
);

const selectedConversation = computed(() =>
    conversations.value.find((c) => c.phone === selectedPhone.value),
);

const selectedDisplayName = computed(
    () => selectedConversation.value?.name?.trim() || selectedPhone.value || '',
);

const showSelectedPhoneSubtitle = computed(() => {
    const name = selectedConversation.value?.name?.trim();
    return Boolean(name && selectedPhone.value && name !== selectedPhone.value);
});
</script>

<template>
    <Head title="Chat WhatsApp" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page crm-page--chat">
            <div class="crm-chat-top">
            <!--PageHeader
                class="!mb-0"
                title="Chat WhatsApp"
                description="Conversaciones en tiempo real, contexto de venta y control de handoff humano."
            /-->

            <div
                v-if="escalationAlerts.length"
                class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
            >
                <div class="font-semibold">Chats que requieren atención humana</div>
                <ul class="mt-2 space-y-1">
                    <li v-for="(alert, idx) in escalationAlerts.slice(0, 5)" :key="idx">
                        {{ alert.phone_number }} — {{ alert.summary || 'Escalamiento automático' }}
                    </li>
                </ul>
            </div>

            <!--div v-if="botMetrics" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <StatCard label="Intenciones" :value="botMetrics.summary.intent_total" tone="success" />
                <StatCard label="Rutas ejecutadas" :value="botMetrics.summary.route_total" />
                <StatCard
                    label="Errores bot"
                    :value="botMetrics.summary.error_total"
                    :tone="botMetrics.summary.error_total > 0 ? 'danger' : 'success'"
                />
            </div-->
            </div>

            <div class="crm-chat-body">
                <div
                    class="crm-chat-filters grid max-w-md shrink-0 grid-cols-3 gap-1 rounded-lg border border-border bg-muted p-1 text-center text-xs font-medium"
                >
                    <button
                        type="button"
                        class="rounded-md py-1.5 transition"
                        :class="
                            filterType === 'all'
                                ? 'bg-card text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="filterType = 'all'"
                    >
                        Todos
                    </button>
                    <button
                        type="button"
                        class="flex items-center justify-center gap-0.5 rounded-md py-1.5 transition"
                        :class="
                            filterType === 'human'
                                ? 'bg-white text-red-600 shadow-sm dark:bg-gray-700 dark:text-red-400'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                        "
                        @click="filterType = 'human'"
                    >
                        <UserX class="h-3 w-3 shrink-0" />
                        Asesor
                    </button>
                    <button
                        type="button"
                        class="flex items-center justify-center gap-0.5 rounded-md py-1.5 transition"
                        :class="
                            filterType === 'ai'
                                ? 'bg-card chat-filter-active shadow-sm'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                            "
                            @click="filterType = 'ai'"
                    >
                        <Bot class="h-3 w-3 shrink-0" />
                        Solo IA
                    </button>
                </div>

                <div class="crm-chat-workspace">
                <!-- Lista de conversaciones -->
                <div class="crm-chat-panel lg:col-span-3">
                        <div class="grid grid-cols-2 gap-1 border-b border-border p-2">
                            <button
                                type="button"
                                class="rounded-md px-2 py-2 text-xs font-semibold transition"
                                :class="
                                    inboxTab === 'active'
                                        ? 'bg-card text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="inboxTab = 'active'"
                            >
                                <span class="flex items-center justify-center gap-1">
                                    <MessageSquare class="h-3.5 w-3.5 shrink-0" />
                                    Conversaciones
                                </span>
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-2 py-2 text-xs font-semibold transition"
                                :class="
                                    inboxTab === 'shipping'
                                        ? 'bg-emerald-600/15 text-emerald-700 shadow-sm dark:text-emerald-300'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="inboxTab = 'shipping'"
                            >
                                <span class="flex items-center justify-center gap-1">
                                    <Truck class="h-3.5 w-3.5 shrink-0" />
                                    Por enviar
                                    <span
                                        v-if="shippingInboxCount > 0"
                                        class="inline-flex min-w-[1.125rem] items-center justify-center rounded-full bg-emerald-600 px-1 text-[10px] font-bold text-white"
                                    >
                                        {{ shippingInboxCount }}
                                    </span>
                                </span>
                            </button>
                        </div>
                        <div class="crm-chat-panel-header">
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-foreground">
                                {{ inboxTab === 'shipping' ? 'Pedidos por enviar' : 'Conversaciones' }}
                            </h2>
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >
                                {{ conversations.length }}
                            </span>
                        </div>
                        <div class="chat-conv-list min-h-0 flex-1 divide-y divide-gray-100 overflow-y-auto">
                            <div
                                v-for="conv in conversations"
                                :key="conv.phone"
                                class="relative flex cursor-pointer flex-col gap-1 border-l-4 p-4 transition hover:bg-muted/50"
                                :class="[
                                    selectedPhone === conv.phone
                                        ? 'chat-selected-conv border-l-4'
                                        : conv.asesor_post_pedido
                                          ? 'border-l-emerald-500 bg-emerald-50/50 dark:border-l-emerald-500 dark:bg-emerald-950/20'
                                          : conv.is_auto_escalated
                                            ? 'animate-pulse border-l-amber-500 bg-amber-50/80 dark:border-l-amber-500 dark:bg-amber-950/20'
                                            : 'border-l-transparent',
                                ]"
                                @click="selectedPhone = conv.phone"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="truncate text-sm font-semibold text-foreground">
                                        {{ conv.name || conv.phone }}
                                    </div>
                                    <div class="whitespace-nowrap pt-0.5 text-[10px] text-muted-foreground">
                                        {{ new Date(conv.lastTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </div>
                                </div>
                                <div class="truncate pr-4 text-xs text-muted-foreground">
                                    {{ conv.lastMessage }}
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <span
                                        v-if="conv.asesor_post_pedido"
                                        class="inline-flex items-center gap-0.5 rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-950/30 dark:text-emerald-400"
                                    >
                                        <Package class="h-2.5 w-2.5 shrink-0" />
                                        Por enviar
                                    </span>
                                    <span
                                        v-else-if="conv.requires_human"
                                        class="inline-flex items-center gap-0.5 rounded-md border px-1.5 py-0.5 text-[10px] font-bold"
                                        :class="
                                            conv.is_auto_escalated
                                                ? 'border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-900/30 dark:bg-amber-950/30 dark:text-amber-400'
                                                : 'border-red-200/50 bg-red-50 text-red-600 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400'
                                        "
                                    >
                                        <AlertTriangle v-if="conv.is_auto_escalated" class="h-2.5 w-2.5 shrink-0" />
                                        👤 {{ conv.is_auto_escalated ? 'Handoff Automático' : 'Humano' }}
                                    </span>
                                    <span
                                        v-else
                                        class="chat-badge-bot inline-flex items-center gap-0.5 rounded-md border px-1.5 py-0.5 text-[10px] font-bold"
                                    >
                                        🤖 Bot Activo
                                    </span>
                                </div>
                            </div>
                            <div v-if="conversations.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                                {{
                                    inboxTab === 'shipping'
                                        ? 'No hay pedidos pendientes de envío'
                                        : 'No hay conversaciones'
                                }}
                            </div>
                        </div>
                </div>

                <!-- Chat -->
                    <div
                        :class="[
                            customerDetails ? 'lg:col-span-5' : 'lg:col-span-9',
                            'crm-chat-panel transition-all duration-300',
                        ]"
                    >
                        <div v-if="!selectedPhone" class="flex min-h-0 flex-1 items-center justify-center p-8 text-center">
                            <div>
                                <MessageSquare class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                                <p class="mt-4 text-sm text-muted-foreground">Selecciona una conversación para chatear</p>
                            </div>
                        </div>
                        <div v-else class="flex min-h-0 flex-1 flex-col overflow-hidden">
                            <div class="crm-chat-panel-header gap-3">
                                <div class="min-w-0 space-y-1">
                                    <h3 class="truncate text-sm font-bold leading-snug text-foreground">
                                        {{ selectedDisplayName }}
                                    </h3>
                                    <p
                                        v-if="showSelectedPhoneSubtitle"
                                        class="truncate text-xs leading-snug text-muted-foreground"
                                    >
                                        {{ selectedPhone }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <div
                                        class="chat-mode-bar flex items-center gap-2 rounded-lg border p-1"
                                    >
                                        <span class="hidden px-1 text-[10px] font-bold uppercase text-muted-foreground sm:inline"
                                            >Modo:</span
                                        >
                                        <div class="inline-flex rounded-md shadow-sm">
                                            <button
                                                type="button"
                                                class="flex items-center gap-1 rounded-l-md border px-2.5 py-1 text-xs font-bold transition-all duration-200"
                                                :class="
                                                    currentConversationMode === 'bot'
                                                        ? 'chat-btn-mode-active shadow-sm'
                                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-[hsl(var(--wa-border))] dark:bg-[hsl(var(--wa-panel-header))] dark:text-[hsl(var(--wa-bubble-in-fg))] dark:hover:brightness-110'
                                                "
                                                @click="updateConversationMode('bot')"
                                            >
                                                🤖 Bot IA
                                            </button>
                                            <button
                                                type="button"
                                                class="flex items-center gap-1 rounded-r-md border-b border-r border-t px-2.5 py-1 text-xs font-bold transition-all duration-200"
                                                :class="
                                                    currentConversationMode === 'human'
                                                        ? enSeguimientoPedido
                                                            ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm'
                                                            : isAutoEscalated
                                                              ? 'animate-pulse border-amber-500 bg-amber-500 text-white shadow-sm'
                                                              : 'border-red-600 bg-red-600 text-white shadow-sm'
                                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-[hsl(var(--wa-border))] dark:bg-[hsl(var(--wa-panel-header))] dark:text-[hsl(var(--wa-bubble-in-fg))] dark:hover:brightness-110'
                                                "
                                                @click="updateConversationMode('human')"
                                            >
                                                👤 Humano
                                            </button>
                                        </div>
                                        <span class="flex items-center gap-1 px-1 text-xs">
                                            <span
                                                class="h-2 w-2 rounded-full"
                                                :class="
                                                    currentConversationMode === 'bot'
                                                        ? 'animate-pulse bg-green-500'
                                                        : enSeguimientoPedido
                                                          ? 'bg-emerald-500'
                                                          : isAutoEscalated
                                                            ? 'animate-ping bg-amber-500'
                                                            : 'animate-pulse bg-red-500'
                                                "
                                            />
                                            <span
                                                class="hidden text-[11px] font-semibold md:inline"
                                                :class="
                                                    currentConversationMode === 'bot'
                                                        ? 'text-green-600 dark:text-green-400'
                                                        : enSeguimientoPedido
                                                          ? 'text-emerald-600 dark:text-emerald-400'
                                                          : isAutoEscalated
                                                            ? 'text-amber-600 dark:text-amber-400'
                                                            : 'text-red-600 dark:text-red-400'
                                                "
                                            >
                                                {{
                                                    currentConversationMode === 'bot'
                                                        ? 'IA Activa'
                                                        : enSeguimientoPedido
                                                          ? 'Por enviar'
                                                          : isAutoEscalated
                                                            ? 'Handoff Pendiente'
                                                            : 'Humano atendiendo'
                                                }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="currentConversationMode === 'human'"
                                class="flex shrink-0 items-center gap-2 border-b px-4 py-2 text-xs transition-colors duration-300"
                                :class="
                                    enSeguimientoPedido
                                        ? 'border-emerald-200/50 bg-emerald-50 text-emerald-800 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-300'
                                        : isAutoEscalated
                                          ? 'border-amber-200/50 bg-amber-50 text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-400'
                                          : 'border-red-200/50 bg-red-50 text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400'
                                "
                            >
                                <Package v-if="enSeguimientoPedido" class="h-4 w-4 shrink-0" />
                                <AlertTriangle v-else class="h-4 w-4 shrink-0" />
                                <span v-if="enSeguimientoPedido"
                                    ><strong>Pedido confirmado:</strong> Coordina entrega con el cliente. El bot vuelve cuando marques
                                    <em>Entregado</em> en el pipeline.</span
                                >
                                <span v-else-if="isAutoEscalated"
                                    ><strong>Handoff Automático:</strong> El cliente solicitó un asesor. ¡Responde desde aquí!</span
                                >
                                <span v-else
                                    ><strong>Modo Asesor:</strong> El bot está en pausa. Reactívalo con el toggle si lo necesitas.</span
                                >
                            </div>

                            <div ref="messagesContainer" class="crm-chat-messages min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain p-4">
                                <div v-if="loading" class="py-8 text-center text-muted-foreground">Cargando chat...</div>
                                <div v-else-if="filteredMessages.length === 0" class="py-8 text-center text-muted-foreground">
                                    Sin mensajes
                                </div>
                                <div
                                    v-for="message in filteredMessages"
                                    :key="message.id"
                                    class="flex"
                                    :class="message.direction === 'incoming' ? 'justify-start' : 'justify-end'"
                                >
                                    <div
                                        class="max-w-xs px-4 py-2.5 lg:max-w-md"
                                        :class="
                                            message.direction === 'incoming' ? 'chat-bubble-in' : 'chat-bubble-out'
                                        "
                                    >
                                        <div v-if="message.metadata?.image_url" class="mb-2">
                                            <a :href="`/whatsapp-media/proxy?url=${encodeURIComponent(message.metadata.image_url)}`" target="_blank" rel="noopener noreferrer">
                                                <img :src="`/whatsapp-media/proxy?url=${encodeURIComponent(message.metadata.image_url)}`" class="max-w-full rounded-md object-contain max-h-48" alt="Imagen enviada por el cliente" />
                                            </a>
                                        </div>
                                        <p v-if="message.content" class="whitespace-pre-wrap break-words text-xs leading-relaxed sm:text-sm">{{ message.content }}</p>
                                        <div
                                            class="mt-1 flex items-center justify-between gap-4 text-[10px]"
                                            :class="
                                                message.direction === 'incoming'
                                                    ? 'text-muted-foreground'
                                                    : 'chat-bubble-meta'
                                            "
                                        >
                                            <span>
                                                {{
                                                    new Date(message.created_at).toLocaleTimeString([], {
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })
                                                }}
                                            </span>
                                            <span class="flex items-center gap-0.5 capitalize italic">
                                                <Bot
                                                    v-if="
                                                        message.direction === 'outgoing' &&
                                                        message.metadata &&
                                                        !message.metadata.sent_via_job &&
                                                        !message.metadata.debug
                                                    "
                                                    class="inline h-3 w-3"
                                                />
                                                {{
                                                    message.direction === 'incoming'
                                                        ? ''
                                                        : message.status === 'pending'
                                                          ? 'enviando...'
                                                          : message.status === 'failed'
                                                            ? 'fallido'
                                                            : ''
                                                }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="message.direction === 'outgoing' && message.metadata?.send_error"
                                            class="mt-1 text-[9px] leading-snug opacity-90"
                                            :class="message.status === 'failed' ? 'chat-bubble-error' : 'opacity-90'"
                                        >
                                            {{ message.metadata.send_error }}
                                        </p>
                                        <div v-if="message.direction === 'outgoing' && message.status === 'failed'" class="mt-1 flex justify-end">
                                            <button
                                                type="button"
                                                class="rounded-md bg-white/15 px-2 py-0.5 text-[10px] font-semibold text-white hover:bg-white/25 disabled:opacity-50"
                                                :disabled="retryingMessageIds.includes(message.id)"
                                                @click="retryMessage(message)"
                                            >
                                                {{ retryingMessageIds.includes(message.id) ? 'Reintentando...' : 'Reintentar' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="crm-chat-input-bar shrink-0 border-t p-4">
                                <div
                                    v-if="awaitingPaymentValidation"
                                    class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950/40"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0 text-xs text-emerald-900 dark:text-emerald-100">
                                            <p class="font-semibold">Validar comprobante de pago</p>
                                            <p class="mt-0.5 text-emerald-800/90 dark:text-emerald-200/90">
                                                Pedido #{{ salesContext?.payment_validation?.order_id ?? '—' }}
                                                <span v-if="salesContext?.payment_validation?.order_total != null">
                                                    · S/ {{ salesContext.payment_validation.order_total.toFixed(2) }}
                                                </span>
                                            </p>
                                            <a
                                                v-if="salesContext?.payment_validation?.payment_proof_url"
                                                :href="`/whatsapp-media/proxy?url=${encodeURIComponent(salesContext.payment_validation.payment_proof_url)}`"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-1 inline-block text-[11px] font-medium text-emerald-700 underline dark:text-emerald-300"
                                            >
                                                Ver captura del cliente
                                            </a>
                                        </div>
                                        <button
                                            type="button"
                                            class="shrink-0 rounded-full bg-emerald-600 px-5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="validatingPayment"
                                            @click="validatePayment"
                                        >
                                            {{ validatingPayment ? 'Validando…' : '✓ Pago validado' }}
                                        </button>
                                    </div>
                                    <p
                                        v-if="paymentValidationError"
                                        class="mt-2 text-[11px] font-medium text-red-700 dark:text-red-300"
                                        role="alert"
                                    >
                                        {{ paymentValidationError }}
                                    </p>
                                </div>
                                <div
                                    v-if="currentConversationMode === 'bot'"
                                    class="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-2 dark:border-amber-700 dark:bg-amber-900/20"
                                >
                                    <Bot class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                    <span class="text-xs text-amber-800 dark:text-amber-200">
                                        🤖 Bot IA activo. Cambia a modo <strong>Humano</strong> para responder.
                                    </span>
                                </div>
                                <div
                                    v-if="sendError"
                                    class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
                                    role="alert"
                                >
                                    {{ sendError }}
                                </div>
                                <div class="flex gap-2">
                                    <input
                                        v-model="newMessage"
                                        type="text"
                                        class="chat-input flex-1 rounded-full border px-4 py-2 text-xs text-gray-900 shadow-sm placeholder:text-gray-400 disabled:bg-gray-100 disabled:opacity-50 dark:placeholder:text-[hsl(var(--wa-muted-fg))] dark:disabled:opacity-50 sm:text-sm"
                                        :disabled="sending || currentConversationMode === 'bot'"
                                        :placeholder="
                                            currentConversationMode === 'bot'
                                                ? 'Cambia a modo Humano para escribir...'
                                                : 'Escribe tu respuesta como asesor...'
                                        "
                                        @keyup.enter="sendMessage"
                                    />
                                    <button
                                        type="button"
                                        class="rounded-full px-5 py-2 text-xs font-semibold text-white shadow-sm transition disabled:opacity-50 sm:text-sm"
                                        :class="
                                            currentConversationMode === 'bot'
                                                ? 'cursor-not-allowed bg-gray-400'
                                                : 'chat-accent-bg'
                                        "
                                        :disabled="sending || !newMessage.trim() || currentConversationMode === 'bot'"
                                        @click="sendMessage"
                                    >
                                        {{ sending ? '...' : currentConversationMode === 'bot' ? '🔒' : 'Enviar' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CRM Sidebar -->
                    <div
                        v-if="customerDetails"
                        class="crm-chat-panel lg:col-span-4"
                    >
                        <div class="crm-chat-panel-header">
                            <h3 class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-foreground">
                                <User class="chat-accent-text h-4 w-4" />
                                Perfil CRM
                            </h3>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-green-600 px-2 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-green-500"
                                :disabled="savingCustomer"
                                @click="saveCustomerProfile"
                            >
                                <Save class="h-3 w-3" />
                                Guardar
                            </button>
                        </div>

                        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain p-4 text-xs">
                            <div
                                v-if="profileMessage"
                                class="rounded border border-green-200 bg-green-50 px-2 py-1.5 text-[10px] text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300"
                                role="status"
                            >
                                {{ profileMessage }}
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block font-semibold text-muted-foreground">Nombre</label>
                                    <input
                                        v-model="customerDetails.name"
                                        type="text"
                                        placeholder="Ingrese nombre"
                                        class="chat-field w-full text-gray-900 dark:text-[hsl(var(--wa-bubble-in-fg))]"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block font-semibold text-muted-foreground">Email</label>
                                    <input
                                        v-model="customerDetails.email"
                                        type="email"
                                        placeholder="correo@ejemplo.com"
                                        class="chat-field w-full text-gray-900 dark:text-[hsl(var(--wa-bubble-in-fg))]"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block font-semibold text-muted-foreground">Embudo (Segmento)</label>
                                    <select
                                        v-model="customerDetails.segment"
                                        class="chat-field w-full text-gray-900 dark:text-[hsl(var(--wa-bubble-in-fg))]"
                                    >
                                        <option value="lead">Lead (Frío)</option>
                                        <option value="interested">Interesado (Caliente)</option>
                                        <option value="considering">Considerando</option>
                                        <option value="repeat_customer">Cliente Frecuente</option>
                                    </select>
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-lg border border-gray-150 bg-gray-50 p-2.5 dark:border-gray-750 dark:bg-gray-900/50"
                                >
                                    <span class="font-semibold text-muted-foreground">Valor Total Ventas (LTV)</span>
                                    <span class="text-sm font-bold text-foreground">
                                        S/ {{ parseFloat(customerDetails.lifetime_value).toFixed(2) }}
                                    </span>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 pt-4 dark:border-gray-700">
                                <label class="mb-1 flex items-center gap-0.5 font-semibold text-muted-foreground">
                                    <Tag class="h-3.5 w-3.5" />
                                    Etiquetas
                                </label>
                                <div class="mb-2 flex flex-wrap gap-1">
                                    <span
                                        v-for="(tag, idx) in customerDetails.tags || []"
                                        :key="idx"
                                        class="chat-tag inline-flex items-center gap-0.5 rounded border px-2 py-0.5 font-semibold"
                                    >
                                        {{ tag }}
                                        <button type="button" class="ml-0.5 hover:text-red-500" @click="removeTag(idx)">×</button>
                                    </span>
                                </div>
                                <input
                                    type="text"
                                    placeholder="Escribe etiqueta + enter"
                                    class="chat-field w-full text-gray-900 dark:text-[hsl(var(--wa-bubble-in-fg))]"
                                    @keyup.enter="addTag"
                                />
                            </div>

                            <div
                                v-if="enSeguimientoPedido"
                                class="border-t border-gray-100 pt-4 dark:border-gray-700"
                            >
                                <label class="mb-2 flex items-center gap-0.5 font-semibold text-muted-foreground">
                                    <ShoppingBag class="h-3.5 w-3.5" />
                                    Pedido confirmado
                                </label>
                                <div
                                    v-if="salesContext?.payment_validation?.order_id"
                                    class="chat-profile-card rounded border p-2 text-[10px]"
                                >
                                    <div class="font-semibold">
                                        Pedido #{{ salesContext.payment_validation.order_id }}
                                        <span v-if="salesContext.payment_validation.order_total != null">
                                            · S/ {{ salesContext.payment_validation.order_total.toFixed(2) }}
                                        </span>
                                    </div>
                                    <div
                                        v-for="(line, i) in salesContext.pedido_confirmado_items ?? []"
                                        :key="i"
                                        class="mt-1 text-gray-600 dark:text-gray-400"
                                    >
                                        {{ line.product }} · {{ line.color }} · {{ line.size }}
                                    </div>
                                    <p class="mt-2 text-muted-foreground">
                                        El carrito del bot ya se cerró. Usa el historial de pedidos abajo si necesitas más detalle.
                                    </p>
                                </div>
                            </div>

                            <div v-else class="border-t border-gray-100 pt-4 dark:border-gray-700">
                                <label class="mb-2 flex items-center gap-0.5 font-semibold text-muted-foreground">
                                    <ShoppingBag class="h-3.5 w-3.5" />
                                    Galería por color
                                </label>

                                <div v-if="photoError" class="mb-2 rounded border border-red-200 bg-red-50 p-2 text-[10px] text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300" role="alert">
                                    {{ photoError }}
                                </div>

                                <div v-if="loadingSalesContext" class="py-2 italic text-gray-400">Cargando catálogo...</div>

                                <div
                                    v-else-if="salesContext?.handoff?.summary"
                                    class="mb-2 rounded border border-amber-200 bg-amber-50 p-2 text-[10px] text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300"
                                >
                                    {{ salesContext.handoff.summary }}
                                </div>

                                <div
                                    v-if="salesContext?.current_product"
                                    class="chat-profile-card mb-2 rounded border p-2"
                                >
                                    <div class="chat-profile-card-title font-semibold">
                                        {{ salesContext.current_product.name }}
                                    </div>
                                    <div class="mt-0.5 text-[10px] text-gray-600 dark:text-gray-400">
                                        S/ {{ salesContext.current_product.price.toFixed(2) }}
                                        <span v-if="salesContext.current_product.selected_color">
                                            · Color: {{ salesContext.current_product.selected_color }}</span
                                        >
                                        <span v-if="salesContext.current_product.selected_size">
                                            · Talla: {{ salesContext.current_product.selected_size }}</span
                                        >
                                    </div>
                                    <div v-if="salesContext.etapa_venta_label || salesContext.sales_stage" class="mt-1 text-[10px] text-gray-500">
                                        Etapa: {{ salesContext.etapa_venta_label || salesContext.sales_stage }}
                                    </div>
                                    <div
                                        v-if="salesContext.stock_por_color?.length"
                                        class="mt-2 space-y-0.5 text-[10px] text-gray-600 dark:text-gray-400"
                                    >
                                        <div class="font-medium text-gray-500">Stock por color</div>
                                        <div v-for="row in salesContext.stock_por_color" :key="row.color">
                                            <span class="capitalize">{{ row.color }}</span>:
                                            <span v-if="row.agotado">agotado</span>
                                            <span v-else>{{ row.tallas.join(', ') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="salesContext?.carrito?.length"
                                    class="chat-profile-card mb-2 rounded border p-2"
                                >
                                    <div class="chat-profile-card-title font-semibold">Carrito</div>
                                    <div
                                        v-for="(line, i) in salesContext.carrito"
                                        :key="i"
                                        class="text-[10px] text-gray-600 dark:text-gray-400"
                                    >
                                        {{ line.producto }} · {{ line.color }} · {{ line.talla }} — S/{{ line.precio }}
                                    </div>
                                    <div
                                        v-if="salesContext.carrito_subtotal != null"
                                        class="mt-1 text-[10px] font-medium"
                                    >
                                        Subtotal S/{{ salesContext.carrito_subtotal.toFixed(0) }}
                                    </div>
                                </div>

                                <div v-if="galleryColors.length" class="grid grid-cols-2 gap-2">
                                    <div
                                        v-for="colorItem in galleryColors"
                                        :key="colorItem.color"
                                        class="overflow-hidden rounded border border-border bg-white dark:border-gray-700 dark:bg-gray-900/30"
                                        :class="
                                            salesContext?.current_product?.selected_color === colorItem.color && !galleryProductOverride
                                                ? 'ring-2 chat-accent-ring'
                                                : ''
                                        "
                                    >
                                        <img
                                            v-if="colorItem.image_url"
                                            :src="colorItem.image_url"
                                            :alt="colorItem.color"
                                            class="h-20 w-full bg-gray-100 object-cover dark:bg-gray-800"
                                        />
                                        <div v-else class="flex h-20 w-full items-center justify-center bg-gray-100 text-[10px] text-gray-400 dark:bg-gray-800">
                                            Sin foto
                                        </div>
                                        <div class="space-y-1 p-1.5">
                                            <div class="font-semibold capitalize text-gray-800 dark:text-gray-200">{{ colorItem.color }}</div>
                                            <div class="text-[9px] text-gray-500">{{ colorItem.stock_summary }}</div>
                                            <button
                                                v-if="colorItem.image_url"
                                                type="button"
                                                class="chat-accent-bg w-full rounded px-1.5 py-1 text-[10px] font-semibold disabled:opacity-50"
                                                :disabled="sendingPhotoColor === colorItem.color || currentConversationMode === 'bot'"
                                                @click="sendColorPhotoToCustomer(colorItem, galleryProductName ?? undefined)"
                                            >
                                                {{ sendingPhotoColor === colorItem.color ? 'Enviando...' : 'Enviar foto' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="mb-2 text-[10px] italic text-gray-400">
                                    Sin vestido activo. Elige uno de «Vistos recientemente».
                                </div>

                                <button
                                    v-if="galleryProductOverride"
                                    type="button"
                                    class="chat-accent-text mb-2 text-[10px] hover:underline"
                                    @click="galleryProductOverride = null"
                                >
                                    Volver al vestido del bot
                                </button>

                                <div v-if="salesContext?.recent_products?.length" class="mt-3">
                                    <div class="mb-1 text-[10px] font-semibold text-gray-500">Vistos recientemente</div>
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <button
                                            v-for="p in salesContext.recent_products"
                                            :key="p.id"
                                            type="button"
                                            class="w-16 shrink-0 rounded text-left"
                                            :class="galleryProductOverride?.id === p.id ? 'ring-2 chat-accent-ring' : ''"
                                            @click="galleryProductOverride = p"
                                        >
                                            <img
                                                v-if="p.thumbnail"
                                                :src="p.thumbnail"
                                                :alt="p.name"
                                                class="h-14 w-16 rounded border border-border object-cover dark:border-gray-700"
                                            />
                                            <div class="mt-0.5 truncate text-[9px]">{{ p.name }}</div>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 pt-4 dark:border-gray-700">
                                <label class="mb-1 flex items-center gap-0.5 font-semibold text-muted-foreground">
                                    <FileText class="h-3.5 w-3.5" />
                                    Notas Internas
                                </label>
                                <textarea
                                    v-model="customerDetails.notes"
                                    rows="3"
                                    placeholder="Comentarios sobre el cliente..."
                                    class="chat-field w-full text-gray-900 dark:text-[hsl(var(--wa-bubble-in-fg))]"
                                />
                            </div>

                            <div class="border-t border-gray-100 pt-4 dark:border-gray-700">
                                <label class="mb-2 flex items-center gap-0.5 font-semibold text-muted-foreground">
                                    <History class="h-3.5 w-3.5" />
                                    Historial Pedidos ({{ customerDetails.orders.length }})
                                </label>
                                <div class="max-h-[220px] space-y-3 overflow-y-auto pr-1">
                                    <div
                                        v-for="order in customerDetails.orders"
                                        :key="order.id"
                                        class="space-y-1.5 rounded border border-border bg-gray-50/50 p-2.5 dark:border-gray-700 dark:bg-gray-900/10"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-gray-800 dark:text-gray-200">#{{ order.id }}</span>
                                            <span
                                                class="rounded border px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                                                :class="
                                                    order.status === 'delivered'
                                                        ? 'border-green-200 bg-green-50 text-green-700'
                                                        : order.status === 'pending'
                                                          ? 'border-yellow-200 bg-yellow-50 text-yellow-700'
                                                          : 'border-blue-200 bg-blue-50 text-blue-700'
                                                "
                                            >
                                                {{ order.status }}
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-muted-foreground">
                                            {{ new Date(order.created_at).toLocaleDateString() }}
                                        </div>
                                        <div class="space-y-0.5">
                                            <div
                                                v-for="item in order.items"
                                                :key="item.id"
                                                class="flex justify-between text-[11px] text-gray-600 dark:text-gray-300"
                                            >
                                                <span class="truncate pr-4">{{ item.product.name }} ({{ item.color }})</span>
                                                <span>x{{ item.qty }}</span>
                                            </div>
                                        </div>
                                        <div
                                            class="flex items-center justify-between border-t border-gray-100 pt-1 font-bold text-gray-800 dark:border-gray-700 dark:text-white"
                                        >
                                            <span>Total:</span>
                                            <span>S/ {{ parseFloat(order.amount_total).toFixed(2) }}</span>
                                        </div>
                                    </div>
                                    <div v-if="customerDetails.orders.length === 0" class="py-3 text-center italic text-muted-foreground">
                                        Ninguna orden registrada
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
