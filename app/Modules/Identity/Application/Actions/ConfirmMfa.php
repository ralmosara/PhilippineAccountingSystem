<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Identity\Application\Exceptions\InvalidMfaOtpException;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

final readonly class ConfirmMfa
{
    public function __construct(
        private Google2FA $google2fa,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @throws InvalidMfaOtpException
     */
    public function execute(UserModel $user, string $otp): void
    {
        $row = DB::table('identity.mfa_secrets')
            ->where('user_id', $user->id)
            ->first();

        if (! $row) {
            throw new InvalidMfaOtpException('No pending MFA enrollment.');
        }

        $secret = Crypt::decryptString($row->secret_encrypted);

        if (! $this->google2fa->verifyKey($secret, $otp)) {
            $this->audit->writeSecurityEvent('mfa_failure', $user->id, request()->ip());
            throw new InvalidMfaOtpException();
        }

        DB::transaction(function () use ($user) {
            DB::table('identity.mfa_secrets')
                ->where('user_id', $user->id)
                ->update(['confirmed_at' => now()]);

            $user->update(['mfa_enabled' => true]);

            $this->audit->writeEvent(
                actorId:     $user->id,
                companyId:   $user->company_id,
                eventType:   'user.mfa_enabled',
                aggregate:   'User',
                aggregateId: $user->id,
                payload:     [],
            );
        });
    }
}
