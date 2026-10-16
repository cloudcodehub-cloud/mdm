<?php

use App\Enums\ComplianceVerificationStatus;
use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_credentials') && ! Schema::hasColumn('employee_credentials', 'verification_status')) {
            Schema::table('employee_credentials', function (Blueprint $table): void {
                $table->string('verification_status')
                    ->default(ComplianceVerificationStatus::Verified->value)
                    ->after('status');
            });
        }
        if (Schema::hasTable('employee_credentials') && ! Schema::hasColumn('employee_credentials', 'verified_by_user_id')) {
            Schema::table('employee_credentials', function (Blueprint $table): void {
                $table->foreignId('verified_by_user_id')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            });
        }
        if (Schema::hasTable('employee_credentials') && ! Schema::hasColumn('employee_credentials', 'verified_at')) {
            Schema::table('employee_credentials', function (Blueprint $table): void {
                $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
            });
        }
        if (Schema::hasTable('employee_credentials') && ! Schema::hasColumn('employee_credentials', 'rejected_by_user_id')) {
            Schema::table('employee_credentials', function (Blueprint $table): void {
                $table->foreignId('rejected_by_user_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            });
        }
        if (Schema::hasTable('employee_credentials') && ! Schema::hasColumn('employee_credentials', 'rejected_at')) {
            Schema::table('employee_credentials', function (Blueprint $table): void {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            });
        }
        if (Schema::hasTable('employee_credentials') && ! Schema::hasColumn('employee_credentials', 'verification_note')) {
            Schema::table('employee_credentials', function (Blueprint $table): void {
                $table->text('verification_note')->nullable()->after('rejected_at');
            });
        }

        if (Schema::hasTable('employee_trainings') && ! Schema::hasColumn('employee_trainings', 'verification_status')) {
            Schema::table('employee_trainings', function (Blueprint $table): void {
                $table->string('verification_status')
                    ->default(ComplianceVerificationStatus::Verified->value)
                    ->after('status');
            });
        }
        if (Schema::hasTable('employee_trainings') && ! Schema::hasColumn('employee_trainings', 'verified_by_user_id')) {
            Schema::table('employee_trainings', function (Blueprint $table): void {
                $table->foreignId('verified_by_user_id')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            });
        }
        if (Schema::hasTable('employee_trainings') && ! Schema::hasColumn('employee_trainings', 'verified_at')) {
            Schema::table('employee_trainings', function (Blueprint $table): void {
                $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
            });
        }
        if (Schema::hasTable('employee_trainings') && ! Schema::hasColumn('employee_trainings', 'rejected_by_user_id')) {
            Schema::table('employee_trainings', function (Blueprint $table): void {
                $table->foreignId('rejected_by_user_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            });
        }
        if (Schema::hasTable('employee_trainings') && ! Schema::hasColumn('employee_trainings', 'rejected_at')) {
            Schema::table('employee_trainings', function (Blueprint $table): void {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            });
        }
        if (Schema::hasTable('employee_trainings') && ! Schema::hasColumn('employee_trainings', 'verification_note')) {
            Schema::table('employee_trainings', function (Blueprint $table): void {
                $table->text('verification_note')->nullable()->after('rejected_at');
            });
        }

        IdempotentMigration::createIfMissing('compliance_requirement_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('compliance_requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['compliance_requirement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_requirement_audits');

        foreach (['employee_trainings', 'employee_credentials'] as $tableName) {
            if (Schema::hasColumn($tableName, 'verified_by_user_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('verified_by_user_id');
                });
            }
            if (Schema::hasColumn($tableName, 'rejected_by_user_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('rejected_by_user_id');
                });
            }
            $columns = array_values(array_filter([
                Schema::hasColumn($tableName, 'verification_status') ? 'verification_status' : null,
                Schema::hasColumn($tableName, 'verified_at') ? 'verified_at' : null,
                Schema::hasColumn($tableName, 'rejected_at') ? 'rejected_at' : null,
                Schema::hasColumn($tableName, 'verification_note') ? 'verification_note' : null,
            ]));
            if ($columns !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
