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

        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'status_reason')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->string('status_reason')->nullable()->after('status');
            });
        }
        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'status_changed_at')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            });
        }
        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'status_changed_by_user_id')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->foreignId('status_changed_by_user_id')
                    ->nullable()
                    ->after('status_changed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('care_plans') && ! Schema::hasColumn('care_plans', 'review_on')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->date('review_on')->nullable()->after('ends_on');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('care_plans', 'review_on')) {
            Schema::table('care_plans', function (Blueprint $table): void {
                $table->dropColumn('review_on');
            });
        }

        if (Schema::hasColumn('clients', 'status_changed_by_user_id')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('status_changed_by_user_id');
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('clients', 'status_reason') ? 'status_reason' : null,
            Schema::hasColumn('clients', 'status_changed_at') ? 'status_changed_at' : null,
        ]));
        if ($columns !== []) {
            Schema::table('clients', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        DB::table('clients')
            ->where('status', 'paused')
            ->update(['status' => 'inactive']);
    }
};
