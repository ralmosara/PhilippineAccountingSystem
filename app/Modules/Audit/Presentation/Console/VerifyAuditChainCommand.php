<?php

declare(strict_types=1);

namespace App\Modules\Audit\Presentation\Console;

use App\Modules\Audit\Application\Actions\VerifyAuditChain;
use Illuminate\Console\Command;

final class VerifyAuditChainCommand extends Command
{
    protected $signature = 'audit:verify-chain
                            {--company= : Limit verification to a single company UUID}';

    protected $description = 'Walk the audit.events hash chain and report any tampering.';

    public function handle(VerifyAuditChain $action): int
    {
        $result = $action->execute($this->option('company'));

        $this->components->info(sprintf(
            'Verified %d events; %d tampered.',
            $result['verified'],
            count($result['tampered']),
        ));

        if ($result['tampered'] !== []) {
            $this->components->error('Audit chain TAMPERING detected:');
            $this->table(
                ['Event ID', 'Reason'],
                array_map(fn ($r) => [$r['id'], $r['reason']], $result['tampered']),
            );
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
