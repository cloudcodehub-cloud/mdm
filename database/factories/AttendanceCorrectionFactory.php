<?php

namespace Database\Factories;

use App\Enums\AttendanceCorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_visit_id' => ScheduledVisit::factory(),
            'visit_id' => null,
            'status' => AttendanceCorrectionStatus::Pending,
            'original_clocked_in_at' => now()->subHours(8),
            'original_clocked_out_at' => now(),
            'requested_clocked_in_at' => now()->subHours(8)->addMinutes(10),
            'requested_clocked_out_at' => now()->subMinutes(5),
            'reason' => 'DSP clocked in after arriving on site.',
            'note' => null,
            'requested_by_user_id' => User::factory()->supervisor(),
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }

    public function forVisit(Visit $visit): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_visit_id' => $visit->scheduled_visit_id,
            'visit_id' => $visit->id,
            'original_clocked_in_at' => $visit->clocked_in_at,
            'original_clocked_out_at' => $visit->clocked_out_at,
        ]);
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceCorrectionStatus::Approved,
            'reviewed_by_user_id' => $reviewer === null ? User::factory()->admin() : $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => 'Approved after timesheet review.',
        ]);
    }
}
