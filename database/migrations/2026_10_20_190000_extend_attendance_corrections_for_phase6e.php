<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 05-05/05-07 / MDM 01 §2.44 — Missed zero-EVV reconstruction history and
 * task/status correction payloads on the existing AttendanceCorrection model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->string('original_attendance_status')->nullable()->after('proposed_fields');
            $table->json('task_corrections')->nullable()->after('requested_clock_out_unavailable_reason');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropColumn(['original_attendance_status', 'task_corrections']);
        });
    }
};
