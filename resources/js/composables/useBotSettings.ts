import { onMounted } from 'vue';
import type { BotSettings } from '@/types/settings';
import { apiJson, useApiResource } from '@/composables/useApi';

export function useBotSettings() {
    const { data: settings, loading, saving, error, success, clearMessages } =
        useApiResource<BotSettings>();

    const fetchSettings = async () => {
        loading.value = true;
        clearMessages();
        try {
            settings.value = await apiJson<BotSettings>('/api/bot-settings');
        } catch {
            error.value = 'No se pudo cargar la configuración del bot.';
        } finally {
            loading.value = false;
        }
    };

    const saveSettings = async () => {
        if (!settings.value) {
            return;
        }

        saving.value = true;
        clearMessages();
        try {
            settings.value = await apiJson<BotSettings>('/api/bot-settings', {
                method: 'PUT',
                body: JSON.stringify(settings.value),
            });
            success.value = 'Configuración guardada correctamente.';
        } catch (e) {
            error.value =
                e instanceof Error && e.message.includes('Sesión')
                    ? e.message
                    : 'Error al guardar la configuración.';
        } finally {
            saving.value = false;
        }
    };

    onMounted(fetchSettings);

    return {
        settings,
        loading,
        saving,
        error,
        success,
        fetchSettings,
        saveSettings,
    };
}
