<?php

namespace Tests\Support;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Fail-closed guard so PHPUnit / RefreshDatabase never touch the persistent
 * MDM demo SQLite file or external recovery backups.
 */
final class PersistentDatabaseGuard
{
    /**
     * @throws RuntimeException
     */
    public static function assertEnvironmentIsSafe(): void
    {
        $connection = self::env('DB_CONNECTION') ?? 'sqlite';
        $database = self::env('DB_DATABASE');
        $url = self::env('DB_URL');

        self::assertDatabaseTargetIsSafe($connection, $database, $url, 'environment');
    }

    /**
     * @throws RuntimeException
     */
    public static function assertApplicationConnectionIsSafe(Application $app): void
    {
        $config = $app['config'];
        $default = (string) $config->get('database.default', 'sqlite');
        $connection = (array) $config->get("database.connections.{$default}", []);

        self::assertDatabaseTargetIsSafe(
            (string) ($connection['driver'] ?? $default),
            isset($connection['database']) ? (string) $connection['database'] : null,
            isset($connection['url']) ? (string) $connection['url'] : null,
            'resolved application connection',
        );
    }

    /**
     * @throws RuntimeException
     */
    public static function assertDatabaseTargetIsSafe(
        string $connection,
        ?string $database,
        ?string $url,
        string $source,
    ): void {
        $connection = strtolower(trim($connection));
        $database = self::normalize($database);
        $url = self::normalize($url);

        if ($url !== null && $url !== '') {
            if (self::urlLooksPersistent($url)) {
                throw self::blocked("{$source} DB_URL points at a persistent database ({$url}).");
            }
        }

        if ($connection !== 'sqlite') {
            throw self::blocked("{$source} DB connection [{$connection}] is not the isolated sqlite :memory: test target.");
        }

        if ($database !== ':memory:') {
            $label = $database === null || $database === '' ? '(empty)' : $database;
            throw self::blocked("{$source} DB database must be :memory:, got {$label}.");
        }
    }

    private static function urlLooksPersistent(string $url): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return false;
        }

        // Empty URL is allowed (phpunit clears DB_URL). In-memory DSN forms are OK.
        if (preg_match('/^sqlite:(:memory:)?$/i', $trimmed) === 1) {
            return false;
        }

        if (stripos($trimmed, ':memory:') !== false && stripos($trimmed, 'sqlite') !== false) {
            // e.g. sqlite:///:memory:
            if (preg_match('/sqlite:\/{0,3}:memory:/i', $trimmed) === 1) {
                return false;
            }
        }

        return true;
    }

    private static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? '' : $trimmed;
    }

    private static function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return null;
        }

        return (string) $value;
    }

    private static function blocked(string $detail): RuntimeException
    {
        return new RuntimeException(
            'Refusing to run tests against a persistent database. '
            .$detail
            .' PHPUnit must use sqlite :memory: only. '
            .'Unset DB_DATABASE / DB_URL shell overrides (phpunit.xml forces :memory:).'
        );
    }
}
