<?php

namespace Tests\Feature\Domain;

use App\Enums\ScheduledVisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ShiftTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduledVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visit_can_use_a_shift_template_including_overnight(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $overnight = ShiftTemplate::factory()->overnight()->create();

        $visit = ScheduledVisit::factory()
            ->forClient($client)
            ->forDsp($dsp)
            ->create([
                'supervisor_id' => $supervisor->id,
                'shift_template_id' => $overnight->id,
                'starts_at' => null,
                'ends_at' => null,
                'service_date' => '2026-09-10',
                'service_type' => 'Residential Habilitation',
            ]);

        $this->assertTrue($visit->usesShiftTemplate());
        $this->assertTrue($visit->spansOvernight());
        $this->assertTrue($visit->dsp->is($dsp));
        $this->assertTrue($visit->supervisor?->is($supervisor));
        $this->assertSame('2026-09-10 23:00:00', $visit->startsAtOn()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 07:00:00', $visit->endsAtOn()->format('Y-m-d H:i:s'));
    }

    public function test_a_visit_can_use_explicit_times_without_a_shift_template(): void
    {
        $visit = ScheduledVisit::factory()->overnight()->create([
            'service_date' => '2026-09-10',
            'shift_template_id' => null,
        ]);

        $this->assertFalse($visit->usesShiftTemplate());
        $this->assertTrue($visit->spansOvernight());
        $this->assertSame('2026-09-10 23:00:00', $visit->startsAtOn(Carbon::parse('2026-09-10'))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 07:00:00', $visit->endsAtOn()->format('Y-m-d H:i:s'));
        $this->assertSame(ScheduledVisitStatus::Scheduled, $visit->status);
        $this->assertCount(1, ScheduledVisit::query()->scheduled()->get());
    }

    public function test_cancelled_visits_are_excluded_from_the_scheduled_scope(): void
    {
        ScheduledVisit::factory()->cancelled()->create();

        $this->assertCount(0, ScheduledVisit::query()->scheduled()->get());
    }
}
