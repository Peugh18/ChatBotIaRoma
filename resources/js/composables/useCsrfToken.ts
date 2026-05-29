import { ref } from 'vue';

const csrfToken = ref('');

export function useCsrfToken() {
    const refreshCsrfToken = async (): Promise<string> => {
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (metaToken) {
            csrfToken.value = metaToken;
            return metaToken;
        }

        try {
            const response = await fetch('/api/csrf-token', { credentials: 'same-origin' });
            if (response.ok) {
                const data = (await response.json()) as { token: string };
                csrfToken.value = data.token;
                return data.token;
            }
        } catch (error) {
            console.error('Error refreshing CSRF token:', error);
        }

        return '';
    };

    return {
        csrfToken,
        refreshCsrfToken,
    };
}
