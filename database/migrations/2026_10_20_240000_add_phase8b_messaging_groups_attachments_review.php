<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 09 — private groups, attachments, exceptional Admin review audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type')->default('direct')->after('organization_id');
            $table->string('name')->nullable()->after('type');
            $table->foreignId('created_by_user_id')->nullable()->after('name')->constrained('users')->nullOnDelete();
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->timestamp('joined_at')->nullable()->after('last_read_at');
            $table->timestamp('left_at')->nullable()->after('joined_at');
        });

        DB::table('conversation_participants')
            ->whereNull('joined_at')
            ->update(['joined_at' => DB::raw('created_at')]);

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('message_type')->default('user')->after('sender_id');
        });

        Schema::create('conversation_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained('conversation_messages')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });

        Schema::create('conversation_admin_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose');
            $table->text('reason');
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['conversation_id', 'admin_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_admin_reviews');
        Schema::dropIfExists('conversation_message_attachments');

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn('message_type');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn(['joined_at', 'left_at']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['type', 'name']);
        });
    }
};
