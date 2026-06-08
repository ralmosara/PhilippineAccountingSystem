<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Modules\Identity\Application\Actions\EnableMfa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action: enable MFA for the current user.
 * Returns a TOTP secret + QR code provisioning URI.
 */
final class EnableMfaController
{
    public function __invoke(Request $request, EnableMfa $action): JsonResponse
    {
        $result = $action->execute($request->user());

        return new JsonResponse([
            'secret'    => $result['secret'],
            'qr_image'  => $result['qr_image_data_uri'],
            'recovery'  => $result['recovery_codes'],
        ]);
    }
}
