<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\GpsFallbackReason;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitNoteHistoryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 5E — MDM 01 §2.30 Visit Note audit history during Active Visit.
 */
class Phase5EVisitNoteHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_first_visit_note_records_history_without_reason(): void
    {
        [$dsp, $visit] = $this->activeVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'visit_notes' => 'Client calm after lunch.',
                'handover_note' => null,
            ])
            ->assertRedirect();

        $this->assertSame('Client calm after lunch.', $visit->fresh()->visit_notes);
        $event = VisitNoteHistoryEvent::query()->where('visit_id', $visit->id)->first();
        $this->assertNotNull($event);
        $this->assertSame('visit_notes', $event->field_key);
        $this->assertNull($event->previous_content);
        $this->assertSame('Client calm after lunch.', $event->new_content);
        $this->assertNull($event->reason);
        $this->assertSame($dsp->user_id, $event->actor_user_id);
    }

    public function test_amending_visit_note_requires_reason_and_preserves_original(): void
    {
        [$dsp, $visit] = $this->activeVisit();
        $visit->forceFill(['visit_notes' => 'Original note'])->save();

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->patch(route('visits.notes', $visit), [
                'visit_notes' => 'Corrected note',
                'handover_note' => null,
            ])
            ->assertRedirect(route('visits.show', $visit))
            ->assertSessionHasErrors('notes_correction_reason');

        $this->assertSame('Original note', $visit->fresh()->visit_notes);

        $this->actingAs($dsp->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'visit_notes' => 'Corrected note',
                'handover_note' => null,
                'notes_correction_reason' => 'Fixed typo',
            ])
            ->assertRedirect();

        $this->assertSame('Corrected note', $visit->fresh()->visit_notes);
        $this->assertTrue(
            VisitNoteHistoryEvent::query()
                ->where('visit_id', $visit->id)
                ->where('previous_content', 'Original note')
                ->where('new_content', 'Corrected note')
                ->where('reason', 'Fixed typo')
                ->exists(),
        );
    }

    public function test_completed_visit_notes_are_read_only(): void
    {
        [$dsp, $visit] = $this->activeVisit();
        $visit->forceFill([
            'visit_notes' => 'Locked note',
            'handover_note' => 'Handover done',
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now(),
        ])->save();
        $visit->scheduledVisit->forceFill(['status' => ScheduledVisitStatus::Completed])->save();

        $this->actingAs($dsp->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'visit_notes' => 'Should not save',
                'notes_correction_reason' => 'Too late',
            ])
            ->assertForbidden();

        $this->assertSame('Locked note', $visit->fresh()->visit_notes);
    }

    public function test_unrelated_dsp_cannot_update_or_view_note_history(): void
    {
        [, $visit] = $this->activeVisit();
        $visit->forceFill(['visit_notes' => 'Private'])->save();
        VisitNoteHistoryEvent::query()->create([
            'visit_id' => $visit->id,
            'field_key' => 'visit_notes',
            'previous_content' => null,
            'new_content' => 'Private',
            'actor_user_id' => User::factory()->admin()->create()->id,
            'reason' => null,
        ]);

        $other = Employee::factory()->dsp()->create();

        $this->actingAs($other->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'visit_notes' => 'Hijack',
                'notes_correction_reason' => 'Nope',
            ])
            ->assertForbidden();

        $this->actingAs($other->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertForbidden();
    }

    public function test_authorized_roles_see_note_history_on_visit_show(): void
    {
        [$dsp, $visit] = $this->activeVisit();
        $admin = User::factory()->admin()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'visit_notes' => 'Timeline note',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->has('visit.note_history', 1)
                ->where('visit.note_history.0.new_content', 'Timeline note')
                ->where('can.view_note_history', true));
    }

    /**
     * @return array{0: Employee, 1: Visit}
     */
    private function activeVisit(): array
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => now()->toDateString(),
            'status' => ScheduledVisitStatus::InProgress,
        ]);
        $visit = Visit::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now()->subHour(),
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Denied,
            'clock_in_unavailable_reason' => GpsFallbackReason::PermissionDenied->label(),
        ]);

        return [$dsp, $visit];
    }
}
