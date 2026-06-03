import { ref, type ComputedRef, type Ref } from 'vue';
import type { ChatConversation, CustomerDetails } from '@/types/chat';
import { useCsrfToken } from '@/composables/useCsrfToken';

export function useConversationMode(
    selectedPhone: Ref<string | null>,
    conversations: ComputedRef<ChatConversation[]>,
    customerDetails: Ref<CustomerDetails | null>,
    onAfterModeChange?: () => Promise<void>,
) {
    const currentConversationMode = ref<'bot' | 'human'>('bot');
    const isAutoEscalated = ref(false);
    const asesorPostPedido = ref(false);
    const { refreshCsrfToken } = useCsrfToken();

    const fetchConversationMode = async (phone: string) => {
        try {
            const response = await fetch(`/api/conversations/${phone}/mode`, {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                const data = (await response.json()) as {
                    mode: 'bot' | 'human';
                    is_auto_escalated: boolean;
                    asesor_post_pedido?: boolean;
                };
                currentConversationMode.value = data.mode;
                isAutoEscalated.value = data.is_auto_escalated;
                asesorPostPedido.value = Boolean(data.asesor_post_pedido);
            }
        } catch (error) {
            console.error('Error fetching conversation mode:', error);
        }
    };

    const postMode = async (phone: string, mode: 'bot' | 'human', token: string) => {
        return fetch(`/api/conversations/${phone}/mode`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ mode }),
        });
    };

    const updateConversationMode = async (mode: 'bot' | 'human') => {
        if (!selectedPhone.value) {
            return;
        }

        try {
            let token = await refreshCsrfToken();
            let response = await postMode(selectedPhone.value, mode, token);

            if (response.status === 419) {
                token = await refreshCsrfToken();
                response = await postMode(selectedPhone.value, mode, token);
            }

            if (!response.ok) {
                alert(
                    response.status === 419
                        ? 'Error al actualizar el modo. Recarga la página.'
                        : 'Error al actualizar el modo de respuesta',
                );
                return;
            }

            currentConversationMode.value = mode;
            isAutoEscalated.value = false;
            if (mode === 'bot') {
                asesorPostPedido.value = false;
            }

            const conv = conversations.value.find((c) => c.phone === selectedPhone.value);
            if (conv) {
                conv.requires_human = mode === 'human';
                conv.is_auto_escalated = false;
            }

            if (customerDetails.value) {
                if (!customerDetails.value.conversation_state) {
                    customerDetails.value.conversation_state = { requires_human: mode === 'human' };
                } else {
                    customerDetails.value.conversation_state.requires_human = mode === 'human';
                }
            }

            await onAfterModeChange?.();
        } catch (error) {
            console.error('Error updating conversation mode:', error);
        }
    };

    return {
        currentConversationMode,
        isAutoEscalated,
        asesorPostPedido,
        fetchConversationMode,
        updateConversationMode,
    };
}
