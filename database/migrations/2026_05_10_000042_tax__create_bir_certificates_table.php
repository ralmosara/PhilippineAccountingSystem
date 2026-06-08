<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax.bir_certificates — X.509 certificates for EIS payload signing.
 * The actual P12 file lives in MinIO under storage/certs/; this row
 * carries the metadata for selection (active cert + rotation schedule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.bir_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('subject');                          // CN=ABC Trading Inc., O=...
            $table->string('serial_no', 128);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->string('p12_path', 500);                    // MinIO key
            $table->string('passphrase_encrypted')->nullable(); // pgp_sym_encrypt
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();

            $table->unique(['company_id', 'serial_no']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.bir_certificates');
    }
};
