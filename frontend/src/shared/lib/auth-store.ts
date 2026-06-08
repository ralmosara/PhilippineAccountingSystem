import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export interface AuthUser {
    id: string;
    email: string;
    fullName: string;
    companyId: string;
    roles: string[];
    permissions: string[];
    mfaEnabled: boolean;
}

interface AuthState {
    user: AuthUser | null;
    token: string | null;
    isAuthenticated: boolean;
    setAuth: (user: AuthUser, token: string) => void;
    setUser: (user: AuthUser) => void;
    logout: () => void;
}

/**
 * Bearer token + minimal user payload, persisted in localStorage so a page
 * refresh keeps the session. The user payload here is a *snapshot* — fresh
 * data comes from /auth/me on every app boot (see useBootstrapAuth).
 */
export const useAuthStore = create<AuthState>()(
    persist(
        (set) => ({
            user: null,
            token: null,
            isAuthenticated: false,
            setAuth: (user, token) => set({ user, token, isAuthenticated: true }),
            setUser: (user) => set({ user }),
            logout: () => set({ user: null, token: null, isAuthenticated: false }),
        }),
        {
            name: 'pha-auth',
            partialize: (s) => ({
                user: s.user,
                token: s.token,
                isAuthenticated: s.isAuthenticated,
            }),
        },
    ),
);

export function hasPermission(user: AuthUser | null, permission: string): boolean {
    return !!user?.permissions.includes(permission);
}
