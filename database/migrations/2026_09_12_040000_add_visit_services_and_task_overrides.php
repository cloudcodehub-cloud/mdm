<?php

use App\Models\CareService;
use App\Models\ScheduledVisit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_visit_care_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_service_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['scheduled_visit_id', 'care_service_id'], 'scheduled_visit_service_unique');
        });

        Schema::create('scheduled_visit_task_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_plan_task_template_id');
            $table->foreign('care_plan_task_template_id', 'svto_care_plan_task_fk')
                ->references('id')
                ->on('care_plan_task_templates')
                ->restrictOnDelete();
            $table->boolean('included');
            $table->text('exclusion_reason')->nullable();
            $table->timestamps();

            $table->unique(['scheduled_visit_id', 'care_plan_task_template_id'], 'scheduled_visit_task_override_unique');
        });

        Schema::table('scheduled_visit_one_off_tasks', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')
                ->nullable()
                ->after('scheduled_visit_id')
                ->constrained('task_catalog_items')
                ->nullOnDelete();
        });

        $services = CareService::query()->get()->keyBy(fn (CareService $service): string => mb_strtolower($service->name));

        ScheduledVisit::query()->orderBy('id')->each(function (ScheduledVisit $visit) use ($services): void {
            $names = preg_split('/\s*[·,;|]\s*/u', $visit->service_type) ?: [];
            $sort = 0;

            foreach ($names as $name) {
                $name = trim($name);

                if ($name === '') {
                    continue;
                }

                $service = $services->get(mb_strtolower($name));

                if ($service === null) {
                    continue;
                }

                $visit->careServices()->syncWithoutDetaching([
                    $service->id => ['sort_order' => ++$sort],
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_visit_one_off_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_item_id');
        });

        Schema::dropIfExists('scheduled_visit_task_overrides');
        Schema::dropIfExists('scheduled_visit_care_services');
    }
};
