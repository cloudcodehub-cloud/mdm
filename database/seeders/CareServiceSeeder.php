<?php

namespace Database\Seeders;

use App\Models\CareService;
use App\Models\TaskCatalogBundle;
use App\Models\TaskCatalogItem;
use Illuminate\Database\Seeder;

class CareServiceSeeder extends Seeder
{
    public function run(): void
    {
        $bundles = TaskCatalogBundle::query()->pluck('id', 'slug');
        $items = TaskCatalogItem::query()->pluck('id', 'slug');
        $order = 0;

        foreach ($this->definitions() as $definition) {
            $order++;
            $service = CareService::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                    'note_required' => $definition['note_required'] ?? false,
                    'supervisor_review_expected' => $definition['supervisor_review'] ?? false,
                    'payer_code' => null,
                    'sort_order' => $order,
                ],
            );

            $bundleSync = [];
            $bundleOrder = 0;

            foreach ($definition['bundles'] as $slug) {
                if (! isset($bundles[$slug])) {
                    continue;
                }

                $bundleOrder++;
                $bundleSync[$bundles[$slug]] = ['sort_order' => $bundleOrder];
            }

            $itemSync = [];
            $itemOrder = 0;

            foreach ($definition['items'] as $slug) {
                if (! isset($items[$slug])) {
                    continue;
                }

                $itemOrder++;
                $itemSync[$items[$slug]] = ['sort_order' => $itemOrder];
            }

            $service->recommendedBundles()->sync($bundleSync);
            $service->recommendedItems()->sync($itemSync);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            $this->service('personal-care', 'Personal Care', 'Hands-on ADL support in the home or community.', ['morning-adl-routine', 'meal-support']),
            $this->service('homemaker', 'Homemaker / Household Support', 'IADL household support that is not personal care.', ['household-support']),
            $this->service('residential-habilitation', 'Residential Habilitation / Supported Living', 'Ongoing support to live safely in a residential setting.', ['morning-adl-routine', 'household-support', 'meal-support'], supervisorReview: true),
            $this->service('community-integration', 'Community Integration / Participation', 'Community access, participation, and natural supports.', ['community-outing-bundle']),
            $this->service('respite-care', 'Respite Care', 'Short-term relief coverage using the client’s existing care plan.', ['morning-adl-routine']),
            $this->service('transportation', 'Transportation / Community Access', 'Travel support to community, medical, or family destinations.', ['community-outing-bundle']),
            $this->service('companion', 'Companion / Non-Medical Support', 'Presence, companionship, and non-medical assistance.', ['meal-support']),
            $this->service('independent-living', 'Independent Living / Skill Development', 'Practice and coaching for independent living skills.', [], ['independent-living-skills']),
            $this->service('behavioral-support', 'Behavioral / Support Plan Services', 'Support-plan follow-through and observation. Not a clinical service.', [], ['wellness-observation', 'progress-note'], true, true),
            $this->service('health-wellness', 'Health / Wellness Support', 'Wellness observation and authorized health-related reminders.', ['medication-support'], [], true),
            $this->service('appointment-escort', 'Appointment / Medical Escort', 'Escort and support for appointments.', [], ['appointment-support', 'transportation-assistance']),
            $this->service('overnight-supervision', 'Overnight / Protective Supervision', 'Overnight presence and protective supervision.', ['evening-routine'], [], false, true),
        ];
    }

    /**
     * @param  list<string>  $bundles
     * @param  list<string>  $items
     * @return array<string, mixed>
     */
    private function service(
        string $slug,
        string $name,
        string $description,
        array $bundles,
        array $items = [],
        bool $noteRequired = false,
        bool $supervisorReview = false,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'bundles' => $bundles,
            'items' => $items,
            'note_required' => $noteRequired,
            'supervisor_review' => $supervisorReview,
        ];
    }
}
