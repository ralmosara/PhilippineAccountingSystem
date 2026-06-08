<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Modules\Identity\Application\Actions\ConfirmMfa;
use App\Modules\Identity\Presentation\Http\Requests\ConfirmMfaRequest;
use Illuminate\Http\JsonResponse;

final class ConfirmMfaController
{
    public function __invoke(ConfirmMfaRequest $request, ConfirmMfa $action): JsonResponse
    {
        $action->execute(
            user: $request->user(),
            otp:  $request->string('otp')->toString(),
        );

        return new JsonResponse(['message' => 'MFA confirmed.']);
    }
}
