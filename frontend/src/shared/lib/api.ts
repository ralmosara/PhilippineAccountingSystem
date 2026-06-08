import axios, { type AxiosError, type AxiosInstance } from 'axios';

import { useAuthStore } from './auth-store';

/**
 * Single shared axios instance for all module API hooks.
 * - Reads bearer token from auth-store
 * - Sends Sanctum CSRF cookie on stateful requests
 * - Surfaces 401 by clearing the auth store (UI redirects to /login)
 */
export const api: AxiosInstance = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.request.use((config) => {
    const token = useAuthStore.getState().token;
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (res) => res,
    (error: AxiosError<{ message?: string }>) => {
        if (error.response?.status === 401) {
            useAuthStore.getState().logout();
        }
        return Promise.reject(error);
    },
);

export type ApiError = AxiosError<{ message?: string; errors?: Record<string, string[]> }>;
