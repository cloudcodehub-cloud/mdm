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
}
