import { ref, type Ref } from 'vue';
import type { ChatMessage, CustomerDetails } from '@/types/chat';
import { useCsrfToken } from '@/composables/useCsrfToken';

interface OutboundDeps {
    selectedPhone: Ref<string | null>;
    currentConversationMode: Ref<'bot' | 'human'>;
    customerDetails: Ref<CustomerDetails | null>;
    sending: Ref<boolean>;
    newMessage: Ref<string>;
    pushMessage: (message: ChatMessage) => void;
    replaceMessage: (tempId: number, real: ChatMessage) => void;
    markMessageFailed: (tempId: number) => void;
    scrollToBottom: () => void;
    onMessageSent?: () => Promise<void>;
    forceHumanMode?: () => void;
}

export function useChatOutbound(deps: OutboundDeps) {
    const { refreshCsrfToken } = useCsrfToken();
    const sendError = ref<string | null>(null);

    const postMessage = async (phone: string, body: Record<string, unknown>, token: string) => {
        return fetch('/api/send-message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
    };

    const sendMessage = async () => {
        if (!deps.newMessage.value.trim() || !deps.selectedPhone.value || deps.sending.value) {
            return;
        }
        if (deps.currentConversationMode.value === 'bot') {
            sendError.value = 'Cambia a modo Humano para poder enviar mensajes.';
            return;
        }

        deps.sending.value = true;
        sendError.value = null;
        const messageContent = deps.newMessage.value;
        deps.newMessage.value = '';

        const tempId = Date.now();
        const optimisticMessage: ChatMessage = {
            id: tempId,
            message_id: `temp_${tempId}`,
            phone_number: deps.selectedPhone.value,
            customer_id: deps.customerDetails.value?.id ?? null,
            conversation_state_id: null,
            customer_name: deps.customerDetails.value?.name ?? null,
            content: messageContent,
            direction: 'outgoing',
            status: 'pending',
            created_at: new Date().toISOString(),
            metadata: null,
            customer: deps.customerDetails.value
                ? { id: deps.customerDetails.value.id, name: deps.customerDetails.value.name }
                : null,
            conversation_state: null,
        };

        deps.pushMessage(optimisticMessage);

        try {
            let token = await refreshCsrfToken();
            let response = await postMessage(
                deps.selectedPhone.value,
                { phone_number: deps.selectedPhone.value, content: messageContent },
                token,
            );

            if (response.status === 419) {
                token = await refreshCsrfToken();
                response = await postMessage(
                    deps.selectedPhone.value,
                    { phone_number: deps.selectedPhone.value, content: messageContent },
                    token,
                );
            }

            if (response.ok) {
                const result = (await response.json()) as { data?: ChatMessage };
                if (result.data) {
                    deps.replaceMessage(tempId, result.data);
                }
                deps.forceHumanMode?.();
                deps.scrollToBottom();
                await deps.onMessageSent?.();
            } else {
                deps.markMessageFailed(tempId);
                sendError.value = 'Error al enviar mensaje. Recarga si persiste.';
            }
        } catch (error) {
            console.error('Error sending message:', error);
            deps.markMessageFailed(tempId);
            sendError.value = 'Error al enviar mensaje';
        } finally {
            deps.sending.value = false;
        }
    };

    const retryMessage = async (
        message: ChatMessage,
        retryingMessageIds: Ref<number[]>,
        refetch: () => Promise<void>,
    ) => {
        if (retryingMessageIds.value.includes(message.id)) {
            return;
        }

        retryingMessageIds.value.push(message.id);

        try {
            const token = await refreshCsrfToken();
            const response = await fetch(`/api/messages/${message.id}/retry`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
            });

            if (response.ok) {
                await refetch();
                deps.scrollToBottom();
            } else {
                sendError.value = 'No se pudo reintentar el mensaje';
            }
        } catch (error) {
            console.error('Error retrying message:', error);
            sendError.value = 'Error al reintentar el mensaje';
        } finally {
            retryingMessageIds.value = retryingMessageIds.value.filter((id) => id !== message.id);
        }
    };

    return {
        sendError,
        sendMessage,
        retryMessage,
    };
}
