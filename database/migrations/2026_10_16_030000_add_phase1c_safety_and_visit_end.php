<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('care_plans') && ! Schema::hasColumn('care_plans', 'timed_check_interval_minutes')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->unsignedTinyInteger('timed_check_interval_minutes')->nullable()->after('notes');
            });
        }
        if (Schema::hasTable('care_plans') && ! Schema::hasColumn('care_plans', 'supervision_instruction')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->text('supervision_instruction')->nullable()->after('timed_check_interval_minutes');
            });
        }

        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'safety_check_interval_minutes')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->unsignedTinyInteger('safety_check_interval_minutes')->nullable()->after('unfinished_required_acknowledged');
            });
        }
        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'supervision_instruction')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->text('supervision_instruction')->nullable()->after('safety_check_interval_minutes');
            });
        }
        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'general_safety_confirmed_at')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->timestamp('general_safety_confirmed_at')->nullable()->after('supervision_instruction');
            });
        }
        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'extended_visit_reason')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->text('extended_visit_reason')->nullable()->after('general_safety_confirmed_at');
            });
        }

        IdempotentMigration::createIfMissing('visit_safety_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->timestamp('due_at');
            $table->string('status', 32)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['visit_id', 'sequence']);
            $table->index(['visit_id', 'status', 'due_at']);
        });

        if (Schema::hasTable('visit_exceptions') && ! Schema::hasColumn('visit_exceptions', 'visit_safety_check_id')) {
            Schema::table('visit_exceptions', function (Blueprint $table): void {
                $table->foreignId('visit_safety_check_id')
                    ->nullable()
                    ->after('visit_task_id')
                    ->constrained('visit_safety_checks')
                    ->nullOnDelete();
            });
        }

        $this->ensureVisitExceptionPartialIndexes();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS visit_exceptions_task_unique');
            DB::statement('DROP INDEX IF EXISTS visit_exceptions_visit_unique');
            DB::statement('DROP INDEX IF EXISTS visit_exceptions_safety_unique');
        }

        if (Schema::hasColumn('visit_exceptions', 'visit_safety_check_id')) {
            Schema::table('visit_exceptions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('visit_safety_check_id');
            });
        }

        Schema::dropIfExists('visit_safety_checks');

        $visitColumns = array_values(array_filter([
            Schema::hasColumn('visits', 'safety_check_interval_minutes') ? 'safety_check_interval_minutes' : null,
            Schema::hasColumn('visits', 'supervision_instruction') ? 'supervision_instruction' : null,
            Schema::hasColumn('visits', 'general_safety_confirmed_at') ? 'general_safety_confirmed_at' : null,
            Schema::hasColumn('visits', 'extended_visit_reason') ? 'extended_visit_reason' : null,
        ]));
        if ($visitColumns !== []) {
            Schema::table('visits', function (Blueprint $table) use ($visitColumns): void {
                $table->dropColumn($visitColumns);
            });
        }

        $carePlanColumns = array_values(array_filter([
            Schema::hasColumn('care_plans', 'timed_check_interval_minutes') ? 'timed_check_interval_minutes' : null,
            Schema::hasColumn('care_plans', 'supervision_instruction') ? 'supervision_instruction' : null,
        ]));
        if ($carePlanColumns !== []) {
            Schema::table('care_plans', function (Blueprint $table) use ($carePlanColumns): void {
                $table->dropColumn($carePlanColumns);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX visit_exceptions_task_unique ON visit_exceptions (visit_id, type, visit_task_id) WHERE visit_task_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX visit_exceptions_visit_unique ON visit_exceptions (visit_id, type) WHERE visit_task_id IS NULL');
        }
    }

    private function ensureVisitExceptionPartialIndexes(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS visit_exceptions_task_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_visit_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_safety_unique');

        DB::statement('CREATE UNIQUE INDEX visit_exceptions_task_unique ON visit_exceptions (visit_id, type, visit_task_id) WHERE visit_task_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_safety_unique ON visit_exceptions (visit_id, type, visit_safety_check_id) WHERE visit_safety_check_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_visit_unique ON visit_exceptions (visit_id, type) WHERE visit_task_id IS NULL AND visit_safety_check_id IS NULL');
    }
};
