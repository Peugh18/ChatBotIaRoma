import { computed, ref, type Ref } from 'vue';
import type { ColorGalleryItem, SalesContext, SalesContextProduct } from '@/types/chat';
import { apiJson, ApiError } from '@/composables/useApi';
import { useCsrfToken } from '@/composables/useCsrfToken';

export function useSalesContext(
    selectedPhone: Ref<string | null>,
    currentConversationMode: Ref<'bot' | 'human'>,
    onPhotoSent?: () => Promise<void>,
    isAutoEscalated?: Ref<boolean>,
) {
    const salesContext = ref<SalesContext | null>(null);
    const loadingSalesContext = ref(false);
    const sendingPhotoColor = ref<string | null>(null);
    const galleryProductOverride = ref<SalesContextProduct | null>(null);
    const photoError = ref<string | null>(null);
    const validatingPayment = ref(false);
    const paymentValidationError = ref<string | null>(null);
    const { refreshCsrfToken } = useCsrfToken();

    const galleryColors = computed((): ColorGalleryItem[] => {
        if (galleryProductOverride.value?.colors?.length) {
            return galleryProductOverride.value.colors;
        }
        return salesContext.value?.colors ?? [];
    });

    const galleryProductName = computed(() => {
        if (galleryProductOverride.value?.name) {
            return galleryProductOverride.value.name;
        }
        return salesContext.value?.current_product?.name;
    });

    const fetchSalesContext = async (phone: string) => {
        loadingSalesContext.value = true;
        try {
            const response = await fetch(`/api/conversations/${encodeURIComponent(phone)}/sales-context`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            salesContext.value = response.ok ? await response.json() : null;
        } catch (error) {
            console.error('Error fetching sales context:', error);
            salesContext.value = null;
        } finally {
            loadingSalesContext.value = false;
        }
    };

    const resetGallery = () => {
        galleryProductOverride.value = null;
    };

    const clearSalesContext = () => {
        salesContext.value = null;
        resetGallery();
    };

    const validatePayment = async (): Promise<boolean> => {
        if (!selectedPhone.value) {
            return false;
        }

        validatingPayment.value = true;
        paymentValidationError.value = null;

        try {
            await apiJson(`/api/conversations/${encodeURIComponent(selectedPhone.value)}/validate-payment`, {
                method: 'POST',
            });
            currentConversationMode.value = 'bot';
            if (isAutoEscalated) {
                isAutoEscalated.value = false;
            }
            await onPhotoSent?.();
            await fetchSalesContext(selectedPhone.value);

            return true;
        } catch (error) {
            paymentValidationError.value =
                error instanceof ApiError ? error.message : 'No se pudo validar el pago';
            return false;
        } finally {
            validatingPayment.value = false;
        }
    };

    const sendColorPhotoToCustomer = async (item: ColorGalleryItem, productName?: string) => {
        if (!selectedPhone.value || !item.image_url) {
            return;
        }
        if (currentConversationMode.value === 'bot') {
            photoError.value = 'Cambia a modo Humano para enviar fotos al cliente.';
            return;
        }

        const caption = productName
            ? `${productName} en color ${item.color} ✨`
            : `Color ${item.color} ✨`;

        sendingPhotoColor.value = item.color;
        photoError.value = null;

        try {
            const token = await refreshCsrfToken();
            const response = await fetch('/api/send-message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    phone_number: selectedPhone.value,
                    content: caption,
                    image_url: item.image_url,
                }),
            });

            if (!response.ok) {
                const err = (await response.json().catch(() => ({}))) as { message?: string; hint?: string };
                photoError.value = err.message || 'No se pudo enviar la foto';
                if (err.hint) {
                    console.warn('[WhatsApp foto]', err.hint);
                }
                return;
            }

            await onPhotoSent?.();
        } catch (error) {
            console.error('Error sending color photo:', error);
            photoError.value = 'Error al enviar foto';
        } finally {
            sendingPhotoColor.value = null;
        }
    };

    return {
        salesContext,
        loadingSalesContext,
        sendingPhotoColor,
        galleryProductOverride,
        galleryColors,
        galleryProductName,
        photoError,
        validatingPayment,
        paymentValidationError,
        fetchSalesContext,
        resetGallery,
        clearSalesContext,
        validatePayment,
        sendColorPhotoToCustomer,
    };
}
