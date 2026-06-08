<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Exceptions;

use RuntimeException;

final class EisCertificateExpiredException extends RuntimeException
{
    public static function on(string $subjectCn, int $notAfterUnix): self
    {
        $date = (new \DateTimeImmutable('@'.$notAfterUnix))->format('Y-m-d');
        return new self(
            "EIS signing certificate '{$subjectCn}' expired on {$date}. "
            ."Renew with BIR and update BIR_EIS_CERT_PATH before resuming transmission."
        );
    }
}
