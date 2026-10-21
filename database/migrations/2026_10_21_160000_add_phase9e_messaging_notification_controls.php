<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 09-14 / 09-16 / 09-25 / 09-26 — personal archive, mute, and notification hide.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversation_participants')) {
            Schema::table('conversation_participants', function (Blueprint $table): void {
                if (! IdempotentMigration::hasColumn('conversation_participants', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable()->after('left_at');
                }
                if (! IdempotentMigration::hasColumn('conversation_participants', 'muted_at')) {
                    $table->timestamp('muted_at')->nullable()->after('archived_at');
                }
            });
        }

        if (Schema::hasTable('in_app_notifications')) {
            Schema::table('in_app_notifications', function (Blueprint $table): void {
                if (! IdempotentMigration::hasColumn('in_app_notifications', 'hidden_at')) {
                    $table->timestamp('hidden_at')->nullable()->after('read_at');
                    $table->index(['user_id', 'hidden_at']);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversation_participants')) {
            Schema::table('conversation_participants', function (Blueprint $table): void {
                if (IdempotentMigration::hasColumn('conversation_participants', 'muted_at')) {
                    $table->dropColumn('muted_at');
                }
                if (IdempotentMigration::hasColumn('conversation_participants', 'archived_at')) {
                    $table->dropColumn('archived_at');
                }
            });
        }

        if (Schema::hasTable('in_app_notifications')) {
            Schema::table('in_app_notifications', function (Blueprint $table): void {
                if (IdempotentMigration::hasColumn('in_app_notifications', 'hidden_at')) {
                    $table->dropColumn('hidden_at');
                }
            });
        }
    }
};
