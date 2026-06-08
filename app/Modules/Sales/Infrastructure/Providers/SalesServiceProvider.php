<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Providers;

use App\Modules\Sales\Application\Contracts\CustomerRepositoryContract;
use App\Modules\Sales\Application\Contracts\OfficialReceiptRepositoryContract;
use App\Modules\Sales\Application\Contracts\SalesInvoiceRepositoryContract;
use App\Modules\Sales\Domain\Events\InvoiceIssued;
use App\Modules\Sales\Infrastructure\Listeners\EnqueueEisSubmission;
use App\Modules\Sales\Infrastructure\Persistence\EloquentCustomerRepository;
use App\Modules\Sales\Infrastructure\Persistence\EloquentOfficialReceiptRepository;
use App\Modules\Sales\Infrastructure\Persistence\EloquentSalesInvoiceRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class SalesServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CustomerRepositoryContract::class        => EloquentCustomerRepository::class,
        SalesInvoiceRepositoryContract::class    => EloquentSalesInvoiceRepository::class,
        OfficialReceiptRepositoryContract::class => EloquentOfficialReceiptRepository::class,
    ];

    public function register(): void
    {
        // bindings auto-resolved
    }

    public function boot(): void
    {
        Event::listen(InvoiceIssued::class, EnqueueEisSubmission::class);
    }
}
