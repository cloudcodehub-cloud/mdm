<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('care_plans') && ! Schema::hasColumn('care_plans', 'revision_number')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->unsignedInteger('revision_number')->default(1)->after('client_id');
            });
        }
        if (Schema::hasTable('care_plans') && ! Schema::hasColumn('care_plans', 'supersedes_care_plan_id')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->foreignId('supersedes_care_plan_id')
                    ->nullable()
                    ->after('revision_number')
                    ->constrained('care_plans')
                    ->nullOnDelete();
            });
        }
        if (Schema::hasTable('care_plans') && ! Schema::hasColumn('care_plans', 'revised_by_user_id')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->foreignId('revised_by_user_id')
                    ->nullable()
                    ->after('supervision_instruction')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
        if (Schema::hasTable('care_plans') && ! Schema::hasColumn('care_plans', 'revision_reason')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->string('revision_reason', 1000)->nullable()->after('revised_by_user_id');
            });
        }
        if (Schema::hasTable('care_plans') && ! Schema::hasColumn('care_plans', 'effective_at')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->timestamp('effective_at')->nullable()->after('revision_reason');
            });
        }

        $this->ensureCarePlanIndexes();

        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'care_plan_id')) {
            // Drop the SQLite partial unique before any rebuild so Laravel cannot
            // recreate it as a full UNIQUE(employee_id) during table recreation.
            $this->dropActiveVisitPartialIndex();

            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                DB::statement('ALTER TABLE visits ADD COLUMN care_plan_id INTEGER NULL REFERENCES care_plans(id)');
            } else {
                Schema::table('visits', function (Blueprint $table): void {
                    $table->foreignId('care_plan_id')
                        ->nullable()
                        ->after('client_id')
                        ->constrained('care_plans')
                        ->nullOnDelete();
                });
            }

            $this->ensureActiveVisitPartialIndex();
        } elseif (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->ensureActiveVisitPartialIndex();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('visits', 'care_plan_id')) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                // SQLite cannot DROP COLUMN with FK cleanly in all versions; leave
                // reverse migrations for non-production use.
                Schema::table('visits', function (Blueprint $table): void {
                    $table->dropColumn('care_plan_id');
                });
            } else {
                Schema::table('visits', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('care_plan_id');
                });
            }
        }

        $this->ensureActiveVisitPartialIndex();

        if (Schema::hasColumn('care_plans', 'supersedes_care_plan_id')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('supersedes_care_plan_id');
            });
        }
        if (Schema::hasColumn('care_plans', 'revised_by_user_id')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('revised_by_user_id');
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('care_plans', 'revision_number') ? 'revision_number' : null,
            Schema::hasColumn('care_plans', 'revision_reason') ? 'revision_reason' : null,
            Schema::hasColumn('care_plans', 'effective_at') ? 'effective_at' : null,
        ]));
        if ($columns !== []) {
            Schema::table('care_plans', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function ensureCarePlanIndexes(): void
    {
        if (! Schema::hasTable('care_plans')) {
            return;
        }

        $sm = Schema::getConnection()->getSchemaBuilder();
        $existing = [];
        if (method_exists($sm, 'getIndexes')) {
            foreach ($sm->getIndexes('care_plans') as $index) {
                $existing[] = $index['name'] ?? '';
            }
        }

        Schema::table('care_plans', function (Blueprint $table) use ($existing): void {
            if (! in_array('care_plans_client_id_revision_number_index', $existing, true)) {
                $table->index(['client_id', 'revision_number']);
            }
            if (! in_array('care_plans_client_id_status_starts_on_index', $existing, true)) {
                $table->index(['client_id', 'status', 'starts_on']);
            }
        });
    }

    private function dropActiveVisitPartialIndex(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_employee');
    }

    private function ensureActiveVisitPartialIndex(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS visits_one_active_per_employee');
        DB::statement("CREATE UNIQUE INDEX visits_one_active_per_employee ON visits (employee_id) WHERE status = 'in_progress'");
    }
};
