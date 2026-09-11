<?php

use App\Enums\AvailabilityRequestType;
use App\Enums\ReviewStatus;
use App\Enums\VisitAssignmentKind;
use App\Enums\VisitRecurrencePattern;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsp_weekly_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_available')->default(true);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('preferred_daypart')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'weekday']);
        });

        Schema::create('dsp_availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('exception_date');
            $table->boolean('is_available')->default(false);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'exception_date']);
        });

        Schema::create('dsp_availability_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default(AvailabilityRequestType::Weekly->value);
            $table->date('effective_on');
            $table->json('payload');
            $table->text('reason')->nullable();
            $table->string('status')->default(ReviewStatus::Pending->value);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('employee_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default(ReviewStatus::Pending->value);
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['employee_id', 'starts_on', 'ends_on']);
        });

        Schema::create('scheduled_visit_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('shift_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_type');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('pattern')->default(VisitRecurrencePattern::Weekly->value);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->json('days_of_week')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('occurrence_count')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('notes')->constrained('scheduled_visit_series')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('series_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->boolean('needs_attention')->default(false)->after('updated_by_user_id');
            $table->text('attention_reason')->nullable()->after('needs_attention');
            $table->timestamp('cancelled_at')->nullable()->after('attention_reason');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by_user_id');
            $table->text('replacement_reason')->nullable()->after('cancellation_reason');
        });

        Schema::create('scheduled_visit_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->default(VisitAssignmentKind::Assigned->value);
            $table->text('reason')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['scheduled_visit_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_visit_assignments');

        Schema::table('scheduled_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('updated_by_user_id');
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn([
                'needs_attention',
                'attention_reason',
                'cancelled_at',
                'cancellation_reason',
                'replacement_reason',
            ]);
        });

        Schema::dropIfExists('scheduled_visit_series');
        Schema::dropIfExists('employee_time_off');
        Schema::dropIfExists('dsp_availability_requests');
        Schema::dropIfExists('dsp_availability_exceptions');
        Schema::dropIfExists('dsp_weekly_availabilities');
    }
};
