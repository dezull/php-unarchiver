<?php

namespace Dezull\Unarchiver\Process;

use Dezull\Unarchiver\Exception\TimeoutException;
use IteratorAggregate;
use LogicException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process as SymfonyProcess;
use Traversable;

/**
 * @implements IteratorAggregate<mixed,mixed>
 */
class Process implements IteratorAggregate
{
    public const ERR = SymfonyProcess::ERR;

    public const OUT = SymfonyProcess::OUT;

    private SymfonyProcess $process;

    private InputStream $input;

    private bool $started = false;

    private bool $ended = false;

    public function __construct(string $executable, ?int $timeout = null, string ...$args)
    {
        $this->input = new InputStream;
        $this->process = new SymfonyProcess([$executable, ...$args]);
        $this->process->setInput($this->input);
        $this->process->setTimeout($timeout);
        $this->process->setIdleTimeout(3600);
    }

    public function start(): static
    {
        if ($this->started) {
            throw new RuntimeException('Process already started');
        }
        $this->process->start();
        $this->started = true;

        return $this;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function end(): static
    {
        if (! $this->started) {
            throw new RuntimeException('Process not yet started');
        }

        if (! $this->ended) {
            $this->input->close();
            try {
                $this->process->wait();
            } catch (ProcessTimedOutException $e) {
                throw new TimeoutException(previous: $e);
            }
            $this->ended = true;
        }

        return $this;
    }

    /**
     * Returns an iterator to the output of the process.
     *
     * @see SymfonyProcess::getIterator()
     *
     * @return Traversable<string,string>
     */
    public function getIterator(): Traversable
    {
        if (! $this->started) {
            throw new LogicException('Must call start() first');
        }

        try {
            yield from $this->process->getIterator();
        } catch (ProcessTimedOutException $e) {
            throw new TimeoutException(previous: $e);
        }
    }

    public function write(string $string): void
    {
        if (! $this->started || $this->ended) {
            throw new RuntimeException('Process not started or already ended');
        }
        $this->input->write($string);
    }

    public function isSuccessful(): bool
    {
        return $this->process->isSuccessful();
    }
}
