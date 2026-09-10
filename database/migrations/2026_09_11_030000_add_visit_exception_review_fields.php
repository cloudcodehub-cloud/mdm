<?php

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
        Schema::table('visit_exceptions', function (Blueprint $table) {
            $table->foreignId('reviewed_by_user_id')->nullable()->after('context')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->foreignId('resolved_by_user_id')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by_user_id');
            $table->text('resolution_notes')->nullable()->after('resolved_at');
            $table->json('status_history')->nullable()->after('resolution_notes');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_exceptions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn([
                'reviewed_at',
                'review_notes',
                'resolved_at',
                'resolution_notes',
                'status_history',
            ]);
        });
    }
};
