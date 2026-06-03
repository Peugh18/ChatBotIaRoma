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

export interface PaymentValidationItem {
    phone_number: string;
    customer_name: string | null;
    order_id: number | null;
    order_total: number | null;
    payment_proof_url: string | null;
    waiting_since: string | null;
}

export interface DashboardStats {
    total_sales: number;
    average_ticket: number;
    open_conversations: number;
    total_customers: number;
    conversion_rate: number;
    recent_orders: RecentOrder[];
    payment_validation_count: number;
    payment_validation_queue: PaymentValidationItem[];
}

const emptyStats = (): DashboardStats => ({
    total_sales: 0,
    average_ticket: 0,
    open_conversations: 0,
    total_customers: 0,
    conversion_rate: 0,
    recent_orders: [],
    payment_validation_count: 0,
    payment_validation_queue: [],
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
