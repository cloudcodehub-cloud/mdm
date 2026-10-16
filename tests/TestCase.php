<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use Tests\Support\PersistentDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // phpunit.xml force=true is not always honored by `artisan test` when the
        // parent shell exports DB_*. Pin isolation before Laravel boots.
        self::forceIsolatedSqliteEnvironment();

        PersistentDatabaseGuard::assertEnvironmentIsSafe();

        $app = parent::createApplication();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.driver', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.url', null);
        DB::purge();

        PersistentDatabaseGuard::assertApplicationConnectionIsSafe($app);

        return $app;
    }

    /**
     * Runs before RefreshDatabase migrate:fresh / transactions.
     */
    protected function beforeRefreshingDatabase()
    {
        self::forceIsolatedSqliteEnvironment();

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.driver', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', ':memory:');
        $this->app['config']->set('database.connections.sqlite.url', null);

        PersistentDatabaseGuard::assertEnvironmentIsSafe();
        PersistentDatabaseGuard::assertApplicationConnectionIsSafe($this->app);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    private static function forceIsolatedSqliteEnvironment(): void
    {
        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        putenv('DB_URL=');

        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['DB_URL'] = '';

        $_SERVER['APP_ENV'] = 'testing';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_URL'] = '';
    }
}
