# Accounting (frontend)

UI for the general ledger and period-close workflow. Mirrors backend module [app/Modules/Accounting](../../../../app/Modules/Accounting).

## Pages

- **JournalEntriesPage** — list with status filter. Posted entries are immutable; reverse to undo.
- **JournalEntryEditor** — keyboard-driven JV editor.
  - Tab cycles cells · Enter on last cell adds a row · Ctrl+S saves draft · Alt+P posts (when balanced).
  - Live "debits = credits" banner uses BigInt-scaled arithmetic from `@/shared/lib/bcmath` — no Number drift at 200+ rows.
- **FiscalPeriodsPage** — monthly close. Lock action is MFA-gated and guarded by a "type LOCK" confirm dialog. Once locked, no JV may be posted or reversed within the period's date range (enforced by Postgres trigger).

## API hooks ([api/journals.ts](api/journals.ts))

`useAccounts`, `useJournalEntries`, `useJournalEntry`, `useSaveJournalEntry`, `usePostJournalEntry`, `useReverseJournalEntry`.

Account list is cached for 5 minutes — shared with the `AccountPicker` component so 30 pickers on one screen make one HTTP call.

## Domain rules surfaced in the UI

- **Posted entries can't be edited.** Editor mode-switches to read-only when `posted_at !== null`.
- **Reverse, don't edit.** The reversal action creates a new compensating JE; both stay in the audit trail.
- **Period lock is irreversible from the SPA.** Genuine unlock requires Approver role + audit event; no UI affordance.
- **Debit/credit mutual exclusion.** Typing into the debit cell auto-clears the credit cell on the same line (and vice-versa).
