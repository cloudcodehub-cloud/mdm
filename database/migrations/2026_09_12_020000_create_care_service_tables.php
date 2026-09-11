<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_services', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('note_required')->default(false);
            $table->boolean('supervisor_review_expected')->default(false);
            $table->string('payer_code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('care_service_task_catalog_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_catalog_bundle_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['care_service_id', 'task_catalog_bundle_id'], 'care_service_bundle_unique');
        });

        Schema::create('care_service_task_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_catalog_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['care_service_id', 'task_catalog_item_id'], 'care_service_item_unique');
        });

        Schema::create('client_care_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_service_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'care_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_care_services');
        Schema::dropIfExists('care_service_task_catalog_items');
        Schema::dropIfExists('care_service_task_catalog_bundles');
        Schema::dropIfExists('care_services');
    }
};
