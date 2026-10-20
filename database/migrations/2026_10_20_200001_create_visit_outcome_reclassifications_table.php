<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 05 Decision 05-10 — Missed → Cancelled with preserved Missed history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_outcome_reclassifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_visit_id')->constrained()->cascadeOnDelete();
            $table->string('from_outcome', 64);
            $table->string('to_outcome', 64);
            $table->text('reason');
            $table->text('evidence_note')->nullable();
            $table->foreignId('reclassified_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reclassified_at');
            $table->timestamps();

            $table->index(['scheduled_visit_id', 'reclassified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_outcome_reclassifications');
    }
};
