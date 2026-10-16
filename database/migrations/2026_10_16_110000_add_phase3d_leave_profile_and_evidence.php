<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('proposed_values');
            $table->json('current_values');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::table('employee_trainings', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('employee_trainings', function (Blueprint $table) {
            $table->dropColumn('document_path');
        });

        Schema::dropIfExists('employee_profile_change_requests');
    }
};
