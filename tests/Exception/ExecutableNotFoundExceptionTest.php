<?php

namespace Tests\Exception;

use Dezull\Unarchiver\Exception\ExecutableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ExecutableNotFoundException::class)]
class ExecutableNotFoundExceptionTest extends TestCase
{
    public function test_get_message(): void
    {
        $e = new ExecutableNotFoundException(['/foo', '/bar']);

        $this->assertSame(
            'At least any of these should be available: [/foo, /bar]',
            $e->getMessage()
        );
    }
}
