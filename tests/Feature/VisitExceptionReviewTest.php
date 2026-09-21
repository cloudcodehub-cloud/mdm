<?php

namespace Tests\Feature;

use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitExceptionReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{supervisor: Employee, other: Employee, exception: VisitException}
     */
    private function scopedException(): array
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create();
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create();
        $exception = VisitException::factory()->create([
            'visit_id' => $visit->id,
            'type' => VisitExceptionType::ClientRefusal,
            'status' => VisitExceptionStatus::Open,
            'message' => 'Client refused a required task.',
            'context' => ['source' => 'task_skip'],
        ]);

        return [
            'supervisor' => $supervisor,
            'other' => $other,
            'exception' => $exception,
        ];
    }

    public function test_supervisor_can_review_and_resolve_in_scope_exception(): void
    {
        ['supervisor' => $supervisor, 'exception' => $exception] = $this->scopedException();
        $originalMessage = $exception->message;
        $originalContext = $exception->context;

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('visit-exceptions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visit-exceptions/index')
                ->has('exceptions.data', 1)
                ->where('exceptions.data.0.id', $exception->id)
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('visit-exceptions.review', $exception), [
                'review_notes' => 'Reviewed with the DSP.',
            ])
            ->assertRedirect(route('visit-exceptions.show', $exception));

        $exception->refresh();
        $this->assertSame(VisitExceptionStatus::Reviewed, $exception->status);
        $this->assertSame('Reviewed with the DSP.', $exception->review_notes);
        $this->assertSame($originalMessage, $exception->message);
        $this->assertSame($originalContext, $exception->context);
        $this->assertNotEmpty($exception->status_history);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('visit-exceptions.resolve', $exception), [
                'resolution_notes' => 'Follow-up complete.',
            ])
            ->assertRedirect(route('visit-exceptions.show', $exception));

        $exception->refresh();
        $this->assertSame(VisitExceptionStatus::Resolved, $exception->status);
        $this->assertSame('Follow-up complete.', $exception->resolution_notes);
        $this->assertSame($originalMessage, $exception->message);
        $this->assertCount(2, $exception->status_history ?? []);
    }

    public function test_resolution_notes_remain_optional(): void
    {
        ['supervisor' => $supervisor, 'exception' => $exception] = $this->scopedException();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('visit-exceptions.resolve', $exception), [])
            ->assertRedirect(route('visit-exceptions.show', $exception));

        $this->assertSame(VisitExceptionStatus::Resolved, $exception->fresh()->status);
        $this->assertNull($exception->fresh()->resolution_notes);
    }

    public function test_out_of_scope_supervisor_cannot_access_exception(): void
    {
        ['other' => $other, 'exception' => $exception] = $this->scopedException();

        $this->actingAs($other->user()->firstOrFail())
            ->get(route('visit-exceptions.show', $exception))
            ->assertForbidden();

        $this->actingAs($other->user()->firstOrFail())
            ->get(route('visit-exceptions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('exceptions.data', 0));

        $this->actingAs($other->user()->firstOrFail())
            ->patch(route('visit-exceptions.review', $exception), [
                'review_notes' => 'Out of scope',
            ])
            ->assertForbidden();

        $this->assertSame(VisitExceptionStatus::Open, $exception->fresh()->status);
    }

    public function test_dsp_cannot_review_exceptions(): void
    {
        ['exception' => $exception] = $this->scopedException();
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($dsp)
            ->get(route('visit-exceptions.show', $exception))
            ->assertForbidden();
    }

    public function test_resolved_exception_cannot_be_reviewed_again(): void
    {
        ['supervisor' => $supervisor, 'exception' => $exception] = $this->scopedException();
        $user = $supervisor->user()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('visit-exceptions.resolve', $exception), [
                'resolution_notes' => 'Closed.',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch(route('visit-exceptions.review', $exception), [
                'review_notes' => 'Too late',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('visit-exceptions.resolve', $exception), [
                'resolution_notes' => 'Again',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_and_admin_can_add_follow_up_without_changing_dsp_notes(): void
    {
        ['supervisor' => $supervisor, 'exception' => $exception] = $this->scopedException();
        $exception->update([
            'message' => 'Client refused a required task.',
            'context' => ['source' => 'task_skip', 'title' => 'Meal preparation'],
        ]);
        $originalMessage = $exception->message;
        $originalContext = $exception->context;

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('visit-exceptions.follow-up', $exception), [
                'notes' => 'Called the DSP and documented next steps.',
            ])
            ->assertRedirect();

        $exception->refresh();
        $this->assertSame(VisitExceptionStatus::Open, $exception->status);
        $this->assertSame($originalMessage, $exception->message);
        $this->assertSame($originalContext, $exception->context);
        $this->assertSame('Called the DSP and documented next steps.', $exception->status_history[0]['notes'] ?? null);
        $this->assertSame($supervisor->user()->firstOrFail()->id, $exception->status_history[0]['user_id'] ?? null);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('visit-exceptions.follow-up', $exception), [
                'notes' => 'Admin confirmed caseload follow-up.',
            ])
            ->assertRedirect();

        $this->assertCount(2, $exception->fresh()->status_history ?? []);
    }

    public function test_supervisor_cannot_add_follow_up_outside_caseload(): void
    {
        ['other' => $other, 'exception' => $exception] = $this->scopedException();

        $this->actingAs($other->user()->firstOrFail())
            ->patch(route('visit-exceptions.follow-up', $exception), [
                'notes' => 'Out of scope follow-up',
            ])
            ->assertForbidden();

        $this->assertNull($exception->fresh()->status_history);
    }

    public function test_dsp_cannot_add_supervisor_follow_up_notes(): void
    {
        ['exception' => $exception] = $this->scopedException();
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($dsp)
            ->patch(route('visit-exceptions.follow-up', $exception), [
                'notes' => 'DSP should not write this',
            ])
            ->assertForbidden();
    }

    public function test_resolved_high_priority_exception_is_not_returned_as_high_priority_open(): void
    {
        ['supervisor' => $supervisor, 'exception' => $exception] = $this->scopedException();
        $user = $supervisor->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('operations.exceptions.high_priority_open.0.id', $exception->id)
            );

        $this->actingAs($user)
            ->get(route('visits.show', $exception->visit_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.has_high_priority_open', true)
                ->where('visit.exceptions.0.is_high_priority_open', true)
            );

        $this->actingAs($user)
            ->patch(route('visit-exceptions.resolve', $exception), [
                'resolution_notes' => 'Closed.',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.exceptions.high_priority_open', 0)
            );

        $this->actingAs($user)
            ->get(route('visits.show', $exception->visit_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.has_high_priority_open', false)
                ->where('visit.exceptions.0.is_high_priority_open', false)
            );
    }
}
