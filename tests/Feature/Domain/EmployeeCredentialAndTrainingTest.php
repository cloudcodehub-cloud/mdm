<?php

namespace Tests\Feature\Domain;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCredentialAndTrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_can_have_multiple_credentials(): void
    {
        $employee = Employee::factory()->dsp()->create();

        $cpr = EmployeeCredential::factory()->forEmployee($employee)->create([
            'type' => CredentialType::Cpr,
            'name' => 'CPR Certification',
        ]);

        $license = EmployeeCredential::factory()->forEmployee($employee)->create([
            'type' => CredentialType::DriversLicense,
            'name' => "Driver's License",
        ]);

        $this->assertCount(2, $employee->credentials);
        $this->assertTrue($employee->credentials->contains($cpr));
        $this->assertTrue($employee->credentials->contains($license));
        $this->assertTrue($cpr->employee->is($employee));
    }

    public function test_expired_credentials_are_not_currently_valid(): void
    {
        $valid = EmployeeCredential::factory()->create([
            'expires_on' => now()->addYear()->toDateString(),
            'status' => CredentialStatus::Active,
        ]);

        $expired = EmployeeCredential::factory()->expired()->create();

        $this->assertTrue($valid->isCurrentlyValid());
        $this->assertFalse($expired->isCurrentlyValid());
        $this->assertTrue($expired->isExpired());
        $this->assertCount(1, EmployeeCredential::query()->currentlyValid()->get());
    }

    public function test_an_employee_can_have_training_records(): void
    {
        $employee = Employee::factory()->dsp()->create();

        $completed = EmployeeTraining::factory()->forEmployee($employee)->create([
            'title' => 'DSP Orientation',
        ]);

        $inProgress = EmployeeTraining::factory()->inProgress()->forEmployee($employee)->create([
            'title' => 'Fire Safety',
        ]);

        $this->assertCount(2, $employee->trainings);
        $this->assertTrue($completed->isCurrentlyValid());
        $this->assertFalse($inProgress->isCurrentlyValid());
        $this->assertSame(TrainingStatus::InProgress, $inProgress->status);
        $this->assertCount(1, EmployeeTraining::query()->currentlyValid()->get());
    }
}
