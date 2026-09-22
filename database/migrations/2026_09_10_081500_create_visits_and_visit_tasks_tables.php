<?php

use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('service_type');
            $table->string('status')->default(VisitStatus::InProgress->value);
            $table->timestamp('clocked_in_at');
            $table->timestamp('clocked_out_at')->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->decimal('clock_in_accuracy', 8, 2)->nullable();
            $table->string('clock_in_location_method');
            $table->string('clock_in_location_status');
            $table->text('clock_in_unavailable_reason')->nullable();
            $table->timestamps();

            $table->unique('scheduled_visit_id');
            $table->index(['employee_id', 'status']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE visits ADD COLUMN active_employee_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'in_progress' THEN employee_id ELSE NULL END) STORED");
            DB::statement('CREATE UNIQUE INDEX visits_one_active_per_employee ON visits (active_employee_id)');
        } else {
            DB::statement("CREATE UNIQUE INDEX visits_one_active_per_employee ON visits (employee_id) WHERE status = 'in_progress'");
        }

        Schema::create('visit_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_plan_task_template_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('recurrence');
            $table->string('recurrence_detail')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default(VisitTaskStatus::Pending->value);
            $table->timestamps();

            $table->unique(['visit_id', 'care_plan_task_template_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_tasks');
        Schema::dropIfExists('visits');
    }
};
