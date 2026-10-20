<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 03 §10 / MASTER §4.4 — Admin-controlled temporary Supervisor coverage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_supervisor_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_supervisor_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('covering_supervisor_id')->constrained('employees')->cascadeOnDelete();
            $table->string('mode', 32);
            $table->json('client_ids')->nullable();
            $table->json('employee_ids')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['covering_supervisor_id', 'starts_at', 'ends_at']);
            $table->index(['original_supervisor_id', 'starts_at', 'ends_at']);
        });

        Schema::create('temporary_supervisor_coverage_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temporary_supervisor_coverage_id')->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_supervisor_coverage_audits');
        Schema::dropIfExists('temporary_supervisor_coverages');
    }
};
