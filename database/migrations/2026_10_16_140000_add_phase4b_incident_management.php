<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (IdempotentMigration::hasTable('incidents')) {
            Schema::table('incidents', function (Blueprint $table): void {
                if (! IdempotentMigration::hasColumn('incidents', 'injury_occurred_at')) {
                    $table->timestamp('injury_occurred_at')->nullable()->after('injury_notes');
                }
                if (! IdempotentMigration::hasColumn('incidents', 'finding')) {
                    $table->text('finding')->nullable()->after('official_subcategory');
                }
                if (! IdempotentMigration::hasColumn('incidents', 'cause_contributing_factors')) {
                    $table->text('cause_contributing_factors')->nullable()->after('finding');
                }
                if (! IdempotentMigration::hasColumn('incidents', 'prevention_plan')) {
                    $table->text('prevention_plan')->nullable()->after('cause_contributing_factors');
                }
                if (! IdempotentMigration::hasColumn('incidents', 'closed_at')) {
                    $table->timestamp('closed_at')->nullable()->after('acknowledgment_overdue_notified_at');
                    $table->foreignId('closed_by_user_id')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
                    $table->text('closure_note')->nullable()->after('closed_by_user_id');
                    $table->timestamp('original_closed_at')->nullable()->after('closure_note');
                    $table->foreignId('original_closed_by_user_id')->nullable()->after('original_closed_at')->constrained('users')->nullOnDelete();
                    $table->text('original_closure_note')->nullable()->after('original_closed_by_user_id');
                    $table->timestamp('reopened_at')->nullable()->after('original_closure_note');
                    $table->foreignId('reopened_by_user_id')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
                    $table->text('reopen_reason')->nullable()->after('reopened_by_user_id');
                }
            });
        }

        IdempotentMigration::createIfMissing('incident_parties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('party_kind', 32);
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('organization')->nullable();
            $table->string('involvement')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['incident_id', 'party_kind']);
        });

        IdempotentMigration::createIfMissing('incident_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->text('note');
            $table->json('determinations')->nullable();
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
        });

        IdempotentMigration::createIfMissing('incident_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('action_type', 120);
            $table->foreignId('assigned_employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 32);
            $table->text('completion_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['incident_id', 'status']);
            $table->index(['assigned_employee_id', 'status']);
        });

        IdempotentMigration::createIfMissing('incident_reporting_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('reported_to');
            $table->string('person_organization')->nullable();
            $table->timestamp('reported_at');
            $table->foreignId('reported_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('category', 120)->nullable();
            $table->string('method', 120)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['incident_id', 'reported_at']);
        });

        IdempotentMigration::createIfMissing('incident_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason');
            $table->string('field_key', 64);
            $table->text('original_content')->nullable();
            $table->text('new_content');
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_amendments');
        Schema::dropIfExists('incident_reporting_records');
        Schema::dropIfExists('incident_follow_ups');
        Schema::dropIfExists('incident_reviews');
        Schema::dropIfExists('incident_parties');

        if (IdempotentMigration::hasColumn('incidents', 'finding')) {
            Schema::table('incidents', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('reopened_by_user_id');
                $table->dropConstrainedForeignId('original_closed_by_user_id');
                $table->dropConstrainedForeignId('closed_by_user_id');
                $table->dropColumn([
                    'injury_occurred_at',
                    'finding',
                    'cause_contributing_factors',
                    'prevention_plan',
                    'closed_at',
                    'closure_note',
                    'original_closed_at',
                    'original_closure_note',
                    'reopened_at',
                    'reopen_reason',
                ]);
            });
        }
    }
};
