<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUser
{
    public function __construct(private AuditWriterContract $audit)
    {
    }

    /** @param array<string, mixed> $data */
    public function execute(string $id, array $data, UserModel $actor): UserModel
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $user = UserModel::findOrFail($id);
            $before = $user->only(['email', 'full_name', 'is_active']);

            $user->fill(array_intersect_key($data, array_flip(['email', 'full_name', 'is_active'])))
                 ->save();

            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            $this->audit->writeEvent(
                actorId:     $actor->id,
                companyId:   $actor->company_id,
                eventType:   'user.updated',
                aggregate:   'User',
                aggregateId: $user->id,
                payload:     ['before' => $before, 'after' => $user->only(['email', 'full_name', 'is_active'])],
            );

            return $user;
        });
    }
}
