<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source_kind');
            $table->string('source_key');
            $table->string('applicability')->default('dsp');
            $table->string('enforcement_level');
            $table->string('override_policy')->default('non_overrideable');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'enforcement_level']);
            $table->index(['source_kind', 'source_key']);
        });

        Schema::create('compliance_temporary_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compliance_requirement_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'compliance_requirement_id', 'starts_at', 'ends_at'], 'cte_employee_requirement_window');
            $table->index(['employee_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_temporary_exceptions');
        Schema::dropIfExists('compliance_requirements');
    }
};
