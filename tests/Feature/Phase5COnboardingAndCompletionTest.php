<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\InAppNotificationType;
use App\Enums\JobType;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeeReference;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase5COnboardingAndCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_onboard_a_dsp_with_role_education_references_and_availability(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'first_name' => 'Jordan',
                'last_name' => 'Lee',
                'email' => 'jordan.lee@mdm.test',
                'job_type' => JobType::Dsp->value,
                'job_title' => 'Direct Support Professional',
                'employment_status' => EmploymentStatus::Active->value,
                'supervisor_id' => $supervisor->id,
                'create_login' => true,
                'password' => 'password',
                'password_confirmation' => 'password',
                'date_of_birth' => '1994-04-12',
                'hired_on' => '2026-09-01',
                'address_line_1' => '10 Maple St',
                'city' => 'Lakeside',
                'state' => 'OH',
                'emergency_contact_name' => 'Pat Lee',
                'emergency_contact_phone' => '555-0100',
                'ssn' => '123-45-6789',
                'availability_days' => [
                    ['weekday' => 1, 'is_available' => true, 'starts_at' => '08:00', 'ends_at' => '16:00'],
                ],
                'educations' => [
                    ['level' => 'high_school', 'institution_name' => 'Lakeside High'],
                ],
                'references' => [
                    ['name' => 'Sam Rivera', 'relationship' => 'Colleague', 'home_phone' => '555-0200'],
                    ['name' => 'Chris Nguyen', 'relationship' => 'Former supervisor', 'home_phone' => '555-0201'],
                ],
                'work_histories' => [
                    ['employer' => 'Harbor Care', 'job_title' => 'Aide'],
                ],
                'ohio_resident_5_years' => true,
                'has_conviction' => false,
                'credentials' => [
                    ['type' => 'tb_screening', 'name' => 'TB Screening', 'status' => 'active'],
                    ['type' => 'physician_statement', 'name' => 'Physician Good-Health Statement', 'status' => 'active'],
                ],
            ])
            ->assertRedirect();

        $employee = Employee::query()->where('email', 'jordan.lee@mdm.test')->firstOrFail();

        $this->assertSame(JobType::Dsp, $employee->job_type);
        $this->assertSame('Direct Support Professional', $employee->job_title);
        $this->assertTrue($employee->user?->isDsp());
        $this->assertSame('123-45-6789', $employee->ssn);
        $this->assertDatabaseHas('employee_educations', [
            'employee_id' => $employee->id,
            'institution_name' => 'Lakeside High',
        ]);
        $this->assertSame(2, EmployeeReference::query()->where('employee_id', $employee->id)->count());
        $this->assertDatabaseHas('dsp_weekly_availabilities', [
            'employee_id' => $employee->id,
            'weekday' => 1,
        ]);
        $this->assertDatabaseHas('employee_credentials', [
            'employee_id' => $employee->id,
            'type' => 'tb_screening',
        ]);

        $notification = InAppNotification::query()
            ->where('type', InAppNotificationType::Profile)
            ->where('body', 'like', '%Jordan Lee%')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('123-45-6789', (string) $notification->body);
        $this->assertStringNotContainsString('SSN', (string) $notification->body);
    }

    public function test_admin_employee_can_receive_an_admin_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'first_name' => 'Riley',
                'last_name' => 'Admin',
                'email' => 'riley.admin@mdm.test',
                'job_type' => JobType::Admin->value,
                'job_title' => 'Administrator',
                'employment_status' => EmploymentStatus::Active->value,
                'create_login' => true,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect();

        $employee = Employee::query()->where('email', 'riley.admin@mdm.test')->firstOrFail();
        $this->assertSame(JobType::Admin, $employee->job_type);
        $this->assertSame(Role::Admin, $employee->user?->role);
    }

    public function test_dsp_cannot_view_another_employees_security_fields(): void
    {
        $dsp = Employee::factory()->dsp()->create([
            'ssn' => '111-22-3333',
            'security_comments' => 'Confidential note',
        ]);
        $other = Employee::factory()->dsp()->create([
            'ssn' => '999-88-7777',
            'security_comments' => 'Should stay hidden',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('employees.show', $other))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('employees.show', $dsp))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.view_sensitive', false)
                ->missing('employee.ssn_masked')
                ->missing('employee.security_comments')
            );
    }

    public function test_profile_photos_are_stored_on_disk_not_as_base64(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->dsp()->create();
        $file = UploadedFile::fake()->image('maya.jpg', 120, 120);

        $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'job_type' => JobType::Dsp->value,
                'employment_status' => EmploymentStatus::Active->value,
                'profile_photo' => $file,
            ])
            ->assertRedirect(route('employees.show', $employee));

        $employee->refresh();
        $this->assertNotNull($employee->profile_photo_path);
        $this->assertStringNotContainsString('base64', (string) $employee->profile_photo_path);
        Storage::disk('local')->assertExists($employee->profile_photo_path);

        $this->actingAs($admin)
            ->get(route('employees.photo', $employee))
            ->assertOk();
    }

    public function test_client_and_employee_completion_are_role_aware_and_not_column_counts(): void
    {
        $dsp = Employee::factory()->dsp()->create([
            'date_of_birth' => '1990-01-01',
            'hired_on' => '2024-01-01',
            'job_title' => 'Direct Support Professional',
            'address_line_1' => '1 Main',
            'city' => 'Lakeside',
            'state' => 'OH',
            'emergency_contact_name' => 'A',
            'emergency_contact_phone' => '555',
            'ohio_resident_5_years' => true,
            'has_conviction' => false,
            'has_drivers_license' => false,
        ]);
        EmployeeEducation::factory()->create(['employee_id' => $dsp->id]);
        EmployeeReference::factory()->count(2)->create(['employee_id' => $dsp->id]);

        $report = app(ProfileCompletionService::class)->forEmployee($dsp);
        $keys = collect($report['items'])->pluck('key');

        $this->assertTrue($keys->contains('availability'));
        $this->assertTrue($keys->contains('login_account'));
        $this->assertFalse($keys->contains('assigned_dsps'));
        $this->assertSame(
            collect($report['items'])->where('complete', true)->count(),
            $report['completed'],
        );

        $client = Client::factory()->create();
        $clientReport = app(ProfileCompletionService::class)->forClient($client);
        $clientKeys = collect($clientReport['items'])->pluck('key');
        $this->assertTrue($clientKeys->contains('care_plan'));
        $this->assertTrue($clientKeys->contains('photo'));
        $this->assertGreaterThan(0, $clientReport['missing']);
    }
}
