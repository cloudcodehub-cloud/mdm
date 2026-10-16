<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\Attributes\UnitTest;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\PersistentDatabaseGuard;

/**
 * Pure guard checks — no application boot, no RefreshDatabase, no real DB I/O.
 */
final class PersistentDatabaseGuardTest extends TestCase
{
    #[UnitTest]
    public function test_allows_sqlite_memory_target(): void
    {
        PersistentDatabaseGuard::assertDatabaseTargetIsSafe('sqlite', ':memory:', '', 'unit');
        PersistentDatabaseGuard::assertDatabaseTargetIsSafe('sqlite', ':memory:', null, 'unit');
        $this->addToAssertionCount(1);
    }

    #[UnitTest]
    public function test_rejects_persistent_sqlite_file_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('persistent database');

        PersistentDatabaseGuard::assertDatabaseTargetIsSafe(
            'sqlite',
            'C:\\Users\\Syed Mustafa Ali\\Herd\\mdm-magic-data-management\\database\\database.sqlite',
            '',
            'simulated unsafe config',
        );
    }

    #[UnitTest]
    public function test_rejects_recovery_backup_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(':memory:');

        PersistentDatabaseGuard::assertDatabaseTargetIsSafe(
            'sqlite',
            'C:\\Users\\Syed Mustafa Ali\\Herd\\mdm-backups\\phase3f_20261016_080719\\database.sqlite.recovery',
            null,
            'simulated unsafe config',
        );
    }

    #[UnitTest]
    public function test_rejects_non_sqlite_connection_even_in_testing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sqlite :memory:');

        PersistentDatabaseGuard::assertDatabaseTargetIsSafe('mysql', 'mdm_testing', null, 'simulated unsafe config');
    }

    #[UnitTest]
    public function test_rejects_persistent_database_url(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_URL');

        PersistentDatabaseGuard::assertDatabaseTargetIsSafe(
            'sqlite',
            ':memory:',
            'sqlite://'.str_replace('\\', '/', 'C:/tmp/database.sqlite'),
            'simulated unsafe config',
        );
    }
}
