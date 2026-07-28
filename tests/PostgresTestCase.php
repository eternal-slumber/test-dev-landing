<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

abstract class PostgresTestCase extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        if (
            $connection->getDriverName() !== 'pgsql'
            || ! Str::endsWith($database, ['_test', '_integration'])
        ) {
            throw new LogicException(
                'PostgreSQL integration tests require a dedicated *_test or *_integration database.',
            );
        }
    }
}
