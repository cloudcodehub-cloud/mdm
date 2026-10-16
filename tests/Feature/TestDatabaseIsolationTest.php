<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_phpunit_resolves_sqlite_memory_even_under_shell_db_overrides(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }
}
