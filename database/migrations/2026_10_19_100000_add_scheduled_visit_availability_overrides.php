<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentMigration::createIfMissing('scheduled_visit_availability_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduled_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('conflict_kind', 32);
            $table->text('conflict_summary');
            $table->text('override_reason');
            $table->foreignId('overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scheduled_visit_id', 'employee_id'], 'sva_overrides_visit_employee_idx');
            $table->index(['employee_id', 'acknowledged_at'], 'sva_overrides_employee_ack_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_visit_availability_overrides');
    }
};
