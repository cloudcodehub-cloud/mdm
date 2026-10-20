<?php

namespace Tests\Feature\Documents;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\DateFormat;
use App\Enums\IncidentStatus;
use App\Enums\TimeFormat;
use App\Enums\TrainingStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\Incident;
use App\Models\OrganizationSetting;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase9BAdminDocumentsAndBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Harbor Supports',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);
    }

    public function test_admin_documents_index_lists_source_owned_entries_with_filters(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        $credential = EmployeeCredential::factory()->create([
            'employee_id' => $dsp->id,
            'type' => CredentialType::Cpr,
            'name' => 'CPR Card',
            'status' => CredentialStatus::Active,
            'document_path' => UploadedFile::fake()->create('cpr.pdf', 20, 'application/pdf')->store('credentials', 'local'),
        ]);

        EmployeeTraining::factory()->create([
            'employee_id' => $dsp->id,
            'title' => 'DSP Orientation Evidence',
            'status' => TrainingStatus::Completed,
            'document_path' => UploadedFile::fake()->create('orientation.pdf', 20, 'application/pdf')->store('trainings', 'local'),
        ]);

        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->forClient($client)->create();
        Visit::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'employee_id' => $dsp->id,
            'client_id' => $client->id,
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now(),
        ]);

        Incident::factory()->create([
            'client_id' => $client->id,
            'reporter_employee_id' => $dsp->id,
            'status' => IncidentStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/index')
                ->has('documents.data')
                ->where('documents.total', fn (int $total): bool => $total >= 4));

        $this->actingAs($admin)
            ->get(route('admin.documents.index', [
                'origin' => 'uploaded',
                'source_area' => 'hr_compliance',
                'search' => 'CPR',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.total', 1)
                ->where('documents.data.0.title', 'CPR Card')
                ->where('documents.data.0.download_url', route('admin.documents.credentials.download', $credential)));
    }

    public function test_non_admin_cannot_access_admin_documents_index_or_uploads(): void
    {
        Storage::fake('local');
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $dsp = Employee::factory()->dsp()->create();
        $credential = EmployeeCredential::factory()->create([
            'employee_id' => $dsp->id,
            'document_path' => UploadedFile::fake()->create('cpr.pdf', 10, 'application/pdf')->store('credentials', 'local'),
        ]);

        $this->actingAs($supervisor)
            ->get(route('admin.documents.index'))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('admin.documents.credentials.download', $credential))
            ->assertForbidden();
    }

    public function test_admin_can_download_uploaded_credential_without_public_url(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $path = UploadedFile::fake()->create('license.pdf', 12, 'application/pdf')->store('credentials', 'local');
        $credential = EmployeeCredential::factory()->create([
            'employee_id' => $dsp->id,
            'document_path' => $path,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.documents.credentials.download', $credential))
            ->assertOk();

        $this->assertStringContainsString('admin/documents/credentials', route('admin.documents.credentials.download', $credential));
        $this->assertStringNotContainsString('/storage/', route('admin.documents.credentials.download', $credential));
    }

    public function test_completed_visit_and_incident_entries_link_to_existing_authorized_document_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->forClient($client)->create();
        $visit = Visit::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'employee_id' => $dsp->id,
            'client_id' => $client->id,
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now(),
        ]);
        $incident = Incident::factory()->create([
            'client_id' => $client->id,
            'reporter_employee_id' => $dsp->id,
            'status' => IncidentStatus::Closed,
            'submitted_at' => now()->subDay(),
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.documents.index', ['source_area' => 'visits']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.data.0.download_url', route('documents.completed-visit.pdf', $visit))
                ->where('documents.data.0.source_url', route('visits.show', $visit)));

        $this->actingAs($admin)
            ->get(route('admin.documents.index', ['category' => 'incident_final_pdf']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.total', 1)
                ->where('documents.data.0.download_url', route('documents.incident-final.pdf', $incident))
                ->where('documents.data.0.source_url', route('incidents.show', $incident)));
    }

    public function test_admin_can_upload_and_remove_horizontal_logo_with_shell_fallback_hierarchy(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $primary = UploadedFile::fake()->image('primary.png', 80, 80);
        $horizontal = UploadedFile::fake()->image('horizontal.png', 240, 80);

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), [
                'organization_name' => 'Harbor Supports',
                'timezone' => 'America/New_York',
                'date_format' => DateFormat::MonthDayYear->value,
                'time_format' => TimeFormat::TwelveHour->value,
                'first_day_of_week' => 0,
                'credential_expiring_soon_days' => 30,
                'incident_acknowledgment_minutes' => 60,
                'staffing_needed_urgency_hours' => 24,
                'logo' => $primary,
                'horizontal_logo' => $horizontal,
            ])
            ->assertSessionHasNoErrors();

        $settings = OrganizationSetting::query()->firstOrFail();
        $this->assertNotNull($settings->logo_path);
        $this->assertNotNull($settings->horizontal_logo_path);
        Storage::disk('local')->assertExists($settings->logo_path);
        Storage::disk('local')->assertExists($settings->horizontal_logo_path);

        $service = app(SettingsService::class);
        $this->assertNotNull($service->logoUrl());
        $this->assertNotNull($service->horizontalLogoUrl());
        $this->assertSame($service->horizontalLogoUrl(), $service->shellLogoUrl());
        $this->assertNotNull($service->logoDataUri());

        $this->actingAs($admin)
            ->get(route('organization.logo', ['variant' => 'horizontal']))
            ->assertOk();

        $this->actingAs($admin)
            ->patch(route('settings.general.update'), [
                'organization_name' => 'Harbor Supports',
                'timezone' => 'America/New_York',
                'date_format' => DateFormat::MonthDayYear->value,
                'time_format' => TimeFormat::TwelveHour->value,
                'first_day_of_week' => 0,
                'credential_expiring_soon_days' => 30,
                'incident_acknowledgment_minutes' => 60,
                'staffing_needed_urgency_hours' => 24,
                'remove_horizontal_logo' => '1',
            ])
            ->assertSessionHasNoErrors();

        app()->forgetInstance(SettingsService::class);
        $service = app(SettingsService::class);
        $this->assertNull($service->horizontalLogoUrl());
        $this->assertSame($service->logoUrl(), $service->shellLogoUrl());
    }

    public function test_documents_continue_to_use_primary_logo_not_horizontal_only(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $horizontal = UploadedFile::fake()->image('horizontal.png', 240, 80);

        $this->actingAs($admin)
            ->patch(route('settings.general.update'), [
                'organization_name' => 'Harbor Supports',
                'timezone' => 'America/New_York',
                'date_format' => DateFormat::MonthDayYear->value,
                'time_format' => TimeFormat::TwelveHour->value,
                'first_day_of_week' => 0,
                'credential_expiring_soon_days' => 30,
                'incident_acknowledgment_minutes' => 60,
                'staffing_needed_urgency_hours' => 24,
                'horizontal_logo' => $horizontal,
            ])
            ->assertSessionHasNoErrors();

        app()->forgetInstance(SettingsService::class);
        $service = app(SettingsService::class);
        $this->assertNull($service->logoDataUri());
        $this->assertNotNull($service->horizontalLogoDataUri());
        $this->assertSame($service->horizontalLogoUrl(), $service->shellLogoUrl());
    }
}
