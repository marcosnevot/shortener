<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind de Metrics fake para no tocar Redis en tests
        $this->app->instance(\App\Services\MetricsContract::class, new \App\Services\MetricsFake());
    }
}
