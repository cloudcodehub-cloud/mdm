<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Decision 06-03: Temporary Pause replaces legacy "inactive" label/value.
        DB::table('clients')
            ->where('status', 'inactive')
            ->update(['status' => 'paused']);

        Schema::table('clients', function (Blueprint $table) {
            $table->string('status_reason')->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            $table->foreignId('status_changed_by_user_id')
                ->nullable()
                ->after('status_changed_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('care_plans', function (Blueprint $table) {
            $table->date('review_on')->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropColumn('review_on');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by_user_id');
            $table->dropColumn(['status_reason', 'status_changed_at']);
        });

        DB::table('clients')
            ->where('status', 'paused')
            ->update(['status' => 'inactive']);
    }
};
