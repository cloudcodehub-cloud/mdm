<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 11 — Agency Profile fields, future-effective timezone governance,
 * schedule timezone context, and high-risk settings audit history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (IdempotentMigration::hasTable('organization_settings')) {
            Schema::table('organization_settings', function (Blueprint $table): void {
                if (! IdempotentMigration::hasColumn('organization_settings', 'display_name')) {
                    $table->string('display_name')->nullable()->after('organization_name');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'address_line_1')) {
                    $table->string('address_line_1')->nullable()->after('display_name');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'address_line_2')) {
                    $table->string('address_line_2')->nullable()->after('address_line_1');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'country')) {
                    $table->string('country', 100)->nullable()->after('postal_code');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'main_phone')) {
                    $table->string('main_phone', 50)->nullable()->after('country');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'main_email')) {
                    $table->string('main_email')->nullable()->after('main_phone');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'website')) {
                    $table->string('website')->nullable()->after('main_email');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'pending_timezone')) {
                    $table->string('pending_timezone')->nullable()->after('timezone');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'pending_timezone_effective_at')) {
                    $table->timestamp('pending_timezone_effective_at')->nullable()->after('pending_timezone');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'pending_timezone_reason')) {
                    $table->text('pending_timezone_reason')->nullable()->after('pending_timezone_effective_at');
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'pending_timezone_scheduled_by')) {
                    $table->foreignId('pending_timezone_scheduled_by')
                        ->nullable()
                        ->after('pending_timezone_reason')
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (! IdempotentMigration::hasColumn('organization_settings', 'pending_timezone_scheduled_at')) {
                    $table->timestamp('pending_timezone_scheduled_at')->nullable()->after('pending_timezone_scheduled_by');
                }
            });

            DB::table('organization_settings')
                ->whereNull('country')
                ->update(['country' => 'United States']);
        }

        IdempotentMigration::createIfMissing('organization_setting_change_events', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 64);
            $table->string('summary');
            $table->text('previous_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['setting_key', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });

        if (IdempotentMigration::hasTable('scheduled_visits')
            && ! IdempotentMigration::hasColumn('scheduled_visits', 'schedule_timezone')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->string('schedule_timezone')->nullable()->after('ends_at');
            });

            $agencyTimezone = DB::table('organization_settings')->value('timezone') ?: 'America/New_York';

            DB::table('scheduled_visits')
                ->whereNull('schedule_timezone')
                ->update(['schedule_timezone' => $agencyTimezone]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_setting_change_events');

        if (IdempotentMigration::hasColumn('scheduled_visits', 'schedule_timezone')) {
            Schema::table('scheduled_visits', function (Blueprint $table): void {
                $table->dropColumn('schedule_timezone');
            });
        }

        if (! IdempotentMigration::hasTable('organization_settings')) {
            return;
        }

        Schema::table('organization_settings', function (Blueprint $table): void {
            $columns = [
                'display_name',
                'address_line_1',
                'address_line_2',
                'country',
                'main_phone',
                'main_email',
                'website',
                'pending_timezone',
                'pending_timezone_effective_at',
                'pending_timezone_reason',
                'pending_timezone_scheduled_by',
                'pending_timezone_scheduled_at',
            ];

            foreach ($columns as $column) {
                if (IdempotentMigration::hasColumn('organization_settings', $column)) {
                    if ($column === 'pending_timezone_scheduled_by') {
                        $table->dropConstrainedForeignId('pending_timezone_scheduled_by');
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
