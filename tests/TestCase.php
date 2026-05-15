<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dashboard layout uses @vite(); CI and fresh clones have no public/build/manifest.json.
        $this->withoutVite();
    }
}
