<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function fixturePath(string $name): string
    {
        return 'tests/fixtures/'.$name;
    }
}
