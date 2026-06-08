<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Soft-deletes the user (BIR auditors must still see who once had access).
 * Hard-deletion is forbidden — only `is_active = false` + soft-delete row.
 */
final readonly class DeleteUser
{
    public function __construct(private AuditWriterContract $audit)
    {
    }

    public function execute(string $id, UserModel $actor): void
    {
        if ($actor->id === $id) {
            throw new RuntimeException('You cannot delete your own account.');
        }

        DB::transaction(function () use ($id, $actor) {
            $user = UserModel::findOrFail($id);

            $user->update(['is_active' => false]);
            $user->delete();      // soft-delete (deleted_at set)

            $this->audit->writeEvent(
                actorId:     $actor->id,
                companyId:   $actor->company_id,
                eventType:   'user.disabled',
                aggregate:   'User',
                aggregateId: $user->id,
                payload:     ['email' => $user->email],
            );
        });
    }
}
