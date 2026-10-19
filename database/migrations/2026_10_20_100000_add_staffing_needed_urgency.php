<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 04-29: configurable Staffing Needed urgency threshold + one-time Admin escalation marker.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! IdempotentMigration::hasColumn('organization_settings', 'staffing_needed_urgency_hours')) {
            Schema::table('organization_settings', function (Blueprint $table): void {
                $table->unsignedSmallInteger('staffing_needed_urgency_hours')
                    ->default(24)
                    ->after('incident_acknowledgment_minutes');
            });
        }

        if (! IdempotentMigration::hasColumn('scheduled_visits', 'staffing_urgency_escalated_at')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->timestamp('staffing_urgency_escalated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (IdempotentMigration::hasColumn('scheduled_visits', 'staffing_urgency_escalated_at')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->dropColumn('staffing_urgency_escalated_at');
            });
        }

        if (IdempotentMigration::hasColumn('organization_settings', 'staffing_needed_urgency_hours')) {
            Schema::table('organization_settings', function (Blueprint $table): void {
                $table->dropColumn('staffing_needed_urgency_hours');
            });
        }
    }
};
