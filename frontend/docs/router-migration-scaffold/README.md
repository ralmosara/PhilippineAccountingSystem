# routes/ — TanStack Router scaffold (staged)

> **Status: scaffold only — not active.** The live router is still the hash-based one in [`src/app/router.tsx`](../app/router.tsx).

This folder is the staging area for the migration described in [`frontend/docs/router-migration.md`](../../docs/router-migration.md).

## What's here

- **`__root.tsx`** — destination root layout with `<Outlet />` + `TopNav` + auth bootstrap. Mirrors the existing `AppRouter` behaviour but uses TanStack `<Link>`s + `activeProps`.
- **`index.tsx`** — sample dashboard route, simplest case.
- **`tax.osd-elections.tsx`** — sample protected route, demonstrates the `beforeLoad` pattern that replaces the current `RouteGuard` switch.

## Status: scaffold complete — activation pending

All 21 page wrappers + the root layout are now scaffolded as `.example` files in this folder. Each is real code (imports the actual page component, sets up `beforeLoad` permission gates, handles typed params). On activation they move to `src/routes/{path}.tsx` matching their URL structure.

The activation is one PR: install the router-plugin, move the `.example` files to `src/routes/`, swap `<AppRouter />` for `<RouterProvider />` in `App.tsx`, replace `<a href="#/...">` → `<Link to="...">` (~30 occurrences), delete the hash router. Verified playbook below.

## When to flip the switch

The hash router is fit-for-purpose at 14 nav entries. Once any of these become true:

- Page count crosses ~20
- Deep-link bookmarks become a UX concern (real history API matters)
- Per-route loaders are wanted (kill the "data flashes empty on mount" pattern)
- Nested layouts (drawer + master/detail) need to compose without re-rendering the chrome

…schedule the migration. Per the migration plan, full execution is ~5-6 hours of mechanical work.

## Why these scaffold files exist now

So the migration can be done in **small reviewable chunks** instead of one mega-diff:

1. PR 1: Add `routes/__root.tsx`, `routes/index.tsx`, vite-plugin config. Old router still active. (Done in this batch.)
2. PR 2: Add the remaining 19 page wrappers. Old router still active.
3. PR 3: Flip `App.tsx` to render `<RouterProvider />`. Delete `app/router.tsx`. Mass-replace `<a href="#/...">` → `<Link to="...">`.
4. PR 4: Add a redirect from `#/`-hash URLs to real paths for old bookmarks.

Each PR is independently revertable. Today only PR 1 is staged; PRs 2-4 are tracked.
