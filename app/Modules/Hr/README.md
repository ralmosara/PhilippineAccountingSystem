# HR Module

Bounded context: **employees, departments, positions.**

Phase 1 ships the employee master file (with encrypted PII for TIN/SSS/PhilHealth/Pag-IBIG) and the department + position hierarchy. Attendance, leave management, and employment history come in a later batch.

## Layout

```
app/Modules/Hr/
├── routes.php
├── Domain/
│   ├── ValueObjects/EmployeeId.php
│   └── Entities/Employee.php
├── Application/
│   ├── Contracts/EmployeeRepositoryContract.php
│   └── Actions/CreateEmployee.php
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/{Employee, Department, Position}Model.php
│   │   └── EloquentEmployeeRepository.php
│   └── Providers/HrServiceProvider.php
└── Presentation/Http/
    ├── Controllers/EmployeeController.php          ← 7 RESTful methods
    ├── Requests/StoreEmployeeRequest.php
    └── Resources/EmployeeResource.php              ← PII masked for read-only roles
```

## PII handling

`hr.employees` stores TIN, SSS, PhilHealth, Pag-IBIG numbers **encrypted** via `Crypt::encryptString` (Laravel's AES-256-CBC with `APP_KEY`). The Eloquent model has accessor methods (`tin()`, `sssNo()`, etc.) that decrypt on read. The `EmployeeResource` masks PII (`***1234`) for users without the `hr.employees.manage` permission — auditors and viewers can confirm an employee exists without exposing the full ID.

## Cross-module surface

`EmployeeRepositoryContract::listActiveForCompany()` is consumed by Payroll's `ComputePayrollRun` action to enumerate who to pay. That's the only outbound contract HR exposes for now.

## Routes

```
GET    /api/v1/employees                  index
POST   /api/v1/employees                  store
GET    /api/v1/employees/{employee}       show
PATCH  /api/v1/employees/{employee}       update
DELETE /api/v1/employees/{employee}       destroy   (soft separation, never hard-delete)
```
