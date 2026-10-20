<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 11-06 horizontal logo + Phase 9B Admin Documents discovery surface support.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (IdempotentMigration::hasTable('organization_settings')
            && ! IdempotentMigration::hasColumn('organization_settings', 'horizontal_logo_path')) {
            Schema::table('organization_settings', function (Blueprint $table): void {
                $table->string('horizontal_logo_path')->nullable()->after('logo_path');
            });
        }
    }

    public function down(): void
    {
        if (IdempotentMigration::hasColumn('organization_settings', 'horizontal_logo_path')) {
            Schema::table('organization_settings', function (Blueprint $table): void {
                $table->dropColumn('horizontal_logo_path');
            });
        }
    }
};
