<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Payroll\Application\Contracts\StatutoryRemittanceAggregatorContract;
use App\Modules\Payroll\Domain\Services\StatutoryRemittanceFormatter;
use App\Modules\Payroll\Domain\ValueObjects\StatutoryAgency;
use DateTimeImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionInterface;
use Ramsey\Uuid\Uuid;

/**
 * Generates a statutory remittance file (SSS R-3 / PhilHealth RF-1 / Pag-IBIG
 * MCRF) for the given period. Persists the file to object storage, writes
 * the audit event, and returns the file metadata + content so the controller
 * can stream it back to the operator.
 *
 *   1. Aggregate the period's approved payroll runs by employee.
 *   2. Resolve the employer profile from identity.companies.
 *   3. Format the agency-specific file body.
 *   4. Store under bir/{company}/{year}/statutory/{agency}/{period}.csv
 *   5. Audit-write 'statutoryremittance.generated' with totals.
 *   6. Return the storage path + bytes + summary.
 */
final readonly class GenerateStatutoryRemittance
{
    public function __construct(
        private StatutoryRemittanceAggregatorContract $aggregator,
        private StatutoryRemittanceFormatter $formatter,
        private FilesystemFactory $storage,
        private ConnectionInterface $db,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     agency: string,
     *     form_code: string,
     *     period_from: string,
     *     period_to: string,
     *     line_count: int,
     *     total_remittance: string,
     *     storage_path: string,
     *     file_body: string,
     * }
     */
    public function execute(
        string $companyId,
        StatutoryAgency $agency,
        DateTimeImmutable $periodFrom,
        DateTimeImmutable $periodTo,
        string $actorId,
    ): array {
        // 1. Aggregate per-employee contributions for the period
        $lines = $this->aggregator->aggregateForPeriod($companyId, $periodFrom, $periodTo);

        // 2. Employer header — read directly; this is a cross-schema read
        //    that we keep narrow rather than open a contract for a one-call use.
        $employer = $this->db->selectOne(<<<'SQL'
            SELECT id, tin, registered_name, address,
                   COALESCE(cas_ptu_number, '') AS branch_code
              FROM identity.companies
             WHERE id = ?::uuid
        SQL, [$companyId]);

        if (! $employer) {
            throw new \RuntimeException("Company {$companyId} not found.");
        }

        $employerHeader = [
            'er_id'         => $this->resolveEmployerId($agency, $employer),
            'er_name'       => (string) $employer->registered_name,
            'er_address'    => (string) $employer->address,
            'er_tin'        => (string) $employer->tin,
            'er_branch_code'=> (string) $employer->branch_code,
        ];

        // 3. Format file body
        $body = $this->formatter->format(
            agency:         $agency,
            employerHeader: $employerHeader,
            lines:          $lines,
            periodFrom:     $periodFrom,
            periodTo:       $periodTo,
        );

        // 4. Persist
        $remittanceId = Uuid::uuid4()->toString();
        $path = sprintf(
            'statutory/%s/%d/%s/%s_%s.%s',
            $companyId,
            (int) $periodFrom->format('Y'),
            $agency->value,
            $agency->formCode(),
            $periodFrom->format('Y-m'),
            $agency->fileExtension(),
        );
        $this->storage->disk('minio')->put($path, $body);

        // Totals for audit + UI display
        $totalRemittance = '0.00';
        foreach ($lines as $l) {
            $totalRemittance = bcadd($totalRemittance, $l->totalRemittance(), 2);
        }

        // 5. Audit
        $this->audit->writeEvent(
            actorId:     $actorId,
            companyId:   $companyId,
            eventType:   'statutoryremittance.generated',
            aggregate:   'StatutoryRemittance',
            aggregateId: $remittanceId,
            payload: [
                'agency'           => $agency->value,
                'form_code'        => $agency->formCode(),
                'period_from'      => $periodFrom->format('Y-m-d'),
                'period_to'        => $periodTo->format('Y-m-d'),
                'line_count'       => count($lines),
                'total_remittance' => $totalRemittance,
                'storage_path'     => $path,
            ],
        );

        return [
            'id'               => $remittanceId,
            'agency'           => $agency->value,
            'form_code'        => $agency->formCode(),
            'period_from'      => $periodFrom->format('Y-m-d'),
            'period_to'        => $periodTo->format('Y-m-d'),
            'line_count'       => count($lines),
            'total_remittance' => $totalRemittance,
            'storage_path'     => $path,
            'file_body'        => $body,
        ];
    }

    /**
     * Each agency identifies the employer by a different field:
     *   - SSS:        Employer SS Number (from identity.companies — TBD column)
     *   - PhilHealth: Employer PhilHealth Employer Number
     *   - Pag-IBIG:   Employer Pag-IBIG Number
     *
     * For Phase 1 we fall back to the company's TIN; once identity.companies
     * carries the per-agency employer IDs this resolver becomes a simple
     * column lookup.
     */
    private function resolveEmployerId(StatutoryAgency $agency, object $employer): string
    {
        // TODO(Phase 2): add sss_er_no, phic_er_no, hdmf_er_no columns to
        // identity.companies and switch on $agency to pick the right one.
        return (string) $employer->tin;
    }
}
