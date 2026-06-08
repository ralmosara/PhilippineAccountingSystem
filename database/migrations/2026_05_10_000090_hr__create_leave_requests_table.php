<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TYPE hr.leave_type AS ENUM (
            'sick', 'vacation', 'emergency', 'maternity', 'paternity', 'solo_parent', 'bereavement'
        )");

        DB::unprepared("CREATE TYPE hr.leave_status AS ENUM (
            'pending', 'approved', 'rejected', 'cancelled'
        )");

        Schema::create('hr.leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('employee_id');       // logical ref to hr.employees, no FK constraint
            $table->timestampsTz();
        });

        // Custom columns that Blueprint does not support natively for custom Postgres enums
        DB::unprepared("ALTER TABLE hr.leave_requests
            ADD COLUMN leave_type    hr.leave_type   NOT NULL,
            ADD COLUMN start_date    DATE            NOT NULL,
            ADD COLUMN end_date      DATE            NOT NULL,
            ADD COLUMN days_requested NUMERIC(5,2)   NOT NULL CHECK (days_requested > 0),
            ADD COLUMN reason        TEXT,
            ADD COLUMN status        hr.leave_status NOT NULL DEFAULT 'pending',
            ADD COLUMN approved_by   UUID,
            ADD COLUMN approved_at   TIMESTAMPTZ,
            ADD COLUMN rejection_reason TEXT
        ");

        DB::unprepared('CREATE INDEX ON hr.leave_requests (company_id, employee_id)');
        DB::unprepared('CREATE INDEX ON hr.leave_requests (company_id, status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hr.leave_requests');
        DB::unprepared('DROP TYPE IF EXISTS hr.leave_status');
        DB::unprepared('DROP TYPE IF EXISTS hr.leave_type');
    }
};
