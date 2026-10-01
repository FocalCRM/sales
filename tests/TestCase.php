<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Core\CoreServiceProvider;
use Focal\Sales\SalesServiceProvider;
use Focal\Sales\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\after_resolving;
use function Orchestra\Testbench\default_migration_path;

abstract class TestCase extends Orchestra
{
    /**
     * Boots sales with only its required dependency (core).
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            SalesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    /**
     * Laravel's own migrations (users, cache, jobs). Registered on the migrator rather than
     * run and rolled back per test: RefreshDatabase owns the schema, and rolling back
     * users fails on databases that enforce foreign keys (PostgreSQL, MySQL).
     */
    protected function defineDatabaseMigrations(): void
    {
        after_resolving($this->app, 'migrator', static function ($migrator): void {
            $migrator->path(default_migration_path());
        });
    }
}
