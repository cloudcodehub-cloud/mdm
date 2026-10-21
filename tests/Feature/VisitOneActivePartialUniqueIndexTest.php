<?php

namespace Tests\Feature;

use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the SQLite partial unique index visits_one_active_per_employee:
 * multiple historical Visits may share employee_id; only one in_progress at a time.
 */
class VisitOneActivePartialUniqueIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_unique_index_sql_is_conditional_on_in_progress(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Partial unique index is SQLite-specific.');
        }

        $sql = DB::table('sqlite_master')
            ->where('type', 'index')
            ->where('name', 'visits_one_active_per_employee')
            ->value('sql');

        $this->assertIsString($sql);
        $this->assertStringContainsString('visits_one_active_per_employee', $sql);
        $this->assertStringContainsString('employee_id', $sql);
        $this->assertMatchesRegularExpression("/where\\s+status\\s*=\\s*'in_progress'/i", $sql);
        $this->assertDoesNotMatchRegularExpression(
            "/create\\s+unique\\s+index\\s+visits_one_active_per_employee\\s+on\\s+visits\\s*\\(\\s*employee_id\\s*\\)\\s*$/i",
            trim($sql),
        );
    }

    public function test_multiple_completed_visits_share_employee_while_second_in_progress_is_rejected(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Partial unique index is SQLite-specific.');
        }

        $employee = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        $firstScheduled = ScheduledVisit::factory()->create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
        ]);
        $secondScheduled = ScheduledVisit::factory()->create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
        ]);
        $thirdScheduled = ScheduledVisit::factory()->create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
        ]);
        $fourthScheduled = ScheduledVisit::factory()->create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
        ]);

        Visit::factory()->forScheduledVisit($firstScheduled)->create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
            'status' => VisitStatus::Completed,
            'clocked_in_at' => now()->subHours(6),
            'clocked_out_at' => now()->subHours(4),
        ]);
        Visit::factory()->forScheduledVisit($secondScheduled)->create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
            'status' => VisitStatus::Completed,
            'clocked_in_at' => now()->subHours(3),
            'clocked_out_at' => now()->subHours(2),
        ]);

        $this->assertSame(2, Visit::query()->where('employee_id', $employee->id)->count());

        Visit::factory()->forScheduledVisit($thirdScheduled)->create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now()->subHour(),
            'clocked_out_at' => null,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Visit::factory()->forScheduledVisit($fourthScheduled)->create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now(),
            'clocked_out_at' => null,
        ]);
    }
}
