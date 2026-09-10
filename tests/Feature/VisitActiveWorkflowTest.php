<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskRecurrence;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\SkipReason;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitActiveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function clockInUnavailable(): array
    {
        return [
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Denied->value,
            'unavailable_reason' => 'Browser GPS permission denied for local demo.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clockOutGps(): array
    {
        return [
            'latitude' => 40.1111111,
            'longitude' => -82.2222222,
            'accuracy' => 8.25,
            'location_method' => ClockInLocationMethod::BrowserGps->value,
            'location_status' => ClockInLocationStatus::Captured->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clockOutUnavailable(): array
    {
        return [
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Unavailable->value,
            'unavailable_reason' => 'GPS unavailable at clock-out.',
        ];
    }

    /**
     * @return array{dsp: Employee, visit: Visit, task: VisitTask, template: CarePlanTaskTemplate}
     */
    private function startedVisit(): array
    {
        Carbon::setTestNow('2026-09-11 09:00:00');

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-09-01',
        ]);
        $template = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Assist with morning ADLs',
            'instructions' => 'Support hygiene as accepted.',
            'recurrence' => TaskRecurrence::Daily,
            'is_required' => true,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->clockInUnavailable())
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
        $task = VisitTask::query()->where('visit_id', $visit->id)->firstOrFail();

        return compact('dsp', 'visit', 'task', 'template');
    }

    private function seedSkipReasons(): void
    {
        SkipReason::query()->create([
            'name' => 'Client refused',
            'code' => SkipReason::CLIENT_REFUSED,
            'requires_comment' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        SkipReason::query()->create([
            'name' => 'Not applicable',
            'code' => 'not_applicable',
            'requires_comment' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        SkipReason::factory()->other()->create();
    }

    public function test_dsp_can_complete_task_without_changing_template(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'task' => $task, 'template' => $template] = $this->startedVisit();
        $originalTitle = $template->title;
        $originalInstructions = $template->instructions;

        Carbon::setTestNow('2026-09-11 09:20:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.complete', [$visit, $task]), [
                'completion_note' => 'Completed with client agreement.',
            ])
            ->assertRedirect();

        $task->refresh();
        $template->refresh();

        $this->assertSame(VisitTaskStatus::Completed, $task->status);
        $this->assertSame('2026-09-11 09:20:00', $task->completed_at?->format('Y-m-d H:i:s'));
        $this->assertSame('Completed with client agreement.', $task->completion_note);
        $this->assertSame($originalTitle, $template->title);
        $this->assertSame($originalInstructions, $template->instructions);
        $this->assertTrue($template->is_required);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.complete', [$visit, $task]), [
                'completion_note' => 'Duplicate should not replace the original note.',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('Completed with client agreement.', $task->completion_note);
        $this->assertSame('2026-09-11 09:20:00', $task->completed_at?->format('Y-m-d H:i:s'));
    }

    public function test_dsp_can_skip_with_reason(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'task' => $task] = $this->startedVisit();
        $this->seedSkipReasons();
        $reason = SkipReason::query()->where('code', 'not_applicable')->firstOrFail();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.skip', [$visit, $task]), [
                'skip_reason_id' => $reason->id,
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(VisitTaskStatus::Skipped, $task->status);
        $this->assertSame($reason->id, $task->skip_reason_id);
        $this->assertNotNull($task->skipped_at);
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $visit->id)
                ->where('type', VisitExceptionType::CriticalTaskSkipped)
                ->where('visit_task_id', $task->id)
                ->exists(),
        );
    }

    public function test_comment_required_skip_is_rejected_without_comment(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'task' => $task] = $this->startedVisit();
        $this->seedSkipReasons();
        $other = SkipReason::query()->where('code', 'other')->firstOrFail();

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.tasks.skip', [$visit, $task]), [
                'skip_reason_id' => $other->id,
            ])
            ->assertSessionHasErrors('skip_comment');

        $this->assertSame(VisitTaskStatus::Pending, $task->fresh()->status);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.skip', [$visit, $task]), [
                'skip_reason_id' => $other->id,
                'skip_comment' => 'Equipment was already used earlier today.',
            ])
            ->assertRedirect();

        $this->assertSame(VisitTaskStatus::Skipped, $task->fresh()->status);
        $this->assertSame('Equipment was already used earlier today.', $task->fresh()->skip_comment);
    }

    public function test_client_refusal_and_required_skip_create_exceptions(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'task' => $task] = $this->startedVisit();
        $this->seedSkipReasons();
        $refused = SkipReason::query()->where('code', SkipReason::CLIENT_REFUSED)->firstOrFail();

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.tasks.skip', [$visit, $task]), [
                'skip_reason_id' => $refused->id,
            ])
            ->assertSessionHasErrors('skip_comment');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.skip', [$visit, $task]), [
                'skip_reason_id' => $refused->id,
                'skip_comment' => 'Client declined morning ADLs today.',
            ])
            ->assertRedirect();

        $types = VisitException::query()
            ->where('visit_id', $visit->id)
            ->pluck('type')
            ->map(fn ($type) => $type instanceof VisitExceptionType ? $type->value : $type)
            ->all();

        $this->assertContains(VisitExceptionType::ClientRefusal->value, $types);
        $this->assertContains(VisitExceptionType::CriticalTaskSkipped->value, $types);
        $this->assertSame(2, VisitException::query()->where('visit_id', $visit->id)->count());

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.skip', [$visit, $task]), [
                'skip_reason_id' => $refused->id,
                'skip_comment' => 'Client declined morning ADLs today.',
            ])
            ->assertRedirect();

        $this->assertSame(2, VisitException::query()->where('visit_id', $visit->id)->count());
    }

    public function test_handover_and_visit_notes_persist(): void
    {
        ['dsp' => $dsp, 'visit' => $visit] = $this->startedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'visit_notes' => 'Client was in good spirits.',
                'handover_note' => 'Please offer a snack after lunch.',
            ])
            ->assertRedirect();

        $visit->refresh();
        $this->assertSame('Client was in good spirits.', $visit->visit_notes);
        $this->assertSame('Please offer a snack after lunch.', $visit->handover_note);
    }

    public function test_clock_out_with_gps_completes_visit_and_clears_active_state(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'task' => $task] = $this->startedVisit();
        Carbon::setTestNow('2026-09-11 15:05:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.complete', [$visit, $task]))
            ->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'handover_note' => 'Next DSP should review the evening plan.',
            ])
            ->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), $this->clockOutGps())
            ->assertRedirect(route('visits.show', $visit));

        $visit->refresh();
        $this->assertSame(VisitStatus::Completed, $visit->status);
        $this->assertSame('2026-09-11 15:05:00', $visit->clocked_out_at?->format('Y-m-d H:i:s'));
        $this->assertEqualsWithDelta(40.1111111, (float) $visit->clock_out_latitude, 0.0001);
        $this->assertSame(ClockInLocationMethod::BrowserGps, $visit->clock_out_location_method);
        $this->assertSame(ClockInLocationStatus::Captured, $visit->clock_out_location_status);
        $this->assertNull($visit->clock_out_unavailable_reason);
        $this->assertSame(ScheduledVisitStatus::Completed, $visit->scheduledVisit->fresh()->status);
        $this->assertSame('Next DSP should review the evening plan.', $visit->handover_note);
        $this->assertNull(
            Visit::query()->where('employee_id', $dsp->id)->inProgress()->first(),
        );

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('dashboard.active_visit', null));
    }

    public function test_gps_unavailable_clock_out_stores_attestation_and_exception(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'task' => $task] = $this->startedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.complete', [$visit, $task]))
            ->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), $this->clockOutUnavailable())
            ->assertRedirect();

        $visit->refresh();
        $this->assertNull($visit->clock_out_latitude);
        $this->assertNull($visit->clock_out_longitude);
        $this->assertSame(ClockInLocationMethod::GpsUnavailable, $visit->clock_out_location_method);
        $this->assertSame('GPS unavailable at clock-out.', $visit->clock_out_unavailable_reason);
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $visit->id)
                ->where('type', VisitExceptionType::GpsUnavailable)
                ->whereNull('visit_task_id')
                ->exists(),
        );
    }

    public function test_duplicate_clock_out_is_blocked(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'task' => $task] = $this->startedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.complete', [$visit, $task]))
            ->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), $this->clockOutUnavailable())
            ->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.clock-out', $visit), $this->clockOutUnavailable())
            ->assertForbidden();

        $this->assertSame(1, Visit::query()->whereNotNull('clocked_out_at')->count());
    }

    public function test_other_dsp_cannot_alter_visit(): void
    {
        ['visit' => $visit, 'task' => $task] = $this->startedVisit();
        $other = Employee::factory()->dsp()->create();
        $this->seedSkipReasons();
        $reason = SkipReason::query()->where('code', 'not_applicable')->firstOrFail();

        $this->actingAs($other->user()->firstOrFail())
            ->post(route('visits.tasks.complete', [$visit, $task]))
            ->assertForbidden();

        $this->actingAs($other->user()->firstOrFail())
            ->post(route('visits.tasks.skip', [$visit, $task]), [
                'skip_reason_id' => $reason->id,
            ])
            ->assertForbidden();

        $this->actingAs($other->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'handover_note' => 'Should not save.',
            ])
            ->assertForbidden();

        $this->actingAs($other->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), $this->clockOutUnavailable())
            ->assertForbidden();

        $this->assertSame(VisitTaskStatus::Pending, $task->fresh()->status);
        $this->assertNull($visit->fresh()->handover_note);
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
    }

    public function test_unfinished_required_tasks_require_acknowledgement(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'task' => $task] = $this->startedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.clock-out', $visit), $this->clockOutUnavailable())
            ->assertSessionHasErrors('acknowledge_unfinished_required');

        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $this->assertSame(VisitTaskStatus::Pending, $task->fresh()->status);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), [
                ...$this->clockOutUnavailable(),
                'acknowledge_unfinished_required' => true,
            ])
            ->assertRedirect();

        $this->assertSame(VisitTaskStatus::Pending, $task->fresh()->status);
        $this->assertTrue($visit->fresh()->unfinished_required_acknowledged);
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $visit->id)
                ->where('type', VisitExceptionType::OtherVisitException)
                ->exists(),
        );
    }

    public function test_consecutive_scheduled_visits_remain_separate_after_clock_out(): void
    {
        ['dsp' => $dsp, 'visit' => $firstVisit, 'task' => $task] = $this->startedVisit();
        $second = ScheduledVisit::factory()->forDsp($dsp)->forClient($firstVisit->client)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '16:00:00',
            'ends_at' => '20:00:00',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.complete', [$firstVisit, $task]))
            ->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $firstVisit), $this->clockOutUnavailable())
            ->assertRedirect();

        Carbon::setTestNow('2026-09-11 16:05:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $second), $this->clockInUnavailable())
            ->assertRedirect();

        $this->assertSame(2, Visit::query()->count());
        $this->assertNotSame(
            $firstVisit->id,
            Visit::query()->where('scheduled_visit_id', $second->id)->value('id'),
        );
        $this->assertSame(ScheduledVisitStatus::Completed, $firstVisit->scheduledVisit->fresh()->status);
        $this->assertSame(ScheduledVisitStatus::InProgress, $second->fresh()->status);
    }
}
