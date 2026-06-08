<?php

declare(strict_types=1);

namespace App\Modules\Hr\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

final class EmployeeModel extends Model
{
    use HasUuids;

    protected $table = 'hr.employees';

    /** @var array<int, string> */
    protected $fillable = [
        'company_id', 'branch_id', 'user_id', 'employee_no',
        'tin_encrypted', 'sss_no_encrypted', 'philhealth_no_encrypted', 'pagibig_no_encrypted',
        'first_name', 'middle_name', 'last_name', 'suffix',
        'birth_date', 'gender', 'civil_status', 'nationality',
        'address_line', 'city', 'province', 'postal_code',
        'mobile', 'email', 'emergency_contact_name', 'emergency_contact_phone',
        'hired_on', 'regularized_on', 'separated_on', 'separation_reason',
        'department_id', 'position_id', 'immediate_supervisor_id',
        'employment_status', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'birth_date'      => 'date',
            'hired_on'        => 'date',
            'regularized_on'  => 'date',
            'separated_on'    => 'date',
            'is_active'       => 'boolean',
        ];
    }

    public function tin(): ?string
    {
        return $this->tin_encrypted ? Crypt::decryptString($this->tin_encrypted) : null;
    }

    public function setTinAttribute(?string $plain): void
    {
        $this->attributes['tin_encrypted'] = $plain ? Crypt::encryptString($plain) : null;
    }

    public function sssNo(): ?string
    {
        return $this->sss_no_encrypted ? Crypt::decryptString($this->sss_no_encrypted) : null;
    }

    public function philhealthNo(): ?string
    {
        return $this->philhealth_no_encrypted ? Crypt::decryptString($this->philhealth_no_encrypted) : null;
    }

    public function pagibigNo(): ?string
    {
        return $this->pagibig_no_encrypted ? Crypt::decryptString($this->pagibig_no_encrypted) : null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionModel::class, 'position_id');
    }
}
