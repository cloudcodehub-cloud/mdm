<?php

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
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
        Schema::create('client_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('authorization_number')->unique();
            $table->string('payer');
            $table->string('service_type');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('authorized_units', 8, 2);
            $table->string('unit')->default(AuthorizationUnit::Hour->value);
            $table->string('status')->default(AuthorizationStatus::Active->value);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_authorizations');
    }
};
