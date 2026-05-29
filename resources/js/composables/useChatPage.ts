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

    const { currentConversationMode, isAutoEscalated, fetchConversationMode, updateConversationMode } =
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
    } = useSalesContext(selectedPhone, currentConversationMode, fetchMessages, isAutoEscalated);

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
    });

    watch(selectedPhone, (phone) => {
        scrollToBottom();
        customerDetails.value = null;
        clearSalesContext();
        if (phone) {
            fetchConversationMode(phone);
            fetchSalesContext(phone);
        }
    });

    watch(selectedCustomerId, () => {
        loadCustomerIfNeeded();
    });

    watch(
        conversations,
        (list) => {
            if (selectedPhone.value) {
                return;
            }
            const phone = new URLSearchParams(window.location.search).get('phone');
            if (phone && list.some((c) => c.phone === phone)) {
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
        retryMessage: retryMessageForUi,
        sendColorPhotoToCustomer,
        validatePayment,
        validatingPayment,
        paymentValidationError,
    };
}
