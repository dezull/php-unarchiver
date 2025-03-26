<?php

namespace Dezull\Unarchiver\Adapter;

use Dezull\Unarchiver\Exception\ExecutableNotFoundException;
use Dezull\Unarchiver\Process\Process;
use Override;
use RuntimeException;

abstract class ExecutableAdapter implements AdapterInterface
{
    private ?string $executable = null;

    private ?int $timeout = null;

    private ?Process $process = null;

    #[Override]
    public function cleanUp(): void
    {
        if (isset($this->process)) {
            $this->process->end();
        }
    }

    public function createProcess(string ...$args): Process
    {
        return $this->process = (new Process($this->getExecutable(), $this->timeout, ...$args));
    }

    /** Set executable from any of the files */
    public function setExecutable(...$files): static
    {
        $this->executable = $this->ensureAnyExecutable($files);

        return $this;
    }

    public function setTimeout(?int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    private function ensureAnyExecutable(array $files): ?string
    {
        foreach ($files as $file) {
            if (file_exists($file) && is_file($file)) return $file;
        }

        throw new ExecutableNotFoundException($files);
    }

    private function getExecutable(): string
    {
        if ($this->executable !== null) return $this->executable;
        
        throw new RuntimeException('$executable property not set');
    }
}
