<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_visit_one_off_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_visit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->boolean('note_required')->default(false);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('visit_tasks', function (Blueprint $table) {
            $table->foreignId('scheduled_visit_one_off_task_id')
                ->nullable()
                ->after('care_plan_task_template_id')
                ->constrained('scheduled_visit_one_off_tasks')
                ->restrictOnDelete();
            $table->unique(['visit_id', 'scheduled_visit_one_off_task_id'], 'visit_tasks_one_off_unique');
        });

        Schema::table('visit_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('care_plan_task_template_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('visit_tasks', function (Blueprint $table) {
            $table->dropUnique('visit_tasks_one_off_unique');
            $table->dropConstrainedForeignId('scheduled_visit_one_off_task_id');
        });

        Schema::table('visit_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('care_plan_task_template_id')->nullable(false)->change();
        });

        Schema::dropIfExists('scheduled_visit_one_off_tasks');
    }
};
