# PHA Frontend

React 18 + Vite + TypeScript SPA for the Philippine Accounting System.

## Stack

- **Vite + React 18 + TypeScript** (strict)
- **TanStack Query** for server state
- **TanStack Router** for routing (planned, currently a minimal switch)
- **TanStack Table** for ledgers/journals
- **Zustand** for UI state (auth, preferences)
- **React Hook Form + Zod** for forms
- **shadcn/ui + Radix + Tailwind** for UI primitives
- **Recharts** for dashboards
- **Vitest + Testing Library + Playwright** for tests

## Layout

```
src/
├── modules/
│   ├── identity/        # auth, MFA, users
│   ├── dashboard/       # main shell view
│   ├── accounting/      # journals, ledgers, trial balance
│   ├── tax/             # BIR forms wizards
│   ├── sales/           # POS, OR/SI
│   ├── ...              # mirrors backend modules
├── shared/
│   ├── components/      # DataTable, Money, DatePicker
│   ├── lib/             # api client, auth store, money formatter
│   └── i18n/            # en, fil
├── styles/
│   └── globals.css
├── app/
│   ├── App.tsx
│   └── router.tsx
└── main.tsx
```

## Dev

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
# http://localhost:5173 (Vite proxies /api → http://localhost:8000)
```

## Build / Test / Lint

```bash
npm run build         # production build to dist/
npm run typecheck     # tsc --noEmit
npm run test          # vitest
npm run test:e2e      # playwright
npm run lint          # eslint
npm run format        # prettier
```
