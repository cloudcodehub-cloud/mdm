<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->unsignedInteger('revision_number')->default(1)->after('client_id');
            $table->foreignId('supersedes_care_plan_id')
                ->nullable()
                ->after('revision_number')
                ->constrained('care_plans')
                ->nullOnDelete();
            $table->foreignId('revised_by_user_id')
                ->nullable()
                ->after('supervision_instruction')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('revision_reason', 1000)->nullable()->after('revised_by_user_id');
            $table->timestamp('effective_at')->nullable()->after('revision_reason');

            $table->index(['client_id', 'revision_number']);
            $table->index(['client_id', 'status', 'starts_on']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('care_plan_id')
                ->nullable()
                ->after('client_id')
                ->constrained('care_plans')
                ->nullOnDelete();
        });

        // SQLite rebuilds the visits table when adding columns and can lose / widen the
        // partial unique that allows only one in-progress visit per employee.
        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_employee');
        DB::statement("CREATE UNIQUE INDEX visits_one_active_per_employee ON visits (employee_id) WHERE status = 'in_progress'");
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('care_plan_id');
        });

        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_employee');
        DB::statement("CREATE UNIQUE INDEX visits_one_active_per_employee ON visits (employee_id) WHERE status = 'in_progress'");

        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supersedes_care_plan_id');
            $table->dropConstrainedForeignId('revised_by_user_id');
            $table->dropIndex(['client_id', 'revision_number']);
            $table->dropIndex(['client_id', 'status', 'starts_on']);
            $table->dropColumn([
                'revision_number',
                'revision_reason',
                'effective_at',
            ]);
        });
    }
};
