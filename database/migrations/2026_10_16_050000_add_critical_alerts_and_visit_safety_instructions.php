<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_critical_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
        });

        Schema::create('client_critical_alert_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_critical_alert_id')
                ->constrained('client_critical_alerts')
                ->cascadeOnDelete();
            $table->unsignedInteger('alert_version');
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(
                ['client_critical_alert_id', 'alert_version', 'visit_id', 'user_id'],
                'critical_alert_ack_unique',
            );
        });

        Schema::create('visit_safety_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_urgent')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('effective_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['visit_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_safety_instructions');
        Schema::dropIfExists('client_critical_alert_acknowledgments');
        Schema::dropIfExists('client_critical_alerts');
    }
};
