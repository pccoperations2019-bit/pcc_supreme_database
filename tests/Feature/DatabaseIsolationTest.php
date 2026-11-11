<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_the_test_suite_runs_against_the_testing_database(): void
    {
        $database = DB::connection()->getDatabaseName();

        $report = sprintf(
            'config=%s getenv=%s _ENV=%s _SERVER=%s app_env=%s',
            $database,
            var_export(getenv('DB_DATABASE'), true),
            var_export(Env::get('DB_DATABASE'), true),
            var_export(Request::server('DB_DATABASE') ?? null, true),
            app()->environment(),
        );

        $this->assertSame('testing', $database, $report);
    }
}
