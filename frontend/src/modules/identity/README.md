# Identity (frontend)

Authentication, MFA, and user session management. Backend counterpart: [app/Modules/Identity](../../../../app/Modules/Identity).

## Pages

- **LoginPage** — email + password form, Zod-validated. Mutation calls `/sanctum/csrf-cookie` → `POST /auth/login` → `GET /auth/me` in sequence to fully hydrate the user including roles, permissions, and MFA state.

## API ([api/auth.ts](api/auth.ts))

- `useLogin()` — full login flow + user rehydration.
- `useLogout()` — best-effort `/auth/logout` + unconditional client-side state clear.
- `useBootstrapAuth()` — called once at app mount. If a token sits in localStorage, re-validates it via `/auth/me`; clears auth on 401.

## Auth state

Persisted via Zustand in `@/shared/lib/auth-store` under the `pha-auth` localStorage key. Carries `user` + `token` + `isAuthenticated`. `hasPermission(user, key)` is the single helper the rest of the app uses for permission checks.

## Permission keys

Mirror `IdentityRolesAndPermissionsSeeder` on the backend. Examples:
- `accounting.journals.view`, `accounting.journals.create`, `accounting.journals.post`
- `sales.invoices.view`, `sales.invoices.create`, `sales.invoices.void`, `sales.or.issue`, `sales.pos.transact`
- `tax.forms.generate`, `tax.osd_election.supersede`, `tax.form_2307_received.write`

Update both ends together — the frontend's RouteGuard mirrors the backend's `FormRequest::authorize()`.
