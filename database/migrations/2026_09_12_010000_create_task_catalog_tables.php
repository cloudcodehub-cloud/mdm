<?php

use App\Enums\TaskCatalogCategory;
use App\Enums\TaskRecurrence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('category')->default(TaskCatalogCategory::PersonalCare->value);
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('default_recurrence')->default(TaskRecurrence::Daily->value);
            $table->string('default_recurrence_detail')->nullable();
            $table->json('default_weekdays')->nullable();
            $table->unsignedTinyInteger('default_interval_weeks')->nullable();
            $table->string('default_preferred_timing')->nullable();
            $table->boolean('default_is_required')->default(true);
            $table->boolean('default_note_required')->default(false);
            $table->boolean('default_can_skip')->default(true);
            $table->boolean('default_is_critical')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'sort_order']);
        });

        Schema::create('task_catalog_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('task_catalog_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_catalog_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_catalog_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['task_catalog_bundle_id', 'task_catalog_item_id'], 'catalog_bundle_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_catalog_bundle_items');
        Schema::dropIfExists('task_catalog_bundles');
        Schema::dropIfExists('task_catalog_items');
    }
};
