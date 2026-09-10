<?php

use App\Enums\VisitExceptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->text('visit_notes')->nullable()->after('clock_in_unavailable_reason');
            $table->text('handover_note')->nullable()->after('visit_notes');
            $table->decimal('clock_out_latitude', 10, 7)->nullable()->after('clocked_out_at');
            $table->decimal('clock_out_longitude', 10, 7)->nullable()->after('clock_out_latitude');
            $table->decimal('clock_out_accuracy', 8, 2)->nullable()->after('clock_out_longitude');
            $table->string('clock_out_location_method')->nullable()->after('clock_out_accuracy');
            $table->string('clock_out_location_status')->nullable()->after('clock_out_location_method');
            $table->text('clock_out_unavailable_reason')->nullable()->after('clock_out_location_status');
            $table->boolean('unfinished_required_acknowledged')->default(false)->after('clock_out_unavailable_reason');
        });

        Schema::table('visit_tasks', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->timestamp('skipped_at')->nullable()->after('completed_at');
            $table->foreignId('skip_reason_id')->nullable()->after('skipped_at')->constrained('skip_reasons')->restrictOnDelete();
            $table->text('skip_comment')->nullable()->after('skip_reason_id');
            $table->text('completion_note')->nullable()->after('skip_comment');
        });

        Schema::create('visit_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_task_id')->nullable()->constrained('visit_tasks')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default(VisitExceptionStatus::Open->value);
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['visit_id', 'type']);
        });

        DB::statement('CREATE UNIQUE INDEX visit_exceptions_task_unique ON visit_exceptions (visit_id, type, visit_task_id) WHERE visit_task_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX visit_exceptions_visit_unique ON visit_exceptions (visit_id, type) WHERE visit_task_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_exceptions');

        Schema::table('visit_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('skip_reason_id');
            $table->dropColumn([
                'completed_at',
                'skipped_at',
                'skip_comment',
                'completion_note',
            ]);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn([
                'visit_notes',
                'handover_note',
                'clock_out_latitude',
                'clock_out_longitude',
                'clock_out_accuracy',
                'clock_out_location_method',
                'clock_out_location_status',
                'clock_out_unavailable_reason',
                'unfinished_required_acknowledged',
            ]);
        });
    }
};
