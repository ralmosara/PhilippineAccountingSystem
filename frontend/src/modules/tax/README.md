# Tax (frontend)

BIR forms, OSD election workflow, received 2307 management. Backend: [app/Modules/Tax](../../../../app/Modules/Tax).

## Pages

| Page | Purpose |
|---|---|
| **ItrWizardPage** | 5-step wizard for 1701Q / 1702Q / 1701 / 1702-RT generation. Period → Regime → Credits → Review → Result. Mutual exclusion between OSD and 8% flat is enforced client-side AND server-side. |
| **OsdElectionsPage** | The year's locked deduction regime per (company, fiscal_year, taxpayer_type). Active election highlighted; superseded ones shown when "Active only" is unchecked. |
| **Form2307ReceivedPage** | List + summary strip showing claimable / claimed totals (these become a tax credit on the next annual ITR). |

## Components

- **SupersedeOsdElectionDialog** — BIR-amendment workflow. Requires the new regime to differ from current AND a ≥20-char justification reason (citing the BIR approval letter / RMC). MFA-gated server-side.
- **Form2307BulkImportDialog** — paste-or-upload CSV with template header shown, per-row error report with row numbers + error-type tags.

## API hooks

- [api/bir-forms.ts](api/bir-forms.ts) — `useGenerate1701Q`, `useGenerate1702Q`, `useGenerate1701`, `useGenerate1702RT`.
- [api/osd-elections.ts](api/osd-elections.ts) — `useOsdElections`, `useSupersedeOsdElection`.
- [api/form-2307-received.ts](api/form-2307-received.ts) — `useForm2307Received`, `useBulkImport2307` (accepts both 201 and 422 as report-shaped responses), `useReject2307Received`.

## Regulatory invariants surfaced in the UI

- **OSD election is irrevocable for the year.** Once the first Q-return is generated, the regime is locked. The ITR wizard's regime step won't change the active election; supersede via the dedicated page if BIR approves an amendment.
- **`flat_8pct` is individual-only.** The Supersede dialog hides this option for corporate taxpayers.
- **Same-regime supersede is rejected.** The dialog blocks Submit if `new_regime === current_regime`.
