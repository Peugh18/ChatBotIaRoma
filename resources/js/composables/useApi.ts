import { ref, type Ref } from 'vue';

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

export function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function apiJson<T>(
    url: string,
    options: RequestInit & { method?: string } = {},
): Promise<T> {
    const method = options.method ?? 'GET';
    const headers: Record<string, string> = {
        Accept: 'application/json',
        ...(options.headers as Record<string, string> | undefined),
    };

    if (method !== 'GET' && method !== 'HEAD') {
        headers['Content-Type'] = headers['Content-Type'] ?? 'application/json';
        headers['X-CSRF-TOKEN'] = getCsrfToken();
    }

    const response = await fetch(url, {
        ...options,
        method,
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        if (response.status === 419) {
            throw new ApiError('Sesión expirada. Recarga la página.', 419);
        }
        throw new ApiError(`Error ${response.status}`, response.status);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

/**
 * Estado estándar para formularios que cargan/guardan vía API REST.
 */
export function useApiResource<T>() {
    const data = ref<T | null>(null) as Ref<T | null>;
    const loading = ref(false);
    const saving = ref(false);
    const error = ref<string | null>(null);
    const success = ref<string | null>(null);

    const clearMessages = () => {
        error.value = null;
        success.value = null;
    };

    return {
        data,
        loading,
        saving,
        error,
        success,
        clearMessages,
    };
}
