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
    public function setTimeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    #[Override]
    public function cleanUp(): void
    {
        if (isset($this->process)) {
            $this->process->end();
        }
    }

    /**
     * Set executable from any of the files
     */
    protected function setExecutable(string ...$paths): static
    {
        $this->executable = $this->ensureAnyExecutable($paths);

        return $this;
    }

    protected function createProcess(string ...$args): Process
    {
        return $this->process = (new Process($this->getExecutable(), $this->timeout, ...$args));
    }

    /**
     * @param  string[]  $paths
     */
    private function ensureAnyExecutable(array $paths): string
    {
        foreach ($paths as $path) {
            if (file_exists($path) && is_file($path)) {
                return $path;
            }
        }

        throw new ExecutableNotFoundException($paths);
    }

    private function getExecutable(): string
    {
        if (isset($this->executable)) {
            return $this->executable;
        }

        throw new RuntimeException('$executable property not set');
    }
}
