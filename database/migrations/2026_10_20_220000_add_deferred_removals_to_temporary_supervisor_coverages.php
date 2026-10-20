<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 03 §10 — defer coverage removals that affect relationships in an Active Visit.
 * Distinct from permanent Supervisor reassignment and from immediate revoke/expire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_supervisor_coverages', function (Blueprint $table) {
            $table->json('deferred_remove_client_ids')->nullable()->after('employee_ids');
            $table->json('deferred_remove_employee_ids')->nullable()->after('deferred_remove_client_ids');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_supervisor_coverages', function (Blueprint $table) {
            $table->dropColumn(['deferred_remove_client_ids', 'deferred_remove_employee_ids']);
        });
    }
};
