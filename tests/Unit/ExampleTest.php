<?php

namespace Tests\Unit;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_application_runs_in_testing_environment(): void
    {
        $this->assertSame('testing', app()->environment());
    }
}
