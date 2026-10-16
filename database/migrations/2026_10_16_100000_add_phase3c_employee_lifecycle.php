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
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'status_reason')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->string('status_reason')->nullable()->after('employment_status');
            });
        }
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'status_changed_at')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            });
        }
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'status_changed_by_user_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->foreignId('status_changed_by_user_id')
                    ->nullable()
                    ->after('status_changed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        DB::table('employees')
            ->where('employment_status', 'inactive')
            ->update(['employment_status' => 'suspended']);

        DB::table('employees')
            ->where('employment_status', 'terminated')
            ->update(['employment_status' => 'employment_ended']);

        IdempotentMigration::createIfMissing('employee_admin_history_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('summary');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->date('effective_on')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'created_at']);
        });

        if (Schema::hasTable('scheduled_visits') && ! Schema::hasColumn('scheduled_visits', 'staffing_needed')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->boolean('staffing_needed')->default(false)->after('needs_attention');
            });
        }
        if (Schema::hasTable('scheduled_visits') && ! Schema::hasColumn('scheduled_visits', 'released_employee_id')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->unsignedBigInteger('released_employee_id')->nullable()->after('employee_id');
            });
        }

        $this->ensureNullableEmployeeAssignment();
    }

    public function down(): void
    {
        if (Schema::hasColumn('scheduled_visits', 'released_employee_id')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->dropForeign(['released_employee_id']);
                $table->dropColumn('released_employee_id');
            });
        }
        if (Schema::hasColumn('scheduled_visits', 'staffing_needed')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->dropColumn('staffing_needed');
            });
        }

        Schema::dropIfExists('employee_admin_history_events');

        DB::table('employees')
            ->where('employment_status', 'suspended')
            ->update(['employment_status' => 'inactive']);

        DB::table('employees')
            ->where('employment_status', 'employment_ended')
            ->update(['employment_status' => 'terminated']);

        if (Schema::hasColumn('employees', 'status_changed_by_user_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('status_changed_by_user_id');
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('employees', 'status_reason') ? 'status_reason' : null,
            Schema::hasColumn('employees', 'status_changed_at') ? 'status_changed_at' : null,
        ]));
        if ($columns !== []) {
            Schema::table('employees', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function ensureNullableEmployeeAssignment(): void
    {
        if (! Schema::hasTable('scheduled_visits')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite cannot ALTER COLUMN nullability in place; rebuild only when still NOT NULL.
            $info = DB::select('PRAGMA table_info(scheduled_visits)');
            $employeeCol = collect($info)->firstWhere('name', 'employee_id');
            $needsNullable = $employeeCol !== null && (int) $employeeCol->notnull === 1;

            if ($needsNullable) {
                Schema::table('scheduled_visits', function (Blueprint $table): void {
                    $table->dropForeign(['employee_id']);
                });

                Schema::table('scheduled_visits', function (Blueprint $table): void {
                    $table->unsignedBigInteger('employee_id')->nullable()->change();
                });

                Schema::table('scheduled_visits', function (Blueprint $table): void {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employees')
                        ->nullOnDelete();
                });
            }

            $foreignKeys = collect(DB::select("PRAGMA foreign_key_list(scheduled_visits)"))
                ->pluck('from')
                ->all();

            if (Schema::hasColumn('scheduled_visits', 'released_employee_id')
                && ! in_array('released_employee_id', $foreignKeys, true)) {
                Schema::table('scheduled_visits', function (Blueprint $table): void {
                    $table->foreign('released_employee_id')
                        ->references('id')
                        ->on('employees')
                        ->nullOnDelete();
                });
            }

            return;
        }

        // MySQL/MariaDB path (fresh installs and non-SQLite environments).
        Schema::table('scheduled_visits', function (Blueprint $table): void {
            $table->dropForeign(['employee_id']);
        });

        Schema::table('scheduled_visits', function (Blueprint $table): void {
            $table->unsignedBigInteger('employee_id')->nullable()->change();
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
            if (Schema::hasColumn('scheduled_visits', 'released_employee_id')) {
                $table->foreign('released_employee_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            }
        });
    }
};
