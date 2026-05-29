import { onMounted } from 'vue';
import {
    defaultCompanySettingsForm,
    type BusinessHoursForm,
    type CompanySettingsForm,
    type SocialNetworksForm,
} from '@/types/settings';
import { apiJson, useApiResource } from '@/composables/useApi';

function normalizeBusinessHours(raw: unknown): BusinessHoursForm {
    const defaults: BusinessHoursForm = { open: '09:00', close: '20:00' };

    if (!raw || typeof raw !== 'object') {
        return defaults;
    }

    const record = raw as Record<string, unknown>;

    if ('open' in record && 'close' in record) {
        return {
            open: String(record.open ?? defaults.open),
            close: String(record.close ?? defaults.close),
        };
    }

    const first = Object.values(record)[0];
    if (first && typeof first === 'object' && first !== null) {
        const slot = first as Record<string, unknown>;
        if ('open' in slot || 'from' in slot) {
            return {
                open: String(slot.open ?? slot.from ?? defaults.open),
                close: String(slot.close ?? slot.to ?? defaults.close),
            };
        }
        if (typeof first === 'string') {
            const [open, close] = first.split('-');
            return { open: open?.trim() || defaults.open, close: close?.trim() || defaults.close };
        }
    }

    return defaults;
}

function normalizeSocialNetworks(raw: unknown): SocialNetworksForm {
    const base: SocialNetworksForm = { instagram: '', facebook: '', tiktok: '' };
    if (!raw || typeof raw !== 'object') {
        return base;
    }
    const r = raw as Record<string, string | undefined>;
    return {
        instagram: r.instagram ?? '',
        facebook: r.facebook ?? '',
        tiktok: r.tiktok ?? '',
    };
}

function mapApiToForm(data: Record<string, unknown>): CompanySettingsForm {
    return {
        id: data.id as number | undefined,
        company_name: String(data.company_name ?? ''),
        yape_number: String(data.yape_number ?? ''),
        yape_name: String(data.yape_name ?? ''),
        business_hours: normalizeBusinessHours(data.business_hours),
        social_networks: normalizeSocialNetworks(data.social_networks),
        address: String(data.address ?? ''),
        sales_tone: String(data.sales_tone ?? 'cálido y cercano'),
        sales_closing_cta: String(data.sales_closing_cta ?? '¿Te lo separo ahora?'),
    };
}

export function useCompanySettings() {
    const { data: form, loading, saving, error, success, clearMessages } =
        useApiResource<CompanySettingsForm>();

    form.value = defaultCompanySettingsForm();

    const fetchSettings = async () => {
        loading.value = true;
        clearMessages();
        try {
            const data = await apiJson<Record<string, unknown> | null>('/api/company-settings');
            if (data) {
                form.value = mapApiToForm(data);
            }
        } catch {
            error.value = 'No se pudo cargar la configuración de empresa.';
        } finally {
            loading.value = false;
        }
    };

    const saveSettings = async () => {
        if (!form.value) {
            return;
        }

        saving.value = true;
        clearMessages();
        try {
            const payload = {
                ...form.value,
                business_hours: {
                    open: form.value.business_hours.open,
                    close: form.value.business_hours.close,
                },
            };
            const data = await apiJson<Record<string, unknown>>('/api/company-settings', {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
            form.value = mapApiToForm(data);
            success.value = 'Configuración guardada. El bot usará el nuevo tono y CTA en el próximo mensaje.';
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
        form,
        loading,
        saving,
        error,
        success,
        fetchSettings,
        saveSettings,
    };
}
