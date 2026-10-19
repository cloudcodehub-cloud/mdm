<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 03 Archive Pending: Admin archive is accepted while live operational work blocks safe archival.
 * The record stays in Archive Pending (current work intact) and finalizes automatically.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['employees', 'clients'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! IdempotentMigration::hasColumn($tableName, 'archive_pending_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->timestamp('archive_pending_at')->nullable();
                });
            }

            if (! IdempotentMigration::hasColumn($tableName, 'archive_pending_by_user_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreignId('archive_pending_by_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                });
            }

            if (! IdempotentMigration::hasColumn($tableName, 'archive_pending_reason')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->text('archive_pending_reason')->nullable();
                });
            }

            if (! IdempotentMigration::hasColumn($tableName, 'archive_pending_blockers')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->json('archive_pending_blockers')->nullable();
                });
            }

            if (! IdempotentMigration::hasColumn($tableName, 'archive_pending_from_status')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('archive_pending_from_status')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (IdempotentMigration::hasColumn($tableName, 'archive_pending_by_user_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('archive_pending_by_user_id');
                });
            }

            $columns = array_values(array_filter([
                IdempotentMigration::hasColumn($tableName, 'archive_pending_at') ? 'archive_pending_at' : null,
                IdempotentMigration::hasColumn($tableName, 'archive_pending_reason') ? 'archive_pending_reason' : null,
                IdempotentMigration::hasColumn($tableName, 'archive_pending_blockers') ? 'archive_pending_blockers' : null,
                IdempotentMigration::hasColumn($tableName, 'archive_pending_from_status') ? 'archive_pending_from_status' : null,
            ]));

            if ($columns !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
