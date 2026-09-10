<?php

namespace Tests\Feature\Domain;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\TrainingStatus;
use App\Http\Requests\ClientAuthorizationRequest;
use App\Http\Requests\EmployeeCredentialRequest;
use App\Http\Requests\EmployeeTrainingRequest;
use App\Http\Requests\ShiftTemplateRequest;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\ShiftTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DomainAuthorizationPhase1B1Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_credentials_training_authorizations_and_shifts(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $credential = EmployeeCredential::factory()->forEmployee($employee)->create();
        $training = EmployeeTraining::factory()->forEmployee($employee)->create();
        $authorization = ClientAuthorization::factory()->forClient($client)->create();
        $shift = ShiftTemplate::factory()->day()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('create', EmployeeCredential::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $credential));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $credential));
        $this->assertTrue(Gate::forUser($admin)->allows('create', EmployeeTraining::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $training));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $training));
        $this->assertTrue(Gate::forUser($admin)->allows('create', ClientAuthorization::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $authorization));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $authorization));
        $this->assertTrue(Gate::forUser($admin)->allows('create', ShiftTemplate::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $shift));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $shift));
    }

    public function test_supervisors_can_view_assigned_employee_credentials_and_client_authorizations(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        $report = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $otherReport = Employee::factory()->dsp()->forSupervisor($otherSupervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $otherClient = Client::factory()->forSupervisor($otherSupervisor)->create();

        $credential = EmployeeCredential::factory()->forEmployee($report)->create();
        $otherCredential = EmployeeCredential::factory()->forEmployee($otherReport)->create();
        $training = EmployeeTraining::factory()->forEmployee($report)->create();
        $authorization = ClientAuthorization::factory()->forClient($client)->create();
        $otherAuthorization = ClientAuthorization::factory()->forClient($otherClient)->create();
        $shift = ShiftTemplate::factory()->evening()->create();

        $user = $supervisor->user()->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('view', $credential));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherCredential));
        $this->assertTrue(Gate::forUser($user)->allows('view', $training));
        $this->assertTrue(Gate::forUser($user)->allows('view', $authorization));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherAuthorization));
        $this->assertTrue(Gate::forUser($user)->allows('view', $shift));
        $this->assertTrue(Gate::forUser($user)->denies('create', EmployeeCredential::class));
        $this->assertTrue(Gate::forUser($user)->denies('create', ClientAuthorization::class));
        $this->assertTrue(Gate::forUser($user)->denies('create', ShiftTemplate::class));
    }

    public function test_dsps_can_view_own_credentials_assigned_client_authorizations_and_shift_templates(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $otherDsp = Employee::factory()->dsp()->create();
        $assignedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        ClientDspAssignment::factory()->forDsp($dsp)->forClient($assignedClient)->create();

        $ownCredential = EmployeeCredential::factory()->forEmployee($dsp)->create();
        $otherCredential = EmployeeCredential::factory()->forEmployee($otherDsp)->create();
        $authorization = ClientAuthorization::factory()->forClient($assignedClient)->create();
        $otherAuthorization = ClientAuthorization::factory()->forClient($otherClient)->create();
        $shift = ShiftTemplate::factory()->overnight()->create();

        $user = $dsp->user()->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('view', $ownCredential));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherCredential));
        $this->assertTrue(Gate::forUser($user)->allows('view', $authorization));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherAuthorization));
        $this->assertTrue(Gate::forUser($user)->allows('view', $shift));
        $this->assertTrue(Gate::forUser($user)->denies('update', $ownCredential));
        $this->assertTrue(Gate::forUser($user)->denies('create', ShiftTemplate::class));
    }

    public function test_form_request_rules_accept_valid_payloads_including_overnight_times(): void
    {
        $employee = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        $credentialValidator = Validator::make([
            'employee_id' => $employee->id,
            'type' => CredentialType::Cpr->value,
            'name' => 'CPR Certification',
            'status' => CredentialStatus::Active->value,
        ], (new EmployeeCredentialRequest)->rules());

        $trainingValidator = Validator::make([
            'employee_id' => $employee->id,
            'title' => 'DSP Orientation',
            'status' => TrainingStatus::Completed->value,
            'hours' => '8.00',
        ], (new EmployeeTrainingRequest)->rules());

        $authorizationValidator = Validator::make([
            'client_id' => $client->id,
            'authorization_number' => 'AUTH-TEST-1',
            'payer' => 'Ohio Medicaid',
            'service_type' => 'Personal Care',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'authorized_units' => '40',
            'unit' => 'hour',
            'status' => 'active',
        ], (new ClientAuthorizationRequest)->rules());

        $shiftValidator = Validator::make([
            'name' => '11–7',
            'code' => 'overnight_11_7',
            'starts_at' => '23:00:00',
            'ends_at' => '07:00:00',
            'is_active' => true,
        ], (new ShiftTemplateRequest)->rules());

        $this->assertTrue($credentialValidator->passes());
        $this->assertTrue($trainingValidator->passes());
        $this->assertTrue($authorizationValidator->passes());
        $this->assertTrue($shiftValidator->passes());
    }
}
