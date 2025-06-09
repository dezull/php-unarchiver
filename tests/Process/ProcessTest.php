<?php

namespace Tests\Process;

use Dezull\Unarchiver\Exception\TimeoutException;
use Dezull\Unarchiver\Process\Process;
use LogicException;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(Process::class)]
class ProcessTest extends TestCase
{
    private Process $process;

    #[After]
    protected function cleanUpProcess(): void
    {
        if ($this->process->started()) {
            $this->process->end();
        }
    }

    public function test_run_process_successfully(): void
    {
        $process = $this->createProcess(null, '-r', <<<'SCRIPT'
            echo "hello\nworld";
        SCRIPT);

        $process->start()->end();

        $this->assertTrue($process->isSuccessful());
    }

    public function test_start_cannot_be_called_more_than_once(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Process already started');

        $process = $this->createProcess(null, '-r', <<<'SCRIPT'
            echo "hello\nworld";
        SCRIPT);

        $process->start()->start();
    }

    public function test_end_cannot_be_called_before_started(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Process not yet started');

        $process = $this->createProcess(null, '-r', <<<'SCRIPT'
            echo "hello\nworld";
        SCRIPT);

        $process->end();
    }

    public function test_get_iterator_yields_output(): void
    {
        $process = $this->createProcess(null, '-r', <<<'SCRIPT'
            echo "hello\nworld";
        SCRIPT);
        $process->start();

        $output = iterator_to_array($process->getIterator());

        $this->assertSame(['out' => "hello\nworld"], $output);
    }

    public function test_get_iterator_cannot_be_called_before_start(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Must call start() first');

        $process = $this->createProcess(null, '-r', <<<'SCRIPT'
            echo "hello\nworld";
        SCRIPT);

        $output = iterator_to_array($process->getIterator());

        $this->assertSame(['out' => "hello\nworld"], $output);
    }

    public function test_timeout_when_reading(): void
    {
        $process = $this->createProcess(1, '-r', <<<'SCRIPT'
            foreach (range(1,5) as $i) {
                echo "$i\n";
                sleep(1);
            }
        SCRIPT);

        $process->start();

        $outputs = [];
        try {
            foreach ($process as $output) {
                $outputs[] = $output;
            }
        } catch (TimeoutException $e) {
        }

        $this->assertLessThan(5, count($outputs));
    }

    public function test_timeout_when_ending(): void
    {
        $this->expectException(TimeoutException::class);

        $process = $this->createProcess(1, '-r', <<<'SCRIPT'
            foreach (range(1,5) as $i) {
                echo "$i\n";
                sleep(1);
            }
        SCRIPT);

        $process->start()->end();
    }

    public function test_write_to_process(): void
    {
        $process = $this->createProcess(null, '-r', <<<'SCRIPT'
            echo "hello ".fgets(fopen("php://stdin", "w"));
        SCRIPT);
        $process->start();

        $process->write("world\n");
        $output = iterator_to_array($process);

        $this->assertSame(['out' => "hello world\n"], $output);
    }

    public function test_write_cannot_be_to_unstarted_processed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Process not started or already ended');

        $process = $this->createProcess(null, '-r', <<<'SCRIPT'
            echo "hello ".fgets(fopen("php://stdin", "w"));
        SCRIPT);

        $process->write("world\n");
    }

    public function test_write_cannot_be_to_ended_processed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Process not started or already ended');

        $process = $this->createProcess(null, '-r', <<<'SCRIPT'
            echo "hello ".fgets(fopen("php://stdin", "w"));
        SCRIPT);
        $process->start()->end();

        $process->write("world\n");
    }

    private function createProcess(?int $timeout = null, string ...$args): Process
    {
        $this->process = new Process(PHP_BINARY, $timeout, ...$args);

        return $this->process;
    }
}
