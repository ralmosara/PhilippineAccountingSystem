<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

final readonly class CreateUser
{
    public function __construct(private AuditWriterContract $audit)
    {
    }

    /**
     * @param  array{email: string, full_name: string, password: string, roles: array<int, string>, branch_ids?: array<int, string>}  $data
     */
    public function execute(array $data, UserModel $actor): UserModel
    {
        return DB::transaction(function () use ($data, $actor) {
            $user = UserModel::create([
                'id'            => Uuid::uuid4()->toString(),
                'company_id'    => $actor->company_id,
                'email'         => $data['email'],
                'password_hash' => Hash::make($data['password']),
                'full_name'     => $data['full_name'],
                'is_active'     => true,
            ]);

            $user->syncRoles($data['roles']);

            if (! empty($data['branch_ids'])) {
                $user->branchScopes()->sync($data['branch_ids']);
            }

            $this->audit->writeEvent(
                actorId:     $actor->id,
                companyId:   $actor->company_id,
                eventType:   'user.created',
                aggregate:   'User',
                aggregateId: $user->id,
                payload:     [
                    'email'     => $user->email,
                    'full_name' => $user->full_name,
                    'roles'     => $data['roles'],
                ],
            );

            return $user;
        });
    }
}
