<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 05-03 / MASTER §10.5 — Administrative Close metadata stays separate from
 * DSP clock-out EVV (clocked_out_at / clock-out GPS remain untouched).
 *
 * SQLite rebuilds the table when a constrained foreign key is added; recreate the
 * partial one-active-visit-per-employee index afterward so completed Visits (and
 * later linked handoff Visits) are not blocked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->timestamp('administrative_effective_ended_at')->nullable()->after('clocked_out_at');
            $table->timestamp('administratively_closed_at')->nullable()->after('administrative_effective_ended_at');
            $table->foreignId('administratively_closed_by_user_id')
                ->nullable()
                ->after('administratively_closed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('administrative_close_reason')->nullable()->after('administratively_closed_by_user_id');
        });

        $this->restoreOneActiveVisitIndex();
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('administratively_closed_by_user_id');
            $table->dropColumn([
                'administrative_effective_ended_at',
                'administratively_closed_at',
                'administrative_close_reason',
            ]);
        });

        $this->restoreOneActiveVisitIndex();
    }

    private function restoreOneActiveVisitIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_employee');
        DB::statement(
            "CREATE UNIQUE INDEX visits_one_active_per_employee ON visits (employee_id) WHERE status = 'in_progress'"
        );
    }
};
