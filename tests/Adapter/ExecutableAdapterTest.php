<?php

namespace Tests\Adapter;

use Dezull\Unarchiver\Adapter\ExecutableAdapter;
use Dezull\Unarchiver\Exception\ExecutableNotFoundException;
use Dezull\Unarchiver\Exception\TimeoutException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(ExecutableAdapter::class)]
class ExecutableAdapterTest extends TestCase
{
    public function test_set_executable_to_valid_path(): void
    {
        $this->expectNotToPerformAssertions();

        new Executable(executable: PHP_BINARY);
    }

    public function test_cannot_set_executable_to_invalid_path(): void
    {
        $this->expectException(ExecutableNotFoundException::class);

        new Executable(executable: '/wrong');
    }

    public function test_set_timeout(): void
    {
        $this->expectException(TimeoutException::class);

        $executable = new Executable(script: 'sleep(5);');

        $executable->setTimeout(1);

        $executable->start()->wait();
    }

    public function test_clean_up_ends_execution(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Process not started or already ended');

        $executable = (new Executable(script: "echo 'hello';"));
        $executable->start();

        $executable->cleanUp();

        $executable->throwIfNotRunning();
    }

    public function test_without_clean_up(): void
    {
        $this->expectNotToPerformAssertions();

        $executable = (new Executable(script: "echo 'hello';"));
        $executable->start();

        $executable->throwIfNotRunning();
    }
}
