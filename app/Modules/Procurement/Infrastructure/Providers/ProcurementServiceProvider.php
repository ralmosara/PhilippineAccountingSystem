<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Providers;

use App\Modules\Procurement\Application\Contracts\AtcCodeProviderContract;
use App\Modules\Procurement\Application\Contracts\VendorBillRepositoryContract;
use App\Modules\Procurement\Application\Contracts\VendorRepositoryContract;
use App\Modules\Procurement\Domain\Events\VendorBillPosted;
use App\Modules\Procurement\Infrastructure\Listeners\AutoIssueForm2307;
use App\Modules\Procurement\Infrastructure\Persistence\EloquentVendorBillRepository;
use App\Modules\Procurement\Infrastructure\Persistence\EloquentVendorRepository;
use App\Modules\Tax\Infrastructure\Persistence\EloquentAtcCodeProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ProcurementServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        VendorRepositoryContract::class     => EloquentVendorRepository::class,
        VendorBillRepositoryContract::class => EloquentVendorBillRepository::class,
        AtcCodeProviderContract::class      => EloquentAtcCodeProvider::class,   // Tax's impl
    ];

    public function register(): void
    {
        // bindings auto-resolved
    }

    public function boot(): void
    {
        Event::listen(VendorBillPosted::class, AutoIssueForm2307::class);
    }
}
