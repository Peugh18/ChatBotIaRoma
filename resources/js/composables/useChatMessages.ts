import { computed, nextTick, ref, type Ref } from 'vue';
import type { ChatConversation, ChatFilterType, ChatInboxTab, ChatMessage } from '@/types/chat';

export function useChatMessages(messagesContainer: Ref<HTMLElement | null>) {
    const messages = ref<ChatMessage[]>([]);
    const loading = ref(true);
    const sending = ref(false);
    const retryingMessageIds = ref<number[]>([]);
    const newMessage = ref('');
    const filterType = ref<ChatFilterType>('all');
    const inboxTab = ref<ChatInboxTab>('active');

    const scrollToBottom = () => {
        nextTick(() => {
            if (messagesContainer.value) {
                messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
            }
        });
    };

    const isNearBottom = (): boolean => {
        if (!messagesContainer.value) {
            return true;
        }
        const el = messagesContainer.value;
        return el.scrollHeight - el.scrollTop - el.clientHeight < 100;
    };

    const mergeServerMessages = (data: ChatMessage[]) => {
        const wasAtBottom = isNearBottom();
        const realMessageIds = new Set(data.map((m) => m.message_id));

        const cleanedMessages = messages.value.filter((m) => {
            const isTemp = m.message_id?.startsWith('temp_');
            if (!isTemp) {
                return true;
            }
            return !realMessageIds.has(m.message_id);
        });

        const serverIds = new Set(data.map((m) => m.id));
        const pendingLocal = cleanedMessages.filter(
            (m) =>
                m.direction === 'outgoing' &&
                m.status === 'pending' &&
                !serverIds.has(m.id) &&
                m.message_id?.startsWith('temp_'),
        );

        messages.value = [...data, ...pendingLocal];

        if (wasAtBottom) {
            scrollToBottom();
        }
    };

    const fetchMessages = async () => {
        try {
            const response = await fetch('/api/messages', {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();

            if (Array.isArray(data)) {
                mergeServerMessages(data);
            } else {
                console.error('Messages payload is not an array:', data);
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        } finally {
            loading.value = false;
        }
    };

    const allConversations = computed((): ChatConversation[] => {
        const map = new Map<string, ChatConversation>();

        for (const msg of messages.value) {
            const existing = map.get(msg.phone_number);
            if (!existing || new Date(msg.created_at) > new Date(existing.lastTime)) {
                const postPedido = Boolean(msg.conversation_state?.asesor_post_pedido);
                map.set(msg.phone_number, {
                    phone: msg.phone_number,
                    name: msg.customer?.name || msg.customer_name,
                    lastMessage: msg.content,
                    lastTime: msg.created_at,
                    count: messages.value.filter((m) => m.phone_number === msg.phone_number).length,
                    requires_human: msg.conversation_state?.requires_human ?? false,
                    is_auto_escalated: postPedido
                        ? false
                        : (msg.conversation_state?.is_auto_escalated ?? false),
                    asesor_post_pedido: postPedido,
                    customer_id: msg.customer_id,
                });
            }
        }

        return Array.from(map.values()).sort(
            (a, b) => new Date(b.lastTime).getTime() - new Date(a.lastTime).getTime(),
        );
    });

    const shippingInboxCount = computed(
        () => allConversations.value.filter((c) => c.asesor_post_pedido).length,
    );

    const conversations = computed((): ChatConversation[] => {
        let list = allConversations.value.filter((c) =>
            inboxTab.value === 'shipping' ? c.asesor_post_pedido : !c.asesor_post_pedido,
        );

        if (filterType.value === 'human') {
            list = list.filter((c) =>
                inboxTab.value === 'shipping'
                    ? c.asesor_post_pedido
                    : c.requires_human && !c.asesor_post_pedido,
            );
        } else if (filterType.value === 'ai') {
            list = list.filter((c) => !c.requires_human && !c.asesor_post_pedido);
        }

        return list;
    });

    const pushMessage = (message: ChatMessage) => {
        messages.value.push(message);
        scrollToBottom();
    };

    const replaceMessage = (tempId: number, real: ChatMessage) => {
        const index = messages.value.findIndex((m) => m.id === tempId);
        if (index !== -1) {
            messages.value.splice(index, 1, real);
        }
    };

    const markMessageFailed = (tempId: number) => {
        const index = messages.value.findIndex((m) => m.id === tempId);
        if (index !== -1) {
            messages.value[index].status = 'failed';
        }
    };

    return {
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
    };
}
