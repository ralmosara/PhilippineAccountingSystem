<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Providers;

use App\Modules\Inventory\Application\Contracts\ItemRepositoryContract;
use App\Modules\Inventory\Application\Contracts\StockBalanceRepositoryContract;
use App\Modules\Inventory\Application\Contracts\StockMovementRepositoryContract;
use App\Modules\Inventory\Application\Contracts\WarehouseRepositoryContract;
use App\Modules\Inventory\Infrastructure\Listeners\DeductStockOnInvoiceIssued;
use App\Modules\Inventory\Infrastructure\Listeners\ReceiveStockOnVendorBillPosted;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentItemRepository;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentStockBalanceRepository;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentStockMovementRepository;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentWarehouseRepository;
use App\Modules\Procurement\Domain\Events\VendorBillPosted;
use App\Modules\Sales\Domain\Events\InvoiceIssued;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class InventoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ItemRepositoryContract::class       => EloquentItemRepository::class,
        WarehouseRepositoryContract::class  => EloquentWarehouseRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(
            StockBalanceRepositoryContract::class,
            fn ($app) => new EloquentStockBalanceRepository($app->make(ConnectionInterface::class))
        );

        $this->app->singleton(
            StockMovementRepositoryContract::class,
            fn ($app) => new EloquentStockMovementRepository($app->make(ConnectionInterface::class))
        );
    }

    public function boot(): void
    {
        // Cross-module wiring — auto-deduct/add stock on sales/procurement events
        Event::listen(InvoiceIssued::class,    DeductStockOnInvoiceIssued::class);
        Event::listen(VendorBillPosted::class, ReceiveStockOnVendorBillPosted::class);
    }
}
