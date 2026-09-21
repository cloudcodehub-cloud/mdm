<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Task-scoped exceptions are unique per visit/type/task.
     * Visit-level exceptions (null visit_task_id) are unique per visit/type.
     *
     * Some SQLite rebuilds dropped the partial WHERE clauses, leaving a
     * visit+type unique that crashes a second legitimate task skip.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_task_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_visit_unique');

        DB::statement('CREATE UNIQUE INDEX visit_exceptions_task_unique ON visit_exceptions (visit_id, type, visit_task_id) WHERE visit_task_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_visit_unique ON visit_exceptions (visit_id, type) WHERE visit_task_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_task_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_visit_unique');

        DB::statement('CREATE UNIQUE INDEX visit_exceptions_task_unique ON visit_exceptions (visit_id, type, visit_task_id)');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_visit_unique ON visit_exceptions (visit_id, type)');
    }
};
