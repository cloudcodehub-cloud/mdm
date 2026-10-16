<?php

use App\Enums\ComplianceVerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_credentials', function (Blueprint $table) {
            $table->string('verification_status')
                ->default(ComplianceVerificationStatus::Verified->value)
                ->after('status');
            $table->foreignId('verified_by_user_id')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
            $table->foreignId('rejected_by_user_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            $table->text('verification_note')->nullable()->after('rejected_at');
            $table->index(['employee_id', 'verification_status']);
        });

        Schema::table('employee_trainings', function (Blueprint $table) {
            $table->string('verification_status')
                ->default(ComplianceVerificationStatus::Verified->value)
                ->after('status');
            $table->foreignId('verified_by_user_id')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
            $table->foreignId('rejected_by_user_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            $table->text('verification_note')->nullable()->after('rejected_at');
            $table->index(['employee_id', 'verification_status']);
        });

        Schema::create('compliance_requirement_audits', function (Blueprint $table) {
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

        Schema::table('employee_trainings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn([
                'verification_status',
                'verified_at',
                'rejected_at',
                'verification_note',
            ]);
        });

        Schema::table('employee_credentials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn([
                'verification_status',
                'verified_at',
                'rejected_at',
                'verification_note',
            ]);
        });
    }
};
