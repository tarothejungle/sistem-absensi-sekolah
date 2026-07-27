<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_contains(strtolower($database), 'test')) {
            throw new RuntimeException("Test dibatalkan: database '{$database}' bukan database testing.");
        }
    }
}
