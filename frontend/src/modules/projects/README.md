# Projects Module

Manages project tracking, timesheet logging, WIP (Work-In-Progress) recognition, and project lifecycle for the Philippine Accounting System.

## Routes

| Hash Route | Component | Notes |
|---|---|---|
| `#/projects` | `ProjectsPage` | List all projects for the company |
| `#/projects/new` | `ProjectEditorPage` | Create a new project |
| `#/projects/:id` | `ProjectDetailPage` | Project detail, timesheets, WIP |
| `#/projects/:id/edit` | `ProjectEditorPage` | Edit an existing project |

## API Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/projects` | List projects (paginated) |
| POST | `/api/v1/projects` | Create project |
| GET | `/api/v1/projects/:id` | Show project |
| PATCH | `/api/v1/projects/:id` | Update project |
| DELETE | `/api/v1/projects/:id` | Cancel project (soft) |
| GET | `/api/v1/projects/:id/timesheets` | List timesheet entries |
| POST | `/api/v1/projects/:id/timesheets` | Log timesheet entry |
| POST | `/api/v1/projects/:id/recognize-wip` | Recognize WIP (MFA) |
| POST | `/api/v1/projects/:id/close` | Close/complete project (MFA) |

## WIP Recognition Flow

1. Employees log timesheet entries against a project with an hourly billable rate.
2. At period end, the accountant triggers **Recognize WIP** for a date range.
3. The `RecognizeWip` action:
   - Aggregates all unbilled timesheet entries in the period.
   - Inserts a journal entry: **DR WIP Asset / CR Revenue**.
   - Marks those timesheet entries as `is_billed = true`.
   - Creates a `WipEntry` record in `posted` status.
4. `WipRecognized` event is dispatched for downstream subscribers.
5. Project can then be closed via `CloseProject` (MFA-gated), which dispatches `ProjectCompleted`.

## Billing Types

- **Fixed Price** — single contract value, WIP tracks progress toward completion.
- **Time & Materials** — every logged hour is billable at the employee's rate.
- **Retainer** — recurring monthly recognition, typically at the contract rate.

## Database Schema: `projects`

- `projects.projects` — project master record.
- `projects.timesheet_entries` — daily time logs per employee per project.
- `projects.wip_entries` — WIP recognition snapshots per period.

DELETE is revoked on `projects.projects` and `projects.wip_entries` at the role level (see migration 000072).
