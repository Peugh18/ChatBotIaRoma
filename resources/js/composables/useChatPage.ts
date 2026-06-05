import { computed, ref, watch } from 'vue';
import type { ChatMessage } from '@/types/chat';
import { useChatMessages } from '@/composables/useChatMessages';
import { useConversationMode } from '@/composables/useConversationMode';
import { useChatCustomer } from '@/composables/useChatCustomer';
import { useSalesContext } from '@/composables/useSalesContext';
import { useChatOutbound } from '@/composables/useChatOutbound';
import { useBotMetricsPanel } from '@/composables/useBotMetricsPanel';
import { useChatRealtime } from '@/composables/useChatRealtime';
import { useCsrfToken } from '@/composables/useCsrfToken';
import { apiJson } from '@/composables/useApi';

export function useChatPage() {
    const selectedPhone = ref<string | null>(null);
    const messagesContainer = ref<HTMLElement | null>(null);

    const {
        messages,
        loading,
        sending,
        retryingMessageIds,
        newMessage,
        filterType,
        inboxTab,
        allConversations,
        shippingInboxCount,
        conversations,
        fetchMessages,
        scrollToBottom,
        pushMessage,
        replaceMessage,
        markMessageFailed,
    } = useChatMessages(messagesContainer);

    const selectedCustomerId = computed(() => {
        const active = conversations.value.find((c) => c.phone === selectedPhone.value);
        return active?.customer_id ?? null;
    });

    const filteredMessages = computed(() => {
        if (!selectedPhone.value) {
            return [];
        }
        return messages.value.filter((m) => m.phone_number === selectedPhone.value);
    });

    const { customerDetails, savingCustomer, profileMessage, saveCustomerProfile, addTag, removeTag, loadCustomerIfNeeded } =
        useChatCustomer(selectedCustomerId, fetchMessages);

    const { currentConversationMode, isAutoEscalated, asesorPostPedido, fetchConversationMode, updateConversationMode } =
        useConversationMode(selectedPhone, conversations, customerDetails, fetchMessages);

    const {
        salesContext,
        loadingSalesContext,
        sendingPhotoColor,
        galleryProductOverride,
        galleryColors,
        galleryProductName,
        photoError,
        fetchSalesContext,
        resetGallery,
        clearSalesContext,
        sendColorPhotoToCustomer,
        validatePayment,
        validatingPayment,
        paymentValidationError,
        sendCardPaymentLink,
        sendingCardLink,
        cardLinkError,
        cardPaymentLinkInput,
    } = useSalesContext(
        selectedPhone,
        currentConversationMode,
        async (afterPayment?: boolean) => {
            await fetchMessages();
            if (selectedPhone.value) {
                await fetchConversationMode(selectedPhone.value);
            }
            if (afterPayment) {
                asesorPostPedido.value = true;
                isAutoEscalated.value = false;
                inboxTab.value = 'shipping';
            }
        },
        isAutoEscalated,
        asesorPostPedido,
    );

    const { sendError, sendMessage, retryMessage } = useChatOutbound({
        selectedPhone,
        currentConversationMode,
        customerDetails,
        sending,
        newMessage,
        pushMessage,
        replaceMessage,
        markMessageFailed,
        scrollToBottom,
        onMessageSent: fetchMessages,
        forceHumanMode: () => {
            if (currentConversationMode.value !== 'human') {
                currentConversationMode.value = 'human';
            }
            isAutoEscalated.value = false;
        },
    });

    const { botMetrics, escalationAlerts, fetchBotMetrics, pushEscalationAlert } = useBotMetricsPanel();
    const cardLinkQueue = ref<Array<{
        phone_number: string;
        customer_name: string | null;
        order_id: number | null;
        order_total: number | null;
    }>>([]);

    const fetchCardLinkQueue = async () => {
        try {
            const data = await apiJson<{
                card_payment_link_queue?: typeof cardLinkQueue.value;
            }>('/api/dashboard-stats');
            cardLinkQueue.value = data.card_payment_link_queue ?? [];
        } catch {
            cardLinkQueue.value = [];
        }
    };
    const { refreshCsrfToken } = useCsrfToken();

    const handleIncomingMessage = (incoming: ChatMessage) => {
        const exists = messages.value.some(
            (m) => m.id === incoming.id || m.message_id === incoming.message_id,
        );
        if (!exists) {
            messages.value.push(incoming);
        }
        scrollToBottom();

        if (selectedPhone.value === incoming.phone_number) {
            if (selectedCustomerId.value) {
                loadCustomerIfNeeded();
            }
            fetchSalesContext(incoming.phone_number);
        }
    };

    const pollChat = () => {
        fetchMessages();
        fetchBotMetrics();
        fetchCardLinkQueue();
        if (selectedCustomerId.value && !savingCustomer.value) {
            loadCustomerIfNeeded();
        }
        if (selectedPhone.value) {
            fetchSalesContext(selectedPhone.value);
        }
    };

    useChatRealtime({
        onPoll: pollChat,
        onMessageReceived: handleIncomingMessage,
        onEscalation: (alert) => {
            pushEscalationAlert(alert);
            fetchMessages();
        },
        onConversationMode: (payload) => {
            const conv = conversations.value.find((c) => c.phone === payload.phone_number);
            const human = payload.mode === 'human' || payload.requires_human === true;
            if (conv) {
                conv.requires_human = human;
                conv.is_auto_escalated = payload.is_auto_escalated ?? false;
                if ('asesor_post_pedido' in payload) {
                    conv.asesor_post_pedido = Boolean(payload.asesor_post_pedido);
                    if (conv.asesor_post_pedido) {
                        conv.is_auto_escalated = false;
                    }
                }
            }
            if (selectedPhone.value === payload.phone_number) {
                currentConversationMode.value = human ? 'human' : 'bot';
                isAutoEscalated.value = payload.is_auto_escalated ?? false;
                if ('asesor_post_pedido' in payload) {
                    asesorPostPedido.value = Boolean(payload.asesor_post_pedido);
                    if (!payload.asesor_post_pedido) {
                        inboxTab.value = 'active';
                    }
                } else {
                    void fetchConversationMode(payload.phone_number);
                }
                if (customerDetails.value?.conversation_state) {
                    customerDetails.value.conversation_state.requires_human = human;
                }
            }
        },
    });

    watch(selectedPhone, (phone) => {
        scrollToBottom();
        customerDetails.value = null;
        clearSalesContext();
        if (phone) {
            const conv = allConversations.value.find((c) => c.phone === phone);
            if (conv?.asesor_post_pedido) {
                inboxTab.value = 'shipping';
            }
            fetchConversationMode(phone);
            fetchSalesContext(phone);
        }
    });

    watch(selectedCustomerId, () => {
        loadCustomerIfNeeded();
    });

    watch(
        allConversations,
        (list) => {
            if (selectedPhone.value) {
                return;
            }
            const phone = new URLSearchParams(window.location.search).get('phone');
            const conv = phone ? list.find((c) => c.phone === phone) : undefined;
            if (conv) {
                if (conv.asesor_post_pedido) {
                    inboxTab.value = 'shipping';
                }
                selectedPhone.value = phone;
            }
        },
        { deep: true },
    );

    const retryMessageForUi = (message: ChatMessage) =>
        retryMessage(message, retryingMessageIds, fetchMessages);

    refreshCsrfToken();

    return {
        selectedPhone,
        messagesContainer,
        messages,
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
        asesorPostPedido,
        sendError,
        saveCustomerProfile,
        addTag,
        removeTag,
        updateConversationMode,
        sendMessage,
        retryMessage: retryMessageForUi,
        sendColorPhotoToCustomer,
        validatePayment,
        validatingPayment,
        paymentValidationError,
        sendCardPaymentLink,
        sendingCardLink,
        cardLinkError,
        cardPaymentLinkInput,
        cardLinkQueue,
    };
}
