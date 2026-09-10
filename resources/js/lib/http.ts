import axios, { AxiosError } from 'axios';

/**
 * Cliente HTTP da aplicação.
 *
 * A autenticação é por cookie de sessão (mesma origem), então enviamos
 * credenciais e o token CSRF em toda requisição de escrita.
 */
export const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

if (csrf) {
    http.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}

export type ApiErrors = Record<string, string[]>;

export interface ApiFailure {
    message: string;
    errors: ApiErrors;
    errorCode: string | null;
    status: number;
}

/** Normaliza qualquer falha de rede/validação em uma forma única para a UI. */
export function toFailure(error: unknown): ApiFailure {
    if (error instanceof AxiosError && error.response) {
        const data = error.response.data ?? {};

        return {
            message: data.message ?? 'Não foi possível completar a ação.',
            errors: data.errors ?? {},
            errorCode: data.error_code ?? null,
            status: error.response.status,
        };
    }

    return {
        message: 'Falha de conexão. Verifique sua internet e tente novamente.',
        errors: {},
        errorCode: null,
        status: 0,
    };
}

/** Primeira mensagem de erro de um campo, se houver. */
export function fieldError(errors: ApiErrors, field: string): string | undefined {
    return errors[field]?.[0];
}
