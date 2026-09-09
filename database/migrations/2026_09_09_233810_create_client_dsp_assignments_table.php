<?php

use App\Enums\AssignmentStatus;
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
        Schema::create('client_dsp_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('status')->default(AssignmentStatus::Active->value);
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'client_id']);
            $table->index(['status', 'started_on', 'ended_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_dsp_assignments');
    }
};
