<?php

namespace Tests\Feature;

use App\Enums\InAppNotificationType;
use App\Enums\Role;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketHistoryAction;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketUrgency;
use App\Enums\SupportTicketWorkImpact;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeSecurityIncident;
use App\Models\InAppNotification;
use App\Models\Incident;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use App\Services\ItTechAccessService;
use App\Services\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase8ASupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-20 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_any_active_user_may_submit_own_ticket_with_system_urgency(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->post(route('support-tickets.store'), [
                'category' => SupportTicketCategory::HardwareDevice->value,
                'subject' => 'Laptop will not start',
                'description' => 'Device stops at the vendor logo.',
                'work_impact' => SupportTicketWorkImpact::CannotWork->value,
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame('IT-000001', $ticket->ticket_number);
        $this->assertSame(SupportTicketStatus::Unassigned, $ticket->status);
        $this->assertSame(SupportTicketUrgency::Critical, $ticket->urgency);
        $this->assertSame($user->id, $ticket->requester_user_id);
        $this->assertDatabaseHas('support_ticket_history_events', [
            'support_ticket_id' => $ticket->id,
            'action' => SupportTicketHistoryAction::Created->value,
        ]);
    }

    public function test_ticket_rejects_authentication_secrets(): void
    {
        $dsp = Employee::factory()->dsp()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('support-tickets.store'), [
                'category' => SupportTicketCategory::EmailPasswordAccess->value,
                'subject' => 'Need help signing in',
                'description' => 'My password: hunter2 still fails.',
                'work_impact' => SupportTicketWorkImpact::CanWorkWithProblems->value,
            ])
            ->assertSessionHasErrors('description');

        $this->assertSame(0, SupportTicket::query()->count());
    }

    public function test_lifecycle_claim_resolve_reopen_and_auto_close(): void
    {
        $requester = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $tech = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $tech->forceFill(['it_tech_access' => true])->save();

        $ticket = app(SupportTicketService::class)->create($requester, [
            'category' => SupportTicketCategory::NetworkInternet->value,
            'subject' => 'No internet',
            'description' => 'Cannot reach the office Wi-Fi.',
            'work_impact' => SupportTicketWorkImpact::CannotPerformPart->value,
        ]);

        $this->actingAs($tech)
            ->post(route('support-tickets.claim', $ticket))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatus::InProgress, $ticket->status);
        $this->assertSame($tech->id, $ticket->assigned_user_id);

        $this->actingAs($tech)
            ->post(route('support-tickets.resolve', $ticket), [
                'resolution_note' => 'Reset the wireless adapter.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatus::Resolved, $ticket->status);

        $this->actingAs($requester)
            ->post(route('support-tickets.reply', $ticket), [
                'body' => 'Still offline after reboot.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->resolved_at);

        $this->actingAs($tech)
            ->post(route('support-tickets.resolve', $ticket), [
                'resolution_note' => 'Replaced the network cable.',
            ])
            ->assertRedirect();

        Carbon::setTestNow('2026-10-28 12:05:00');
        $closed = app(SupportTicketService::class)->closeExpiredResolved();
        $this->assertSame(1, $closed);
        $this->assertSame(SupportTicketStatus::Closed, $ticket->fresh()->status);
    }

    public function test_assignment_reassign_takeover_and_revoke_returns_unassigned(): void
    {
        $admin = User::factory()->admin()->create();
        $requester = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $techA = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $techB = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $techA->forceFill(['it_tech_access' => true])->save();
        $techB->forceFill(['it_tech_access' => true])->save();

        $service = app(SupportTicketService::class);
        $ticket = $service->create($requester, [
            'category' => SupportTicketCategory::SoftwareInstallation->value,
            'subject' => 'Need spreadsheet app',
            'description' => 'Cannot open agency templates.',
            'work_impact' => SupportTicketWorkImpact::CanWorkWithProblems->value,
        ]);

        $service->claim($techA, $ticket);
        $this->actingAs($techA)
            ->post(route('support-tickets.reassign', $ticket), [
                'assigned_user_id' => $techB->id,
            ])
            ->assertRedirect();

        $this->assertSame($techB->id, $ticket->fresh()->assigned_user_id);

        $this->actingAs($techA)
            ->post(route('support-tickets.takeover', $ticket))
            ->assertRedirect();
        $this->assertSame($techA->id, $ticket->fresh()->assigned_user_id);

        app(ItTechAccessService::class)->revoke($admin, $techA);
        $ticket->refresh();
        $this->assertFalse($techA->fresh()->hasItTechAccess());
        $this->assertNull($ticket->assigned_user_id);
        $this->assertSame(SupportTicketStatus::Unassigned, $ticket->status);
    }

    public function test_privacy_and_it_tech_authorization_isolation(): void
    {
        $admin = User::factory()->admin()->create();
        $requester = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $otherDsp = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $tech = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $tech->forceFill(['it_tech_access' => true])->save();

        $ticket = app(SupportTicketService::class)->create($requester, [
            'category' => SupportTicketCategory::Other->value,
            'subject' => 'Printer jam',
            'description' => 'Paper tray is stuck closed.',
            'work_impact' => SupportTicketWorkImpact::CanWorkNormally->value,
        ]);

        $this->actingAs($otherDsp)
            ->get(route('support-tickets.show', $ticket))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('support-tickets.show', $ticket))
            ->assertForbidden();

        $this->actingAs($tech)
            ->get(route('support-tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('support-tickets/show')
                ->where('ticket.summary_only', true)
                ->where('ticket.description', null)
                ->where('can.claim', true));

        $this->actingAs($admin)
            ->get(route('support-tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ticket.summary_only', false)
                ->where('ticket.description', 'Paper tray is stuck closed.'));

        // IT/Tech does not gain clinical or messaging authority.
        $client = Client::factory()->create();
        $this->actingAs($tech)->get(route('clients.show', $client))->assertForbidden();
        $this->actingAs($tech)->get(route('messages.index'))->assertOk();
        $this->assertSame(Role::Dsp, $tech->role);
        $this->assertTrue($tech->hasItTechAccess());
        $this->assertFalse($tech->isAdmin());
        $this->assertFalse($tech->isSupervisor());
    }

    public function test_attachments_and_notifications_for_ticket_events(): void
    {
        Storage::fake('local');

        $requester = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $tech = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $tech->forceFill(['it_tech_access' => true])->save();

        $file = UploadedFile::fake()->image('screen.png', 80, 80);

        $this->actingAs($requester)
            ->post(route('support-tickets.store'), [
                'category' => SupportTicketCategory::HardwareDevice->value,
                'subject' => 'Broken webcam',
                'description' => 'Camera shows a black screen.',
                'work_impact' => SupportTicketWorkImpact::CanWorkWithProblems->value,
                'attachment' => $file,
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $attachment = SupportTicketAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->assertTrue(
            InAppNotification::query()
                ->where('user_id', $tech->id)
                ->where('type', InAppNotificationType::SupportTicket)
                ->exists()
        );

        app(SupportTicketService::class)->claim($tech, $ticket);

        $this->actingAs($tech)
            ->get(route('support-ticket-attachments.show', $attachment))
            ->assertOk();

        $this->actingAs($requester)
            ->get(route('support-ticket-attachments.show', $attachment))
            ->assertOk();

        $outsider = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $this->actingAs($outsider)
            ->get(route('support-ticket-attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_admin_can_grant_it_tech_access_on_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->dsp()->create();

        $this->actingAs($admin)
            ->patch(route('employees.it-tech-access', $employee), [
                'enabled' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($employee->user()->firstOrFail()->fresh()->hasItTechAccess());
    }

    public function test_support_tickets_remain_separate_from_clinical_incidents(): void
    {
        $this->assertTrue(class_exists(Incident::class));
        $this->assertTrue(class_exists(EmployeeSecurityIncident::class));
        $this->assertNotSame(SupportTicket::class, Incident::class);
        $this->assertSame('support_tickets', (new SupportTicket)->getTable());
    }
}
