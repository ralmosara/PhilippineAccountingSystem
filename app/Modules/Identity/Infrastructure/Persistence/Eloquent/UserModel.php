<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Database\Factories\Identity\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Eloquent model — Infrastructure concern, not Domain.
 * Domain code uses App\Modules\Identity\Domain\Entities\User.
 */
final class UserModel extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'identity.users';

    protected $guard_name = 'sanctum';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id',
        'email',
        'password_hash',
        'full_name',
        'employee_no',
        'mfa_enabled',
        'is_active',
    ];

    /** @var array<int, string> */
    protected $hidden = [
        'password_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mfa_enabled'        => 'boolean',
            'is_active'          => 'boolean',
            'email_verified_at'  => 'datetime',
            'last_login_at'      => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** Branches this user is scoped to (row-level access). */
    public function branchScopes(): BelongsToMany
    {
        return $this->belongsToMany(
            related: BranchModel::class,
            table: 'identity.user_branch_scopes',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'branch_id',
        )->withPivot('can_post', 'granted_at');
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
