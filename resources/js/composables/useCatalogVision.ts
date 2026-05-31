import { onMounted, ref } from 'vue';
import { apiJson } from '@/composables/useApi';

export type CatalogVisionStats = {
    total_variants_with_photo: number;
    indexed_variants: number;
    pending_variants: number;
    indexed_percentage: number;
    model: string;
    min_similarity: number;
    top_k: number;
    vision_enabled: boolean;
    token_configured: boolean;
    public_url_configured: boolean;
    last_indexed_at: string | null;
};

export function useCatalogVision() {
    const stats = ref<CatalogVisionStats | null>(null);
    const loading = ref(false);
    const reindexing = ref(false);
    const error = ref<string | null>(null);
    const success = ref<string | null>(null);

    const fetchStats = async () => {
        loading.value = true;
        error.value = null;
        try {
            stats.value = await apiJson<CatalogVisionStats>('/api/catalog-vision/stats');
        } catch {
            error.value = 'No se pudo cargar el estado de visión del catálogo.';
        } finally {
            loading.value = false;
        }
    };

    const reindex = async (force = false) => {
        reindexing.value = true;
        error.value = null;
        success.value = null;
        try {
            const result = await apiJson<{ queued: number; message: string }>(
                `/api/catalog-vision/reindex${force ? '?force=1' : ''}`,
                { method: 'POST' },
            );
            success.value = result.message;
            await fetchStats();
        } catch {
            error.value = 'No se pudo encolar la indexación. Revisa el token Hugging Face y la cola.';
        } finally {
            reindexing.value = false;
        }
    };

    onMounted(fetchStats);

    return {
        stats,
        loading,
        reindexing,
        error,
        success,
        fetchStats,
        reindex,
    };
}
