<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 05 §10.6–10.7 / MASTER — linked handoff Visits share one Scheduled Visit.
 * Drop 1:1 unique; keep one-active-Visit-per-DSP; optionally one in-progress
 * segment per Scheduled Visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop before SQLite FK rebuild so the partial unique is not recreated
        // as an unconditional UNIQUE(employee_id).
        $this->dropOneActivePerEmployee();

        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('replaced_visit_id')
                ->nullable()
                ->after('scheduled_visit_id')
                ->constrained('visits')
                ->nullOnDelete();
            $table->timestamp('handed_off_at')->nullable()->after('administrative_close_reason');
            $table->foreignId('handed_off_by_user_id')
                ->nullable()
                ->after('handed_off_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('handoff_reason')->nullable()->after('handed_off_by_user_id');
        });

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->boolean('handoff_pending')->default(false)->after('needs_attention');
        });

        $this->dropScheduledVisitUnique();
        $this->restoreVisitIndexes();
    }

    public function down(): void
    {
        $this->dropOneActivePerEmployee();

        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handed_off_by_user_id');
            $table->dropConstrainedForeignId('replaced_visit_id');
            $table->dropColumn(['handed_off_at', 'handoff_reason']);
        });

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->dropColumn('handoff_pending');
        });

        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_scheduled');
        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_employee');

        // Restore legacy 1:1 unique only when safe (no multi-segment rows).
        $dupes = DB::table('visits')
            ->select('scheduled_visit_id')
            ->groupBy('scheduled_visit_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($dupes === 0) {
            Schema::table('visits', function (Blueprint $table) {
                $table->unique('scheduled_visit_id');
            });
        }

        $this->restoreOneActivePerEmployee();
    }

    private function dropScheduledVisitUnique(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite may rebuild the table when FKs were added; drop by known names.
            DB::statement('DROP INDEX IF EXISTS visits_scheduled_visit_id_unique');
            DB::statement('DROP INDEX IF EXISTS visits_scheduled_visit_id_unique');

            return;
        }

        Schema::table('visits', function (Blueprint $table) {
            $table->dropUnique(['scheduled_visit_id']);
        });
    }

    private function restoreVisitIndexes(): void
    {
        $this->restoreOneActivePerEmployee();

        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_scheduled');
        DB::statement(
            "CREATE UNIQUE INDEX visits_one_active_per_scheduled ON visits (scheduled_visit_id) WHERE status = 'in_progress'"
        );
    }

    private function dropOneActivePerEmployee(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_employee');
    }

    private function restoreOneActivePerEmployee(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_employee');
        DB::statement(
            "CREATE UNIQUE INDEX visits_one_active_per_employee ON visits (employee_id) WHERE status = 'in_progress'"
        );
    }
};

