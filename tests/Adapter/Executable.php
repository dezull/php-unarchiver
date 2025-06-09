<?php

namespace Tests\Adapter;

use Dezull\Unarchiver\Adapter\ExecutableAdapter;
use Dezull\Unarchiver\Entry\EntryInterface;
use Dezull\Unarchiver\Process\Process;
use EmptyIterator;
use Iterator;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class Executable extends ExecutableAdapter
{
    private TemporaryDirectory $scriptDir;

    private string $scriptPath;

    private Process $process;

    public function __construct(?string $executable = PHP_BINARY, ?string $script = '')
    {
        $this->scriptDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $this->setExecutable($executable);
        $this->scriptPath = $this->makeScript($script);
    }

    public function getEntries(): Iterator
    {
        return new EmptyIterator;
    }

    public function getEntry(string $filename): ?EntryInterface
    {
        return null;
    }

    public function extract(string $outputDirectory, ?array $filenames = null, bool $overwrite = true): int
    {
        return 0;
    }

    public function start(): static
    {
        $this->process = $this->createProcess('-f', $this->scriptPath)->start();

        return $this;
    }

    public function wait(): void
    {
        iterator_to_array($this->process->getIterator());
    }

    public function throwIfNotRunning(): void
    {
        $this->process->write('test');
    }

    private function makeScript(?string $code = null): string
    {
        $script = $this->scriptDir->path('script.php');

        file_put_contents($script, "<?php {$code}");

        return $script;
    }
}
