<?php

namespace Tests\Feature;

use App\Enums\ComplianceVerificationStatus;
use App\Enums\CredentialType;
use App\Enums\EmploymentStatus;
use App\Enums\ReviewStatus;
use App\Enums\ScheduledVisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\EmployeeTimeOff;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Services\DspAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3DLeaveAndSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_leave_warns_but_does_not_release_assignments(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->create();
        $visit = ScheduledVisit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => now()->addDays(2)->toDateString(),
            'status' => ScheduledVisitStatus::Scheduled,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('my-availability.time-off'), [
                'starts_on' => now()->addDays(2)->toDateString(),
                'ends_on' => now()->addDays(2)->toDateString(),
                'reason' => 'Personal appointment',
            ])
            ->assertRedirect(route('my-availability.index'));

        $visit->refresh();
        $this->assertSame($dsp->id, $visit->employee_id);
        $this->assertFalse($visit->staffing_needed);
        $this->assertTrue($visit->needs_attention);
        $this->assertStringContainsString('Pending leave', (string) $visit->attention_reason);
        $this->assertDatabaseHas('employee_time_off', [
            'employee_id' => $dsp->id,
            'status' => ReviewStatus::Pending->value,
        ]);
    }

    public function test_approving_leave_releases_conflicting_future_visits_to_staffing_needed(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        $future = ScheduledVisit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => now()->addDays(3)->toDateString(),
            'status' => ScheduledVisitStatus::Scheduled,
            'series_id' => null,
        ]);
        $past = ScheduledVisit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => now()->subDays(2)->toDateString(),
            'status' => ScheduledVisitStatus::Completed,
        ]);
        $outside = ScheduledVisit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => now()->addDays(10)->toDateString(),
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $timeOff = EmployeeTimeOff::factory()->pending()->create([
            'employee_id' => $dsp->id,
            'requested_by_user_id' => $dsp->user_id,
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDays(4)->toDateString(),
            'starts_at' => null,
            'ends_at' => null,
        ]);

        app(DspAvailabilityService::class)->flagPendingLeaveConflicts($timeOff);

        $this->actingAs($admin)
            ->get(route('availability-requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('time_off.0.impact_preview')
                ->where('time_off.0.impact_preview.will_release_to_staffing_needed', true)
            );

        $this->actingAs($admin)
            ->patch(route('time-off.approve', $timeOff))
            ->assertRedirect();

        $future->refresh();
        $past->refresh();
        $outside->refresh();
        $timeOff->refresh();

        $this->assertSame(ReviewStatus::Approved, $timeOff->status);
        $this->assertNull($future->employee_id);
        $this->assertTrue($future->staffing_needed);
        $this->assertSame($dsp->id, $future->released_employee_id);
        $this->assertSame($dsp->id, $past->employee_id);
        $this->assertFalse($past->staffing_needed);
        $this->assertSame($dsp->id, $outside->employee_id);
        $this->assertFalse($outside->staffing_needed);
        $this->assertSame(EmploymentStatus::Active, $dsp->fresh()->employment_status);
    }

    public function test_unassigned_supervisor_cannot_approve_leave(): void
    {
        $other = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->create();
        $timeOff = EmployeeTimeOff::factory()->pending()->create([
            'employee_id' => $dsp->id,
            'requested_by_user_id' => $dsp->user_id,
        ]);

        $this->actingAs($other->user()->firstOrFail())
            ->patch(route('time-off.approve', $timeOff))
            ->assertForbidden();
    }

    public function test_personal_info_request_preserves_authoritative_values_until_approval(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create([
            'cell_phone' => '555-0001',
            'city' => 'Dayton',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('my-profile-changes.store'), [
                'cell_phone' => '555-9999',
                'city' => 'Columbus',
                'reason' => 'Moved',
            ])
            ->assertRedirect(route('my-profile-changes.index'));

        $dsp->refresh();
        $this->assertSame('555-0001', $dsp->cell_phone);
        $this->assertSame('Dayton', $dsp->city);

        $change = EmployeeProfileChangeRequest::query()->firstOrFail();
        $this->assertSame(ReviewStatus::Pending, $change->status);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('profile-change-requests.approve', $change))
            ->assertRedirect();

        $dsp->refresh();
        $this->assertSame('555-9999', $dsp->cell_phone);
        $this->assertSame('Columbus', $dsp->city);
        $this->assertSame(ReviewStatus::Approved, $change->fresh()->status);
        $this->assertDatabaseHas('employee_admin_history_events', [
            'employee_id' => $dsp->id,
            'category' => 'profile_change',
        ]);
    }

    public function test_dsp_cannot_review_another_employees_profile_change(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $change = EmployeeProfileChangeRequest::factory()->create([
            'employee_id' => $other->id,
            'requested_by_user_id' => $other->user_id,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->patch(route('profile-change-requests.approve', $change))
            ->assertForbidden();
    }

    public function test_dsp_can_submit_compliance_evidence_with_document_without_changing_eligibility(): void
    {
        Storage::fake('local');

        $dsp = Employee::factory()->dsp()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('employees.compliance-evidence.store', $dsp), [
                'kind' => 'credential',
                'type' => CredentialType::DriversLicense->value,
                'name' => 'Driver License renewal',
                'expires_on' => now()->addYear()->toDateString(),
                'document' => UploadedFile::fake()->create('license.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $credential = EmployeeCredential::query()->where('employee_id', $dsp->id)->latest('id')->firstOrFail();
        $this->assertSame(ComplianceVerificationStatus::PendingReview, $credential->verification_status);
        $this->assertNotNull($credential->document_path);
        $this->assertTrue($dsp->fresh()->isActiveDsp());
    }

    public function test_rejecting_pending_leave_clears_warning_without_releasing(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $visit = ScheduledVisit::factory()->create([
            'employee_id' => $dsp->id,
            'service_date' => now()->addDay()->toDateString(),
            'status' => ScheduledVisitStatus::Scheduled,
        ]);
        $timeOff = EmployeeTimeOff::factory()->pending()->create([
            'employee_id' => $dsp->id,
            'requested_by_user_id' => $dsp->user_id,
            'starts_on' => now()->addDay()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ]);

        app(DspAvailabilityService::class)->flagPendingLeaveConflicts($timeOff);
        $this->assertTrue($visit->fresh()->needs_attention);

        $this->actingAs($admin)
            ->patch(route('time-off.reject', $timeOff))
            ->assertRedirect();

        $visit->refresh();
        $this->assertSame($dsp->id, $visit->employee_id);
        $this->assertFalse($visit->staffing_needed);
        $this->assertFalse($visit->needs_attention);
    }
}
