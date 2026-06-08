# Identity Module

Bounded context: **users, companies, branches, roles, permissions, MFA, sessions, DPA consent.**

This module is the canonical example of the project's DDD module layout. Every other module follows the same shape.

---

## Layout

```
app/Modules/Identity/
├── routes.php                                     # auto-loaded by ModuleServiceProvider
├── Domain/                                        # pure PHP, no framework
│   ├── Entities/
│   │   └── User.php
│   ├── ValueObjects/
│   │   ├── Email.php
│   │   └── UserId.php
│   ├── Events/                                    # domain events emitted on state change
│   └── Services/                                  # domain services (no I/O)
├── Application/                                   # use cases — controllers call into this
│   ├── Actions/
│   │   ├── AuthenticateUser.php                   # one verb = one Action class
│   │   ├── EnableMfa.php
│   │   ├── ConfirmMfa.php
│   │   ├── CreateUser.php
│   │   ├── UpdateUser.php
│   │   └── DeleteUser.php
│   ├── Contracts/                                 # PUBLIC SURFACE — other modules import these
│   │   ├── UserRepositoryContract.php
│   │   └── AuthenticatorContract.php
│   ├── Queries/                                   # CQRS read side
│   └── Exceptions/
│       └── InvalidCredentialsException.php
├── Infrastructure/                                # adapters — Eloquent, Sanctum, Redis
│   ├── Persistence/
│   │   ├── Eloquent/
│   │   │   └── UserModel.php
│   │   └── EloquentUserRepository.php             # implements UserRepositoryContract
│   ├── Authentication/
│   │   └── SanctumAuthenticator.php               # implements AuthenticatorContract
│   └── Providers/
│       └── IdentityServiceProvider.php            # binds Contracts → implementations
├── Presentation/                                  # HTTP adapters
│   └── Http/
│       ├── Controllers/                           # THIN — Taylor Otwell convention
│       │   ├── UserController.php                 # 7 RESTful methods only
│       │   ├── AuthenticateUserController.php     # __invoke (single-action)
│       │   ├── LogoutUserController.php           # __invoke
│       │   ├── EnableMfaController.php            # __invoke
│       │   └── ConfirmMfaController.php           # __invoke
│       ├── Requests/                              # FormRequest validation
│       ├── Resources/                             # API output shaping
│       └── Policies/                              # RBAC at action level
└── database/
    ├── migrations/                                # auto-loaded by ModuleServiceProvider
    └── seeders/
```

---

## Controller Convention (Taylor Otwell)

**Resource controllers** — `index`, `show`, `create`, `store`, `edit`, `update`, `destroy` — and that's it. See `UserController`.

**Single-action controllers** — every other verb gets its own class with one public method `__invoke`. See `AuthenticateUserController`, `EnableMfaController`, etc.

Controllers stay thin: validate → resolve Action → return Response. Logic lives in **Action classes** (`Application/Actions/`).

---

## Cross-Module Contracts

Other modules MAY use:

- `App\Modules\Identity\Application\Contracts\UserRepositoryContract`
- `App\Modules\Identity\Application\Contracts\AuthenticatorContract`

They MUST NOT directly import:

- Anything in `Infrastructure/`
- Anything in `Domain/Entities/` (use a Contract that returns a DTO/value object)
- The Eloquent `UserModel`

This boundary is enforced in CI via `pestphp/pest-plugin-arch`.

---

## Tables (managed by migrations under `database/migrations/`)

See [docs/database/erd-identity.md](../../../docs/database/erd-identity.md).

---

## Testing

- **Unit**: `tests/Unit/Modules/Identity/Application/Actions/*` — exercise Actions with mocked Contracts.
- **Feature**: `tests/Feature/Modules/Identity/Http/*` — full HTTP cycle, hits the DB.
- **Architecture**: `tests/Architecture/IdentityModuleTest.php` — assert no module imports forbidden internals.
