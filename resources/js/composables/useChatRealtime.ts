import { onMounted, onUnmounted } from 'vue';
import type { ChatMessage, EscalationAlert } from '@/types/chat';

interface ConversationModePayload {
    phone_number: string;
    mode: 'bot' | 'human';
    requires_human?: boolean;
    is_auto_escalated?: boolean;
    asesor_post_pedido?: boolean;
}

interface ChatRealtimeHandlers {
    onPoll: () => void;
    onMessageReceived: (message: ChatMessage) => void;
    onEscalation: (alert: EscalationAlert) => void;
    onConversationMode?: (payload: ConversationModePayload) => void;
}

export function useChatRealtime(handlers: ChatRealtimeHandlers) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    let echo: any = null;
    let pollingInterval: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        handlers.onPoll();

        pollingInterval = setInterval(handlers.onPoll, 30_000);

        if (typeof window !== 'undefined' && (window as Window & { Echo?: unknown }).Echo) {
            try {
                echo = (window as Window & { Echo: typeof echo }).Echo;

                echo.private('crm.messages').listen('.message.received', (e: { message: ChatMessage }) => {
                    handlers.onMessageReceived(e.message);
                });

                echo.private('crm.messages').listen('.conversation.mode', (e: ConversationModePayload) => {
                    handlers.onConversationMode?.(e);
                });

                echo.private('crm.escalations').listen(
                    '.human.escalation',
                    (e: { phone_number: string; handoff?: { summary?: string }; timestamp?: string }) => {
                        handlers.onEscalation({
                            phone_number: e.phone_number,
                            summary: e.handoff?.summary,
                            at: e.timestamp ?? new Date().toISOString(),
                        });
                    },
                );
            } catch (error) {
                console.error('Error configuring Echo:', error);
            }
        }
    });

    onUnmounted(() => {
        if (echo) {
            echo.leave('crm.messages');
            echo.leave('crm.escalations');
        }
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    });
}
