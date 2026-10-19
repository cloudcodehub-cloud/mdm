<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 01 §2.30 — Active-visit Visit Note corrections preserve original content,
 * actor, and timestamp. Post clock-out remains Request Correction (MDM 05).
 */
return new class extends Migration
{
    public function up(): void
    {
        IdempotentMigration::createIfMissing('visit_note_history_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('field_key', 64);
            $table->text('previous_content')->nullable();
            $table->text('new_content')->nullable();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['visit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_note_history_events');
    }
};
