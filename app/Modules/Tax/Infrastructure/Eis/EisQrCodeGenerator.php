<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Eis;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/**
 * Generates the QR code PNG that gets printed on every invoice ack'd by EIS.
 * The QR encodes the BIR-supplied portal URL — a customer scanning it lands
 * on https://eis.bir.gov.ph/invoice/<ack_no> to verify the invoice.
 *
 * Output:  raw PNG bytes (caller stores them on MinIO/S3 under
 *          eis/qr/<sales_invoice_id>.png and writes the path to
 *          tax.eis_submissions.qr_image_path).
 *
 * BaconQrCode v3 ships a pure-PHP GD renderer — no Imagick required.
 */
final readonly class EisQrCodeGenerator
{
    public function __construct(
        private int $sizePx        = 256,
        private int $marginModules = 2,
    ) {
    }

    public function generate(string $qrUrl): string
    {
        $renderer = new GDLibRenderer(
            size:   $this->sizePx,
            margin: $this->marginModules,
        );

        $writer = new Writer($renderer);
        return $writer->writeString($qrUrl);
    }
}
