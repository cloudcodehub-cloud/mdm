<?php

namespace Tests\Feature\Domain;

use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskRecurrence;
use App\Http\Requests\CarePlanRequest;
use App\Http\Requests\CarePlanTaskTemplateRequest;
use App\Http\Requests\ScheduledVisitRequest;
use App\Http\Requests\SkipReasonRequest;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ShiftTemplate;
use App\Models\SkipReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DomainAuthorizationPhase1B2Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_care_plans_tasks_visits_and_skip_reasons(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = CarePlan::factory()->create();
        $task = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create();
        $visit = ScheduledVisit::factory()->create();
        $reason = SkipReason::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('create', CarePlan::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $plan));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $plan));
        $this->assertTrue(Gate::forUser($admin)->allows('create', CarePlanTaskTemplate::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $task));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $task));
        $this->assertTrue(Gate::forUser($admin)->allows('create', ScheduledVisit::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $visit));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $visit));
        $this->assertTrue(Gate::forUser($admin)->allows('create', SkipReason::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $reason));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $reason));
    }

    public function test_supervisors_can_view_assigned_care_plans_and_visits(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $otherClient = Client::factory()->forSupervisor($otherSupervisor)->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();

        $plan = CarePlan::factory()->forClient($client)->create();
        $otherPlan = CarePlan::factory()->forClient($otherClient)->create();
        $task = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create();
        $visit = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'supervisor_id' => $supervisor->id,
        ]);
        $otherVisit = ScheduledVisit::factory()->forClient($otherClient)->create();
        $reason = SkipReason::factory()->create();

        $user = $supervisor->user()->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('view', $plan));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherPlan));
        $this->assertTrue(Gate::forUser($user)->allows('view', $task));
        $this->assertTrue(Gate::forUser($user)->allows('view', $visit));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherVisit));
        $this->assertTrue(Gate::forUser($user)->allows('view', $reason));
        $this->assertTrue(Gate::forUser($user)->denies('create', CarePlan::class));
        $this->assertTrue(Gate::forUser($user)->denies('create', ScheduledVisit::class));
        $this->assertTrue(Gate::forUser($user)->denies('create', SkipReason::class));
    }

    public function test_dsps_can_view_assigned_client_care_plans_and_own_visits(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $otherDsp = Employee::factory()->dsp()->create();
        $assignedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        ClientDspAssignment::factory()->forDsp($dsp)->forClient($assignedClient)->create();

        $plan = CarePlan::factory()->forClient($assignedClient)->create();
        $otherPlan = CarePlan::factory()->forClient($otherClient)->create();
        $ownVisit = ScheduledVisit::factory()->forClient($assignedClient)->forDsp($dsp)->create();
        $otherVisit = ScheduledVisit::factory()->forClient($otherClient)->forDsp($otherDsp)->create();
        $reason = SkipReason::factory()->create();

        $user = $dsp->user()->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('view', $plan));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherPlan));
        $this->assertTrue(Gate::forUser($user)->allows('view', $ownVisit));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherVisit));
        $this->assertTrue(Gate::forUser($user)->allows('view', $reason));
        $this->assertTrue(Gate::forUser($user)->denies('update', $plan));
        $this->assertTrue(Gate::forUser($user)->denies('create', ScheduledVisit::class));
    }

    public function test_form_request_rules_accept_template_and_explicit_visit_payloads(): void
    {
        $client = Client::factory()->create();
        $dsp = Employee::factory()->dsp()->create();
        $plan = CarePlan::factory()->forClient($client)->create();
        $shift = ShiftTemplate::factory()->overnight()->create();

        $carePlanValidator = Validator::make([
            'client_id' => $client->id,
            'title' => 'Current ISP',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => 'active',
        ], (new CarePlanRequest)->rules());

        $taskValidator = Validator::make([
            'care_plan_id' => $plan->id,
            'title' => 'Weekday breakfast setup',
            'recurrence' => TaskRecurrence::Custom->value,
            'recurrence_detail' => 'Every weekday morning',
            'is_required' => true,
            'sort_order' => 1,
        ], (new CarePlanTaskTemplateRequest)->rules());

        $templateVisitValidator = Validator::make([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'shift_template_id' => $shift->id,
            'service_date' => '2026-09-10',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled->value,
        ], (new ScheduledVisitRequest)->rules());

        $explicitVisitValidator = Validator::make([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => '2026-09-13',
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Scheduled->value,
        ], (new ScheduledVisitRequest)->rules());

        $skipValidator = Validator::make([
            'name' => 'Other',
            'code' => 'other',
            'requires_comment' => true,
        ], (new SkipReasonRequest)->rules());

        $this->assertTrue($carePlanValidator->passes());
        $this->assertTrue($taskValidator->passes());
        $this->assertTrue($templateVisitValidator->passes());
        $this->assertTrue($explicitVisitValidator->passes());
        $this->assertTrue($skipValidator->passes());
    }

    public function test_custom_recurrence_requires_detail_and_visits_require_template_or_times(): void
    {
        $client = Client::factory()->create();
        $dsp = Employee::factory()->dsp()->create();
        $plan = CarePlan::factory()->forClient($client)->create();

        $taskValidator = Validator::make([
            'care_plan_id' => $plan->id,
            'title' => 'Custom task',
            'recurrence' => TaskRecurrence::Custom->value,
        ], (new CarePlanTaskTemplateRequest)->rules());

        $visitValidator = Validator::make([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => '2026-09-10',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled->value,
        ], (new ScheduledVisitRequest)->rules());

        $this->assertTrue($taskValidator->fails());
        $this->assertTrue($visitValidator->fails());
    }
}
