<?php

namespace Tests\Unit;

use App\Enums\ProfileCompletenessStatus;
use App\Services\ProfileCompletionService;
use Tests\TestCase;

class ProfileCompletenessStatusTest extends TestCase
{
    public function test_percent_bands_match_the_agreed_thresholds(): void
    {
        $this->assertSame(ProfileCompletenessStatus::Critical, ProfileCompletenessStatus::fromPercent(0));
        $this->assertSame(ProfileCompletenessStatus::Critical, ProfileCompletenessStatus::fromPercent(39));
        $this->assertSame(ProfileCompletenessStatus::Attention, ProfileCompletenessStatus::fromPercent(40));
        $this->assertSame(ProfileCompletenessStatus::Attention, ProfileCompletenessStatus::fromPercent(69));
        $this->assertSame(ProfileCompletenessStatus::Healthy, ProfileCompletenessStatus::fromPercent(70));
        $this->assertSame(ProfileCompletenessStatus::Healthy, ProfileCompletenessStatus::fromPercent(100));
    }

    public function test_completion_service_exposes_reusable_status_payload(): void
    {
        $payload = app(ProfileCompletionService::class)->statusForPercent(38);

        $this->assertSame('critical', $payload['status']);
        $this->assertSame('danger', $payload['tone']);
        $this->assertSame('Critical', $payload['label']);
    }
}
