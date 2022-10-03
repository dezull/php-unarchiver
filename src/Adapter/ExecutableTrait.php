<?php

namespace Dezull\Unarchiver\Adapter;

use Dezull\Unarchiver\Exception\ExecutableNotFoundException;
use Dezull\Unarchiver\Process\Process;
use RuntimeException;

trait ExecutableTrait
{
    private ?string $executable = null;

    public function createProcess(string ...$args): Process
    {
        return new Process($this->getExecutable(), ...$args);
    }

    /** Set executable from any of the files */
    public function setExecutable(...$files): void
    {
        $this->executable = $this->ensureAnyExecutable($files);
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
