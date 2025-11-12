<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_is_ok()
    {
        $this->get('/health')->assertOk();
    }
}
