import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';
import { useAuthStore, type AuthUser } from '@/shared/lib/auth-store';

interface ApiUser {
    id: string;
    email: string;
    full_name: string;
    company_id: string;
    roles: string[];
    permissions: string[];
    mfa_enabled: boolean;
}

function toAuthUser(u: ApiUser): AuthUser {
    return {
        id: u.id,
        email: u.email,
        fullName: u.full_name,
        companyId: u.company_id,
        roles: u.roles,
        permissions: u.permissions,
        mfaEnabled: u.mfa_enabled,
    };
}

/**
 * Fetch the CSRF cookie before any stateful Sanctum request.
 * Required for the SPA cookie-auth path; safe to call multiple times.
 */
async function ensureCsrfCookie(): Promise<void> {
    await api.get('/sanctum/csrf-cookie', { baseURL: '/' });
}

interface LoginResponse {
    token: string;
    expires_at: string;
    requires_mfa: boolean;
    user: { id: string; email: string; full_name: string };
}

/**
 * Login mutation — POST /auth/login then immediately fetch /auth/me to
 * populate the full user payload (roles, permissions, mfa state).
 */
export function useLogin() {
    const setAuth = useAuthStore((s) => s.setAuth);
    const qc = useQueryClient();

    return useMutation({
        mutationFn: async (creds: { email: string; password: string }) => {
            await ensureCsrfCookie();
            const res = await api.post<LoginResponse>('/auth/login', creds);
            return res.data;
        },
        onSuccess: async (data) => {
            // Stash the token first so the /auth/me request carries it.
            useAuthStore.setState({ token: data.token, isAuthenticated: true });
            const me = await api.get<{ user: ApiUser }>('/auth/me');
            setAuth(toAuthUser(me.data.user), data.token);
            qc.invalidateQueries();
        },
    });
}

export function useLogout() {
    const logout = useAuthStore((s) => s.logout);
    const qc = useQueryClient();

    return useMutation({
        mutationFn: async () => {
            await api.post('/auth/logout');
        },
        // Always log out client-side, even if the server call failed (token
        // may already be invalid).
        onSettled: () => {
            logout();
            qc.clear();
        },
    });
}

/**
 * Bootstraps the auth state on app mount. If a persisted token exists, hit
 * /auth/me to confirm it's still valid and refresh the user payload.
 * Returns the loading flag so the app can show a splash screen during the
 * round-trip.
 */
export function useBootstrapAuth(): { isBootstrapping: boolean } {
    const token = useAuthStore((s) => s.token);
    const setUser = useAuthStore((s) => s.setUser);
    const logout = useAuthStore((s) => s.logout);

    const query = useQuery({
        queryKey: ['auth', 'me'],
        enabled: !!token,
        queryFn: async () => {
            try {
                const res = await api.get<{ user: ApiUser }>('/auth/me');
                setUser(toAuthUser(res.data.user));
                return res.data.user;
            } catch (e) {
                // /auth/me returned 401 — token is stale, sign out cleanly
                logout();
                throw e;
            }
        },
        retry: false,
        staleTime: 60_000,
    });

    return { isBootstrapping: !!token && query.isLoading };
}
