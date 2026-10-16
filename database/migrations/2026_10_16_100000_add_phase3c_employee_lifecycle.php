<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('status_reason')->nullable()->after('employment_status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            $table->foreignId('status_changed_by_user_id')
                ->nullable()
                ->after('status_changed_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('employees')
            ->where('employment_status', 'inactive')
            ->update(['employment_status' => 'suspended']);

        DB::table('employees')
            ->where('employment_status', 'terminated')
            ->update(['employment_status' => 'employment_ended']);

        Schema::create('employee_admin_history_events', function (Blueprint $table) {
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

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->boolean('staffing_needed')->default(false)->after('needs_attention');
            $table->unsignedBigInteger('released_employee_id')->nullable()->after('employee_id');
        });

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->change();
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
            $table->foreign('released_employee_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->dropForeign(['released_employee_id']);
            $table->dropColumn(['staffing_needed', 'released_employee_id']);
            $table->dropForeign(['employee_id']);
        });

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('employee_admin_history_events');

        DB::table('employees')
            ->where('employment_status', 'suspended')
            ->update(['employment_status' => 'inactive']);

        DB::table('employees')
            ->where('employment_status', 'employment_ended')
            ->update(['employment_status' => 'terminated']);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by_user_id');
            $table->dropColumn(['status_reason', 'status_changed_at']);
        });
    }
};
