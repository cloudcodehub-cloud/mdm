<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! IdempotentMigration::hasColumn('organization_settings', 'incident_acknowledgment_minutes')) {
            Schema::table('organization_settings', function (Blueprint $table): void {
                $table->unsignedSmallInteger('incident_acknowledgment_minutes')
                    ->default(60)
                    ->after('credential_expiring_soon_days');
            });
        }

        IdempotentMigration::createIfMissing('incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('reporter_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('responsible_supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 32);
            $table->string('tracking_number', 64)->nullable()->unique();
            $table->timestamp('occurred_at')->nullable();
            $table->string('original_category', 120)->nullable();
            $table->string('original_subcategory', 120)->nullable();
            $table->string('official_category', 120)->nullable();
            $table->string('official_subcategory', 120)->nullable();
            $table->text('summary')->nullable();
            $table->text('immediate_action')->nullable();
            $table->boolean('injured')->default(false);
            $table->string('injury_type', 120)->nullable();
            $table->string('injury_body_location', 120)->nullable();
            $table->string('injury_medical_check', 255)->nullable();
            $table->text('injury_notes')->nullable();
            $table->boolean('intervention')->default(false);
            $table->string('intervention_type', 120)->nullable();
            $table->timestamp('intervention_started_at')->nullable();
            $table->timestamp('intervention_stopped_at')->nullable();
            $table->text('intervention_notes')->nullable();
            $table->boolean('supervisor_notified')->default(false);
            $table->text('additional_notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->string('location_method', 32)->nullable();
            $table->string('location_status', 32)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledgment_overdue_notified_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->foreignId('discarded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
            $table->index(['client_id', 'status']);
            $table->index(['reporter_employee_id', 'status']);
            $table->index(['responsible_supervisor_id', 'status']);
            $table->index(['visit_id', 'status']);
        });

        IdempotentMigration::createIfMissing('incident_history_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->cascadeOnDelete();
            $table->string('action', 64);
            $table->string('summary');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_history_events');
        Schema::dropIfExists('incidents');

        if (IdempotentMigration::hasColumn('organization_settings', 'incident_acknowledgment_minutes')) {
            Schema::table('organization_settings', function (Blueprint $table): void {
                $table->dropColumn('incident_acknowledgment_minutes');
            });
        }
    }
};
