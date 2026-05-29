import { ref } from 'vue';
import type { BotMetrics, EscalationAlert } from '@/types/chat';

export function useBotMetricsPanel() {
    const botMetrics = ref<BotMetrics | null>(null);
    const escalationAlerts = ref<EscalationAlert[]>([]);

    const fetchBotMetrics = async () => {
        try {
            const response = await fetch('/api/bot-metrics', {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                botMetrics.value = await response.json();
            }
        } catch (error) {
            console.error('Error fetching bot metrics:', error);
        }
    };

    const pushEscalationAlert = (alert: EscalationAlert) => {
        escalationAlerts.value.unshift(alert);
    };

    return {
        botMetrics,
        escalationAlerts,
        fetchBotMetrics,
        pushEscalationAlert,
    };
}
