<?php

namespace Database\Factories;

use App\Models\ComplianceRequirement;
use App\Models\ComplianceTemporaryException;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceTemporaryException>
 */
class ComplianceTemporaryExceptionFactory extends Factory
{
    protected $model = ComplianceTemporaryException::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'compliance_requirement_id' => ComplianceRequirement::factory()->temporaryExceptionEligible(),
            'reason' => 'Authorized temporary operational exception.',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(7),
            'created_by_user_id' => User::factory()->admin(),
        ];
    }
}
