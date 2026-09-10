<?php

namespace Tests\Feature\Domain;

use App\Models\SkipReason;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkipReasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_requires_a_comment_for_later_skip_capture(): void
    {
        $other = SkipReason::factory()->other()->create();

        $this->assertTrue($other->requires_comment);
        $this->assertSame('other', $other->code);
        $this->assertTrue($other->is_active);
    }

    public function test_skip_reason_codes_are_unique(): void
    {
        SkipReason::factory()->create(['code' => 'client_refused']);

        $this->expectException(QueryException::class);

        SkipReason::factory()->create(['code' => 'client_refused']);
    }
}
