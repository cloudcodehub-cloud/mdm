<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_visits') && ! Schema::hasColumn('scheduled_visits', 'compliance_staffing_status')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->string('compliance_staffing_status')->nullable()->after('staffing_needed');
            });
        }
        if (Schema::hasTable('scheduled_visits') && ! Schema::hasColumn('scheduled_visits', 'compliance_staffing_marked_at')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->timestamp('compliance_staffing_marked_at')->nullable()->after('compliance_staffing_status');
            });
        }
        if (Schema::hasTable('scheduled_visits') && ! Schema::hasColumn('scheduled_visits', 'compliance_staffing_reason')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->text('compliance_staffing_reason')->nullable()->after('compliance_staffing_marked_at');
            });
        }

        IdempotentMigration::createIfMissing('compliance_staffing_episodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grace_scheduled_visit_id')->nullable()->constrained('scheduled_visits')->nullOnDelete();
            $table->text('hard_stop_summary')->nullable();
            $table->timestamp('triggered_at');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('grace_consumed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_staffing_episodes');

        $columns = array_values(array_filter([
            Schema::hasColumn('scheduled_visits', 'compliance_staffing_status') ? 'compliance_staffing_status' : null,
            Schema::hasColumn('scheduled_visits', 'compliance_staffing_marked_at') ? 'compliance_staffing_marked_at' : null,
            Schema::hasColumn('scheduled_visits', 'compliance_staffing_reason') ? 'compliance_staffing_reason' : null,
        ]));

        if ($columns !== []) {
            Schema::table('scheduled_visits', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
