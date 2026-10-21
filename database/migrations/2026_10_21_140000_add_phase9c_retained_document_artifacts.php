<?php

use App\Support\IdempotentMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MDM 10-02 / 10-15 — retained official PDF artifact snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        IdempotentMigration::createIfMissing('retained_document_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->string('document_key', 64);
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('milestone', 64);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 128)->default('application/pdf');
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->string('checksum_sha256', 64);
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('source_status')->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('superseded_at')->nullable();
            $table->unsignedBigInteger('superseded_by_artifact_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'document_key', 'version'],
                'retained_docs_source_key_version_uq',
            );
            $table->index(['document_key', 'is_current', 'generated_at']);
            $table->index(['source_type', 'source_id', 'document_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retained_document_artifacts');
    }
};
