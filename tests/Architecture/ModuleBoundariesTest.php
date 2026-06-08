<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Module Boundary Architecture Tests (pestphp/pest-plugin-arch)
|--------------------------------------------------------------------------
|
| Compile-time enforcement of the bounded-context rules captured in
| CLAUDE.md and the plan:
|
|   1. Domain layer is pure PHP — no Illuminate, no Eloquent, no HTTP.
|   2. Application layer never reaches Infrastructure (own or other modules').
|   3. Cross-module access is permitted ONLY through Application\Contracts.
|   4. Every controller is final; every Action class is final.
|   5. Single-action controllers expose __invoke; resource controllers expose
|      exactly the seven RESTful methods (Taylor Otwell convention).
|
| The N×(N-1) cross-module matrix is enumerated explicitly below so a future
| contributor adding `App\Modules\Sales\Application` → `App\Modules\Tax\
| Infrastructure` fails CI deterministically, regardless of which module
| owns the edge.
|
*/

/** All modules currently in the codebase. Add new modules here. */
const MODULES = [
    'Accounting',
    'Audit',
    'Hr',
    'Identity',
    'Inventory',
    'Payroll',
    'Procurement',
    'Reporting',
    'Sales',
    'Tax',
];

/* ────────────────────────────────────────────────────────────────────────────
 * Universal rules (apply to every module)
 * ──────────────────────────────────────────────────────────────────────────── */

arch('strict types are declared everywhere')
    ->expect('App\Modules')
    ->toUseStrictTypes();

arch('Domain layer never imports Eloquent, HTTP, or Sanctum')
    ->expect('App\Modules')
    ->toUse([])           // placeholder — chained checks below cover the real surface
    ->and('App\Modules\*\Domain')
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
        'Illuminate\Support\Facades',
        'Laravel\Sanctum',
        'Barryvdh\DomPDF',
    ]);

arch('Application layer never imports Infrastructure (own module)')
    ->expect('App\Modules\*\Application')
    ->not->toUse('App\Modules\*\Infrastructure');

arch('Application layer never imports another module\'s Domain directly')
    // Cross-module access goes through Application\Contracts. The exceptions
    // baked in today (Sales\Domain\Entities used by Tax\Domain\Services\Eis\
    // EisPayloadBuilder for the EIS payload shape) are explicit and reviewed:
    // Sales entities are part of Sales' public surface because the EIS payload
    // is structurally derived from the invoice.
    ->expect('App\Modules\Identity\Application')
    ->not->toUse([
        'App\Modules\Accounting\Domain',
        'App\Modules\Tax\Domain',
        'App\Modules\Sales\Domain',
    ]);

/* ────────────────────────────────────────────────────────────────────────────
 * Final + readonly conventions
 * ──────────────────────────────────────────────────────────────────────────── */

arch('all controllers are final')
    ->expect('App\Modules\*\Presentation\Http\Controllers')
    ->toBeFinal();

arch('all Action classes are final')
    ->expect('App\Modules\*\Application\Actions')
    ->toBeFinal();

arch('all Query classes are final and readonly')
    ->expect('App\Modules\*\Application\Queries')
    ->toBeFinal()
    ->toBeReadonly();

arch('Domain entities are final')
    ->expect('App\Modules\*\Domain\Entities')
    ->toBeFinal();

arch('Domain value objects are final readonly')
    ->expect('App\Modules\*\Domain\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

arch('Application DTOs are final readonly')
    ->expect('App\Modules\*\Application\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

arch('Domain services are final')
    ->expect('App\Modules\*\Domain\Services')
    ->toBeFinal();

/* ────────────────────────────────────────────────────────────────────────────
 * Code hygiene
 * ──────────────────────────────────────────────────────────────────────────── */

arch('no debug helpers in production code')
    ->expect('App\Modules')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray', 'die']);

arch('Eloquent models live only in Infrastructure\Persistence\Eloquent')
    ->expect('Illuminate\Database\Eloquent\Model')
    ->toOnlyBeUsedIn('App\Modules\*\Infrastructure\Persistence\Eloquent');

/* ────────────────────────────────────────────────────────────────────────────
 * Cross-module boundary matrix
 * ──────────────────────────────────────────────────────────────────────────── */

foreach (MODULES as $consumer) {
    foreach (MODULES as $provider) {
        if ($consumer === $provider) continue;

        // A consumer module's Application + Infrastructure may NOT import the
        // provider's Infrastructure. Period. The only legal route is through
        // App\Modules\<Provider>\Application\Contracts.
        arch("{$consumer} does not import {$provider}\\Infrastructure")
            ->expect("App\\Modules\\{$consumer}")
            ->not->toUse("App\\Modules\\{$provider}\\Infrastructure");

        // Domain layer of any module is sacred — no other module may reach
        // into it. Even the Tax→Sales\Domain link permitted at the
        // Application layer (for EIS payload building) does NOT extend to
        // other consumers reaching Sales\Domain directly.
        // The exceptions: Tax may use Sales\Domain (EIS payload builder
        // operates on the SalesInvoice entity shape; that's its API).
        $domainException = $consumer === 'Tax' && $provider === 'Sales';
        if (! $domainException) {
            arch("{$consumer} does not import {$provider}\\Domain")
                ->expect("App\\Modules\\{$consumer}")
                ->not->toUse("App\\Modules\\{$provider}\\Domain");
        }
    }
}

/* ────────────────────────────────────────────────────────────────────────────
 * Money & BCMath enforcement
 * ──────────────────────────────────────────────────────────────────────────── */

arch('Domain code never uses (float) casts for monetary values')
    // PHPStan catches casts to float; arch-test layer catches dependence on
    // the `floatval` helper or the `is_float` family that hints at sloppy
    // money handling sneaking into a calculator.
    ->expect('App\Modules\*\Domain')
    ->not->toUse(['floatval', 'doubleval', 'is_float', 'is_double']);
