<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Ramsey\Uuid\Uuid;

/**
 * Initiates MFA setup for a user. Returns the TOTP secret and a QR-code
 * provisioning URI suitable for Authy / Google Authenticator / 1Password.
 *
 * The user is NOT yet considered MFA-enabled — they must call ConfirmMfa
 * with a valid 6-digit OTP first.
 */
final readonly class EnableMfa
{
    public function __construct(
        private Google2FA $google2fa,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @return array{secret: string, qr_image_data_uri: string, recovery_codes: array<int, string>}
     */
    public function execute(UserModel $user): array
    {
        $secret = $this->google2fa->generateSecretKey(32);
        $recoveryCodes = collect(range(1, 8))
            ->map(fn () => strtoupper(Str::random(10)))
            ->all();

        DB::transaction(function () use ($user, $secret, $recoveryCodes) {
            DB::table('identity.mfa_secrets')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'id'                          => Uuid::uuid4()->toString(),
                    'secret_encrypted'            => Crypt::encryptString($secret),
                    'recovery_codes_encrypted'    => Crypt::encryptString(json_encode($recoveryCodes, JSON_THROW_ON_ERROR)),
                    'confirmed_at'                => null,
                    'updated_at'                  => now(),
                    'created_at'                  => now(),
                ],
            );

            $this->audit->writeEvent(
                actorId:     $user->id,
                companyId:   $user->company_id,
                eventType:   'user.mfa_initiated',
                aggregate:   'User',
                aggregateId: $user->id,
                payload:     [],
            );
        });

        $issuer = config('app.name');
        $uri = $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);

        $renderer = new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($uri);
        $dataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        return [
            'secret'             => $secret,
            'qr_image_data_uri'  => $dataUri,
            'recovery_codes'     => $recoveryCodes,
        ];
    }
}
