<?php

use App\Enums\ScheduledVisitStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scheduled_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('shift_template_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('service_type');
            $table->string('status')->default(ScheduledVisitStatus::Scheduled->value);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'service_date']);
            $table->index(['client_id', 'service_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_visits');
    }
};
