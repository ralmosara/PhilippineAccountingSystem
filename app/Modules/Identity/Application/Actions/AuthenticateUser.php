<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Identity\Application\Contracts\AuthenticatorContract;
use App\Modules\Identity\Application\Exceptions\InvalidCredentialsException;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\Email;

/**
 * Application Action — single use case, single public method.
 *
 * Controllers invoke ::execute(); they never put logic of their own.
 * This is what keeps controllers thin (Taylor Otwell convention).
 */
final readonly class AuthenticateUser
{
    public function __construct(
        private AuthenticatorContract $authenticator,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{user: User, token: string, expires_at: \DateTimeImmutable, requires_mfa: bool}
     *
     * @throws InvalidCredentialsException
     */
    public function execute(
        string $email,
        string $password,
        string $deviceName,
        string $ipAddress,
    ): array {
        $user = $this->authenticator->attempt(new Email($email), $password);

        if (! $user) {
            $this->audit->writeSecurityEvent('login_failure', null, $ipAddress, ['email' => $email]);
            throw new InvalidCredentialsException();
        }

        $token = $this->authenticator->issueToken($user, $deviceName);

        $this->audit->writeSecurityEvent('login_success', $user->id->value, $ipAddress);

        return [
            'user'         => $user,
            'token'        => $token['token'],
            'expires_at'   => $token['expires_at'],
            'requires_mfa' => $user->requiresMfa(),
        ];
    }
}
