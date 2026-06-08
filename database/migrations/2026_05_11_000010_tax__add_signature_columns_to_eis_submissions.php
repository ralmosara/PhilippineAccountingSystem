<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the audit-trail columns BIR auditors look for when re-verifying an
 * EIS submission years after the fact:
 *
 *   payload_hash    — sha256 of the canonical JSON bytes that were signed.
 *                     Lets an auditor re-canonicalize the payload from
 *                     `payload` and confirm it matches the hash recorded
 *                     at transmission time.
 *
 *   cert_thumbprint — sha1 of the X.509 cert used to sign. Pinpoints which
 *                     signing key was in use on the day the invoice was
 *                     transmitted (relevant when certs rotate annually).
 *
 *   http_status     — denormalised HTTP status of the final response,
 *                     so the eis_submissions row is self-explaining without
 *                     joining eis_retries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax.eis_submissions', function (Blueprint $table) {
            $table->string('payload_hash', 64)->nullable()->after('signature');
            $table->string('cert_thumbprint', 40)->nullable()->after('payload_hash');
            $table->smallInteger('http_status')->nullable()->after('cert_thumbprint');

            $table->index('payload_hash');
            $table->index('cert_thumbprint');
        });
    }

    public function down(): void
    {
        Schema::table('tax.eis_submissions', function (Blueprint $table) {
            $table->dropIndex(['payload_hash']);
            $table->dropIndex(['cert_thumbprint']);
            $table->dropColumn(['payload_hash', 'cert_thumbprint', 'http_status']);
        });
    }
};
