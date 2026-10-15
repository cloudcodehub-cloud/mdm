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
            $table->unsignedTinyInteger('timed_check_interval_minutes')->nullable()->after('notes');
            $table->text('supervision_instruction')->nullable()->after('timed_check_interval_minutes');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->unsignedTinyInteger('safety_check_interval_minutes')->nullable()->after('unfinished_required_acknowledged');
            $table->text('supervision_instruction')->nullable()->after('safety_check_interval_minutes');
            $table->timestamp('general_safety_confirmed_at')->nullable()->after('supervision_instruction');
            $table->text('extended_visit_reason')->nullable()->after('general_safety_confirmed_at');
        });

        Schema::create('visit_safety_checks', function (Blueprint $table) {
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

        Schema::table('visit_exceptions', function (Blueprint $table) {
            $table->foreignId('visit_safety_check_id')
                ->nullable()
                ->after('visit_task_id')
                ->constrained('visit_safety_checks')
                ->nullOnDelete();
        });

        DB::statement('DROP INDEX IF EXISTS visit_exceptions_task_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_visit_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_safety_unique');

        DB::statement('CREATE UNIQUE INDEX visit_exceptions_task_unique ON visit_exceptions (visit_id, type, visit_task_id) WHERE visit_task_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_safety_unique ON visit_exceptions (visit_id, type, visit_safety_check_id) WHERE visit_safety_check_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_visit_unique ON visit_exceptions (visit_id, type) WHERE visit_task_id IS NULL AND visit_safety_check_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_task_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_visit_unique');
        DB::statement('DROP INDEX IF EXISTS visit_exceptions_safety_unique');

        Schema::table('visit_exceptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visit_safety_check_id');
        });

        Schema::dropIfExists('visit_safety_checks');

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn([
                'safety_check_interval_minutes',
                'supervision_instruction',
                'general_safety_confirmed_at',
                'extended_visit_reason',
            ]);
        });

        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropColumn([
                'timed_check_interval_minutes',
                'supervision_instruction',
            ]);
        });

        DB::statement('CREATE UNIQUE INDEX visit_exceptions_task_unique ON visit_exceptions (visit_id, type, visit_task_id) WHERE visit_task_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_visit_unique ON visit_exceptions (visit_id, type) WHERE visit_task_id IS NULL');
    }
};
