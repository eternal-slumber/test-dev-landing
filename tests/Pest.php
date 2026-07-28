<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PostgresTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(PostgresTestCase::class)->in('Postgres');
