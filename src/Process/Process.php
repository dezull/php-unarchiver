<?php

namespace Dezull\Unarchiver\Process;

use Generator;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process as SymfonyProcess;

class Process
{
    private SymfonyProcess $process;
    private InputStream $input;
    private bool $started = false;
    private bool $ended = false;

    public function __construct($executable, ...$args)
    {
        $this->input = new InputStream();
        $this->process = new SymfonyProcess(array($executable, ...$args));
        $this->process->setInput($this->input);
        $this->process->setTimeout(null);
        $this->process->setIdleTimeout(3600);
    }

    public function start(): self
    {
        if ($this->started) throw new RuntimeException('Process already started');
        $this->process->start();
        $this->started = true;

        return $this;
    }

    public function end(): self
    {
        if (! $this->started) throw new RuntimeException('Process not yet started');
        $this->input->close();
        $this->process->wait();
        $this->ended = true;

        return $this;
    }

    public function getIterator(): Generator
    {
        yield from $this->process->getIterator();
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
