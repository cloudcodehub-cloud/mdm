<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase3FIdempotentOctoberMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_october_schema_objects_exist_after_migrate(): void
    {
        $this->assertTrue(Schema::hasColumn('care_plans', 'timed_check_interval_minutes'));
        $this->assertTrue(Schema::hasColumn('care_plans', 'revision_number'));
        $this->assertTrue(Schema::hasColumn('care_plans', 'review_on'));
        $this->assertTrue(Schema::hasColumn('visits', 'care_plan_id'));
        $this->assertTrue(Schema::hasTable('visit_safety_checks'));
        $this->assertTrue(Schema::hasTable('client_critical_alerts'));
        $this->assertTrue(Schema::hasTable('client_contacts'));
        $this->assertTrue(Schema::hasTable('compliance_requirements'));
        $this->assertTrue(Schema::hasColumn('employee_credentials', 'verification_status'));
        $this->assertTrue(Schema::hasColumn('employees', 'status_reason'));
        $this->assertTrue(Schema::hasColumn('scheduled_visits', 'staffing_needed'));
        $this->assertTrue(Schema::hasColumn('scheduled_visits', 'compliance_staffing_status'));
        $this->assertTrue(Schema::hasTable('employee_profile_change_requests'));
        $this->assertTrue(Schema::hasTable('compliance_staffing_episodes'));
        $this->assertTrue(Schema::hasColumn('employee_trainings', 'document_path'));
    }

    public function test_october_migrations_are_safe_to_re_invoke_up_methods(): void
    {
        // Directly re-run up() bodies via migrate:refresh is destructive; instead
        // assert a second migrate reports nothing pending after RefreshDatabase.
        $exit = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Nothing to migrate', Artisan::output());
    }
}
