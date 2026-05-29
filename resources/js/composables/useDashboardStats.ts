import { onMounted, ref } from 'vue';
import { apiJson } from '@/composables/useApi';

export interface RecentOrder {
    id: number;
    amount_total: string;
    status: string;
    district: string | null;
    shipping_method: string;
    created_at: string;
    customer: { name: string | null; phone_number: string } | null;
}

export interface DashboardStats {
    total_sales: number;
    average_ticket: number;
    open_conversations: number;
    total_customers: number;
    conversion_rate: number;
    recent_orders: RecentOrder[];
}

const emptyStats = (): DashboardStats => ({
    total_sales: 0,
    average_ticket: 0,
    open_conversations: 0,
    total_customers: 0,
    conversion_rate: 0,
    recent_orders: [],
});

export function useDashboardStats() {
    const stats = ref<DashboardStats>(emptyStats());
    const loading = ref(true);
    const error = ref<string | null>(null);

    const fetchStats = async () => {
        loading.value = true;
        error.value = null;
        try {
            stats.value = await apiJson<DashboardStats>('/api/dashboard-stats');
        } catch {
            error.value = 'No se pudieron cargar las estadísticas.';
            stats.value = emptyStats();
        } finally {
            loading.value = false;
        }
    };

    onMounted(fetchStats);

    return { stats, loading, error, fetchStats };
}
