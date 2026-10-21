<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 03 §8 — Admin reopen of resolved Visit Exceptions with preserved resolution history.
 *
 * SQLite rebuilds the table when adding constrained foreign IDs. Drop the partial
 * unique indexes before that rebuild so Laravel cannot recreate them without their
 * WHERE clauses, then restore the exact conditional definitions afterward.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropPartialUniques();

        Schema::table('visit_exceptions', function (Blueprint $table) {
            $table->timestamp('original_resolved_at')->nullable()->after('resolution_notes');
            $table->foreignId('original_resolved_by_user_id')->nullable()->after('original_resolved_at')
                ->constrained('users')->nullOnDelete();
            $table->text('original_resolution_notes')->nullable()->after('original_resolved_by_user_id');
            $table->timestamp('reopened_at')->nullable()->after('original_resolution_notes');
            $table->foreignId('reopened_by_user_id')->nullable()->after('reopened_at')
                ->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by_user_id');
        });

        $this->restorePartialUniques();
    }

    public function down(): void
    {
        $this->dropPartialUniques();

        Schema::table('visit_exceptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('original_resolved_by_user_id');
            $table->dropConstrainedForeignId('reopened_by_user_id');
            $table->dropColumn([
                'original_resolved_at',
                'original_resolution_notes',
                'reopened_at',
                'reopen_reason',
            ]);
        });

        $this->restorePartialUniques();
    }

    private function dropPartialUniques(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS visit_exceptions_task_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_safety_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_visit_unique');
    }

    private function restorePartialUniques(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS visit_exceptions_task_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_safety_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_visit_unique');

        DB::statement('CREATE UNIQUE INDEX visit_exceptions_task_unique ON visit_exceptions (visit_id, type, visit_task_id) WHERE visit_task_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_safety_unique ON visit_exceptions (visit_id, type, visit_safety_check_id) WHERE visit_safety_check_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_visit_unique ON visit_exceptions (visit_id, type) WHERE visit_task_id IS NULL AND visit_safety_check_id IS NULL');
    }
};
