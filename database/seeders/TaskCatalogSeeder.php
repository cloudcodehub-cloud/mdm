<?php

namespace Database\Seeders;

use App\Enums\TaskCatalogCategory;
use App\Enums\TaskPreferredTiming;
use App\Enums\TaskRecurrence;
use App\Models\TaskCatalogBundle;
use App\Models\TaskCatalogItem;
use Illuminate\Database\Seeder;

class TaskCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $items = [];
        $order = 0;

        foreach ($this->definitions() as $definition) {
            $order++;
            $items[$definition['slug']] = TaskCatalogItem::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'category' => $definition['category'],
                    'title' => $definition['title'],
                    'instructions' => $definition['instructions'] ?? null,
                    'default_recurrence' => $definition['recurrence'],
                    'default_recurrence_detail' => $definition['detail'] ?? null,
                    'default_weekdays' => $definition['weekdays'] ?? null,
                    'default_interval_weeks' => $definition['interval_weeks'] ?? $definition['recurrence']->defaultIntervalWeeks(),
                    'default_preferred_timing' => $definition['timing'] ?? null,
                    'default_is_required' => $definition['required'] ?? true,
                    'default_note_required' => $definition['note_required'] ?? false,
                    'default_can_skip' => $definition['can_skip'] ?? true,
                    'default_is_critical' => $definition['critical'] ?? false,
                    'sort_order' => $order,
                    'is_active' => true,
                ],
            );
        }

        $bundleOrder = 0;

        foreach ($this->bundles() as $bundle) {
            $bundleOrder++;
            $record = TaskCatalogBundle::query()->updateOrCreate(
                ['slug' => $bundle['slug']],
                [
                    'name' => $bundle['name'],
                    'description' => $bundle['description'],
                    'sort_order' => $bundleOrder,
                ],
            );

            $sync = [];
            $itemOrder = 0;

            foreach ($bundle['items'] as $slug) {
                if (! isset($items[$slug])) {
                    continue;
                }

                $itemOrder++;
                $sync[$items[$slug]->id] = ['sort_order' => $itemOrder];
            }

            $record->items()->sync($sync);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            $this->item('bathing-assistance', TaskCatalogCategory::PersonalCare, 'Bathing Assistance', TaskRecurrence::Daily, TaskPreferredTiming::Morning),
            $this->item('grooming', TaskCatalogCategory::PersonalCare, 'Grooming', TaskRecurrence::Daily, TaskPreferredTiming::Morning),
            $this->item('dressing', TaskCatalogCategory::PersonalCare, 'Dressing', TaskRecurrence::Daily, TaskPreferredTiming::Morning),
            $this->item('oral-care', TaskCatalogCategory::PersonalCare, 'Oral Care', TaskRecurrence::Daily, TaskPreferredTiming::Morning),
            $this->item('toileting', TaskCatalogCategory::PersonalCare, 'Toileting', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit),
            $this->item('personal-hygiene', TaskCatalogCategory::PersonalCare, 'Personal Hygiene', TaskRecurrence::Daily, TaskPreferredTiming::Morning),
            $this->item('transfer-assistance', TaskCatalogCategory::Mobility, 'Transfer Assistance', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit, required: true, critical: true),
            $this->item('ambulation-support', TaskCatalogCategory::Mobility, 'Ambulation Support', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit),
            $this->item('repositioning', TaskCatalogCategory::Mobility, 'Repositioning', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit),
            $this->item('meal-preparation', TaskCatalogCategory::Meals, 'Meal Preparation', TaskRecurrence::Daily, TaskPreferredTiming::Morning, noteRequired: true),
            $this->item('feeding-assistance', TaskCatalogCategory::Meals, 'Feeding Assistance', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit),
            $this->item('hydration', TaskCatalogCategory::Meals, 'Hydration', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit),
            $this->item(
                'medication-reminder',
                TaskCatalogCategory::Medication,
                'Medication Reminder',
                TaskRecurrence::Daily,
                TaskPreferredTiming::Morning,
                instructions: 'Template only. Use only when the client care plan authorizes a reminder.',
            ),
            $this->item(
                'medication-assistance',
                TaskCatalogCategory::Medication,
                'Medication Assistance/Observation',
                TaskRecurrence::Daily,
                TaskPreferredTiming::DuringVisit,
                instructions: 'Template only. Not universally permitted. Configure per agency and client.',
                required: false,
            ),
            $this->item('light-housekeeping', TaskCatalogCategory::Household, 'Light Housekeeping', TaskRecurrence::Weekly, TaskPreferredTiming::Afternoon),
            $this->item('laundry', TaskCatalogCategory::Household, 'Laundry', TaskRecurrence::Weekly, TaskPreferredTiming::Afternoon),
            $this->item('dishes', TaskCatalogCategory::Household, 'Dishes', TaskRecurrence::Daily, TaskPreferredTiming::Evening),
            $this->item('bedding-change', TaskCatalogCategory::Household, 'Bedding Change', TaskRecurrence::Weekly, TaskPreferredTiming::Morning),
            $this->item('grocery-shopping', TaskCatalogCategory::Community, 'Grocery Shopping', TaskRecurrence::Biweekly, TaskPreferredTiming::Afternoon),
            $this->item('appointment-support', TaskCatalogCategory::Community, 'Appointment Support', TaskRecurrence::Weekly, TaskPreferredTiming::Morning),
            $this->item('community-outing', TaskCatalogCategory::Community, 'Community Outing', TaskRecurrence::Weekly, TaskPreferredTiming::Afternoon),
            $this->item('transportation-assistance', TaskCatalogCategory::Community, 'Transportation Assistance', TaskRecurrence::Weekly, TaskPreferredTiming::DuringVisit),
            $this->item('family-community-visit', TaskCatalogCategory::Community, 'Family/Community Visit', TaskRecurrence::Monthly, TaskPreferredTiming::Afternoon),
            $this->item('wellness-observation', TaskCatalogCategory::HealthSafety, 'Wellness Observation', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit, noteRequired: true),
            $this->item('safety-check', TaskCatalogCategory::HealthSafety, 'Safety Check', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit, critical: true),
            $this->item('independent-living-skills', TaskCatalogCategory::Behavioral, 'Independent Living Skills', TaskRecurrence::Weekly, TaskPreferredTiming::DuringVisit),
            $this->item('progress-note', TaskCatalogCategory::Documentation, 'Progress Note', TaskRecurrence::Daily, TaskPreferredTiming::DuringVisit, noteRequired: true),
            $this->item('handover-note', TaskCatalogCategory::Documentation, 'Handover Note', TaskRecurrence::Daily, TaskPreferredTiming::Evening, noteRequired: true),
            $this->item('supervisor-notification', TaskCatalogCategory::Documentation, 'Supervisor Notification', TaskRecurrence::Custom, TaskPreferredTiming::DuringVisit, required: false, detail: 'As needed during the visit'),
        ];
    }

    /**
     * @return list<array{slug: string, name: string, description: string, items: list<string>}>
     */
    private function bundles(): array
    {
        return [
            [
                'slug' => 'morning-adl-routine',
                'name' => 'Morning ADL Routine',
                'description' => 'Core personal care tasks for a morning visit.',
                'items' => ['bathing-assistance', 'grooming', 'dressing', 'oral-care', 'toileting', 'personal-hygiene'],
            ],
            [
                'slug' => 'meal-support',
                'name' => 'Meal Support',
                'description' => 'Meal preparation, feeding, and hydration.',
                'items' => ['meal-preparation', 'feeding-assistance', 'hydration'],
            ],
            [
                'slug' => 'evening-routine',
                'name' => 'Evening Routine',
                'description' => 'Close-of-day personal care and documentation.',
                'items' => ['dressing', 'oral-care', 'repositioning', 'bedding-change', 'progress-note', 'handover-note'],
            ],
            [
                'slug' => 'household-support',
                'name' => 'Household Support',
                'description' => 'Light IADL household tasks.',
                'items' => ['light-housekeeping', 'laundry', 'dishes', 'bedding-change'],
            ],
            [
                'slug' => 'community-outing-bundle',
                'name' => 'Community Outing',
                'description' => 'Community access and transportation support.',
                'items' => ['community-outing', 'transportation-assistance', 'appointment-support'],
            ],
            [
                'slug' => 'medication-support',
                'name' => 'Medication Support',
                'description' => 'Reminders and observation templates. Configure per client.',
                'items' => ['medication-reminder', 'medication-assistance', 'wellness-observation'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $slug,
        TaskCatalogCategory $category,
        string $title,
        TaskRecurrence $recurrence,
        TaskPreferredTiming $timing,
        bool $required = true,
        bool $noteRequired = false,
        bool $canSkip = true,
        bool $critical = false,
        ?string $instructions = null,
        ?string $detail = null,
    ): array {
        return [
            'slug' => $slug,
            'category' => $category,
            'title' => $title,
            'recurrence' => $recurrence,
            'timing' => $timing,
            'required' => $required,
            'note_required' => $noteRequired,
            'can_skip' => $canSkip,
            'critical' => $critical,
            'instructions' => $instructions,
            'detail' => $detail,
        ];
    }
}
