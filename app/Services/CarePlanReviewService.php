<?php

namespace App\Services;

use App\Enums\CarePlanStatus;
use App\Enums\InAppNotificationType;
use App\Enums\Role;
use App\Models\CarePlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CarePlanReviewService
{
    public function __construct(
        private SettingsService $settings,
        private InAppNotificationService $notifications,
    ) {}

    /**
     * @return array{status: string, status_label: string, review_on: string|null, days_until_review: int|null, is_overdue: bool, is_approaching: bool, warning: string|null}
     */
    public function reviewState(CarePlan $plan, ?CarbonInterface $on = null): array
    {
        $today = $on?->toDateString() ?? $this->settings->today();
        $reviewOn = $plan->review_on?->toDateString();

        if ($reviewOn === null) {
            return [
                'status' => 'none',
                'status_label' => 'No review date',
                'review_on' => null,
                'days_until_review' => null,
                'is_overdue' => false,
                'is_approaching' => false,
                'warning' => null,
            ];
        }

        $days = (int) Carbon::parse($today)
            ->startOfDay()
            ->diffInDays(Carbon::parse($reviewOn)->startOfDay(), false);
        $window = $this->settings->credentialExpiringSoonDays();
        $overdue = $days < 0;
        $approaching = ! $overdue && $days <= $window;

        $warning = null;

        if ($overdue) {
            $warning = 'Care Plan review is overdue. This is a warning/review condition, not an automatic service hard stop.';
        } elseif ($approaching) {
            $warning = 'Care Plan review date is approaching.';
        }

        return [
            'status' => $overdue ? 'overdue' : ($approaching ? 'approaching' : 'ok'),
            'status_label' => $overdue ? 'Review overdue' : ($approaching ? 'Review approaching' : 'Review scheduled'),
            'review_on' => $reviewOn,
            'days_until_review' => $days,
            'is_overdue' => $overdue,
            'is_approaching' => $approaching,
            'warning' => $warning,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function warningsForClientPlans(Collection $plans): array
    {
        return $plans
            ->filter(fn (CarePlan $plan): bool => in_array($plan->status, [CarePlanStatus::Active, CarePlanStatus::Pending], true))
            ->map(function (CarePlan $plan): ?array {
                $state = $this->reviewState($plan);

                if ($state['warning'] === null) {
                    return null;
                }

                return [
                    'care_plan_id' => $plan->id,
                    'title' => $plan->title,
                    'revision_number' => $plan->revision_number,
                    ...$state,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Notify responsible users once per plan/review date/condition.
     *
     * @return int notifications created/updated
     */
    public function dispatchDue(?CarbonInterface $now = null): int
    {
        $now = $now ?? $this->settings->now();
        $count = 0;

        CarePlan::query()
            ->whereIn('status', [CarePlanStatus::Active->value, CarePlanStatus::Pending->value])
            ->whereNotNull('review_on')
            ->with(['client.supervisor.user'])
            ->orderBy('id')
            ->each(function (CarePlan $plan) use ($now, &$count): void {
                $state = $this->reviewState($plan, $now);

                if (! $state['is_overdue'] && ! $state['is_approaching']) {
                    return;
                }

                $recipients = $this->recipientsFor($plan);

                foreach ($recipients as $user) {
                    $sourceKey = sprintf(
                        'care-plan.%d.review.%s.%s',
                        $plan->id,
                        $state['review_on'],
                        $state['status'],
                    );

                    $this->notifications->notify(
                        $user,
                        InAppNotificationType::Profile,
                        $state['is_overdue'] ? 'Care Plan review overdue' : 'Care Plan review approaching',
                        sprintf(
                            '%s · %s (rev %d) review %s.',
                            $plan->client->full_name,
                            $plan->title,
                            $plan->revision_number,
                            $state['review_on'],
                        ),
                        $sourceKey,
                        route('clients.show', $plan->client),
                    );
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return list<User>
     */
    private function recipientsFor(CarePlan $plan): array
    {
        $users = [];
        $supervisorUser = $plan->client->supervisor?->user;

        if ($supervisorUser !== null) {
            $users[$supervisorUser->id] = $supervisorUser;
        }

        User::query()
            ->where('role', Role::Admin)
            ->orderBy('id')
            ->get()
            ->each(function (User $admin) use (&$users): void {
                $users[$admin->id] = $admin;
            });

        return array_values($users);
    }
}
