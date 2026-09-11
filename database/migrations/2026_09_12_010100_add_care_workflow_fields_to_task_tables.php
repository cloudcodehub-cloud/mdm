<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_plan_task_templates', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')
                ->nullable()
                ->after('care_plan_id')
                ->constrained('task_catalog_items')
                ->nullOnDelete();
            $table->json('weekdays')->nullable()->after('recurrence_detail');
            $table->unsignedTinyInteger('interval_weeks')->nullable()->after('weekdays');
            $table->string('preferred_timing')->nullable()->after('interval_weeks');
            $table->boolean('note_required')->default(false)->after('is_required');
            $table->boolean('can_skip')->default(true)->after('note_required');
            $table->boolean('is_critical')->default(false)->after('can_skip');
            $table->boolean('is_active')->default(true)->after('sort_order');
        });

        Schema::table('visit_tasks', function (Blueprint $table) {
            $table->string('preferred_timing')->nullable()->after('recurrence_detail');
            $table->boolean('note_required')->default(false)->after('is_required');
            $table->boolean('can_skip')->default(true)->after('note_required');
            $table->boolean('is_critical')->default(false)->after('can_skip');
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->json('care_context')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn('care_context');
        });

        Schema::table('visit_tasks', function (Blueprint $table) {
            $table->dropColumn(['preferred_timing', 'note_required', 'can_skip', 'is_critical']);
        });

        Schema::table('care_plan_task_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_item_id');
            $table->dropColumn([
                'weekdays',
                'interval_weeks',
                'preferred_timing',
                'note_required',
                'can_skip',
                'is_critical',
                'is_active',
            ]);
        });
    }
};
