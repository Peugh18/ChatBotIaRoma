import { ref, type ComputedRef } from 'vue';
import type { CustomerDetails } from '@/types/chat';
import { useCsrfToken } from '@/composables/useCsrfToken';

export function useChatCustomer(
    selectedCustomerId: ComputedRef<number | null>,
    onProfileSaved?: () => Promise<void>,
) {
    const customerDetails = ref<CustomerDetails | null>(null);
    const loadingCustomer = ref(false);
    const savingCustomer = ref(false);
    const profileMessage = ref<string | null>(null);
    const { refreshCsrfToken } = useCsrfToken();

    const fetchCustomerDetails = async (id: number) => {
        loadingCustomer.value = true;
        try {
            const response = await fetch(`/api/customers/${id}`, {
                headers: { Accept: 'application/json' },
            });
            customerDetails.value = response.ok ? await response.json() : null;
        } catch (error) {
            console.error('Error fetching customer details:', error);
            customerDetails.value = null;
        } finally {
            loadingCustomer.value = false;
        }
    };

    const saveCustomerProfile = async () => {
        if (!customerDetails.value || savingCustomer.value) {
            return;
        }

        savingCustomer.value = true;
        profileMessage.value = null;

        try {
            const token = await refreshCsrfToken();
            const response = await fetch(`/api/customers/${customerDetails.value.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    name: customerDetails.value.name,
                    email: customerDetails.value.email,
                    segment: customerDetails.value.segment,
                    notes: customerDetails.value.notes,
                    tags: customerDetails.value.tags,
                }),
            });

            if (response.ok) {
                profileMessage.value = 'Perfil guardado correctamente.';
                await onProfileSaved?.();
            } else {
                profileMessage.value = 'Error al guardar el perfil.';
            }
        } catch (error) {
            console.error('Error saving customer profile:', error);
            profileMessage.value = 'Error al guardar el perfil.';
        } finally {
            savingCustomer.value = false;
        }
    };

    const addTag = (event: Event) => {
        const input = event.target as HTMLInputElement;
        const value = input.value.trim();
        if (value && customerDetails.value) {
            if (!customerDetails.value.tags) {
                customerDetails.value.tags = [];
            }
            if (!customerDetails.value.tags.includes(value)) {
                customerDetails.value.tags.push(value);
            }
            input.value = '';
        }
    };

    const removeTag = (index: number) => {
        customerDetails.value?.tags?.splice(index, 1);
    };

    const loadCustomerIfNeeded = async () => {
        const id = selectedCustomerId.value;
        if (id) {
            await fetchCustomerDetails(id);
        } else {
            customerDetails.value = null;
        }
    };

    return {
        customerDetails,
        loadingCustomer,
        savingCustomer,
        profileMessage,
        fetchCustomerDetails,
        saveCustomerProfile,
        addTag,
        removeTag,
        loadCustomerIfNeeded,
    };
}
