<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 05 §L / Decisions 05-02, 05-04, 05-05, 05-22 — extend the existing
 * AttendanceCorrection architecture for notes, GPS context, and EVV reconstruction.
 * Original Visit EVV rows remain immutable; approved overlays apply effective values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->boolean('is_evv_reconstruction')->default(false)->after('status');
            $table->boolean('overlap_conflict')->default(false)->after('is_evv_reconstruction');
            $table->json('proposed_fields')->nullable()->after('overlap_conflict');

            $table->text('original_visit_notes')->nullable()->after('requested_clocked_out_at');
            $table->text('requested_visit_notes')->nullable()->after('original_visit_notes');
            $table->text('original_handover_note')->nullable()->after('requested_visit_notes');
            $table->text('requested_handover_note')->nullable()->after('original_handover_note');

            $table->string('original_clock_in_location_status')->nullable()->after('requested_handover_note');
            $table->string('requested_clock_in_location_status')->nullable()->after('original_clock_in_location_status');
            $table->text('original_clock_in_unavailable_reason')->nullable()->after('requested_clock_in_location_status');
            $table->text('requested_clock_in_unavailable_reason')->nullable()->after('original_clock_in_unavailable_reason');
            $table->string('original_clock_out_location_status')->nullable()->after('requested_clock_in_unavailable_reason');
            $table->string('requested_clock_out_location_status')->nullable()->after('original_clock_out_location_status');
            $table->text('original_clock_out_unavailable_reason')->nullable()->after('requested_clock_out_location_status');
            $table->text('requested_clock_out_unavailable_reason')->nullable()->after('original_clock_out_unavailable_reason');

            $table->timestamp('applied_at')->nullable()->after('review_note');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->boolean('evv_reconstructed')->default(false)->after('handoff_reason');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropColumn([
                'is_evv_reconstruction',
                'overlap_conflict',
                'proposed_fields',
                'original_visit_notes',
                'requested_visit_notes',
                'original_handover_note',
                'requested_handover_note',
                'original_clock_in_location_status',
                'requested_clock_in_location_status',
                'original_clock_in_unavailable_reason',
                'requested_clock_in_unavailable_reason',
                'original_clock_out_location_status',
                'requested_clock_out_location_status',
                'original_clock_out_unavailable_reason',
                'requested_clock_out_unavailable_reason',
                'applied_at',
            ]);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('evv_reconstructed');
        });
    }
};
