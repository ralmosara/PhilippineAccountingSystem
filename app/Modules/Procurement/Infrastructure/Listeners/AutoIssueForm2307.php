<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Listeners;

use App\Modules\Procurement\Application\Actions\IssueForm2307;
use App\Modules\Procurement\Domain\Events\VendorBillPosted;

/**
 * On VendorBillPosted, automatically issue the corresponding Form 2307
 * if the bill carries a withholding ATC code.
 *
 * Synchronous (not queued) — the 2307 row creation is cheap and we want
 * it inside the same transaction that posted the bill, so a failure to
 * create the cert rolls back the bill posting too.
 */
final readonly class AutoIssueForm2307
{
    public function __construct(private IssueForm2307 $action)
    {
    }

    public function handle(VendorBillPosted $event): void
    {
        if (! $event->hasWithholding()) {
            return;
        }

        $this->action->execute(
            vendorBillId: $event->vendorBillId,
            actorId:      $event->postedBy,
        );
    }
}
