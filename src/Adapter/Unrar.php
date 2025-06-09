<?php

namespace Dezull\Unarchiver\Adapter;

use Dezull\Unarchiver\Entry\Entry;
use Dezull\Unarchiver\Entry\EntryInterface;
use Dezull\Unarchiver\Exception\EncryptionPasswordRequiredException;
use Dezull\Unarchiver\Process\LineBufferedOutput;
use Dezull\Unarchiver\Process\Process;
use Generator;
use Iterator;
use Override;

class Unrar extends ExecutableAdapter
{
    private ?Entry $currentEntry;

    public function __construct(protected string $filename, protected ?string $password = null)
    {
        $this->setExecutable(
            '/usr/local/bin/unrar',
            '/usr/bin/unrar'
        );
    }

    #[Override]
    public function getEntries(): Iterator
    {
        yield from $this->getParsedEntries();
    }

    #[Override]
    public function getEntry(string $filename): ?EntryInterface
    {
        return $this->hasEntry($filename)
            ? $this->getParsedEntries($filename)->current()
            : null;
    }

    #[Override]
    public function extract(
        string $outputDirectory, ?array $filenames = null, bool $overwrite = true): int
    {
        return $this->extractAndCount($outputDirectory, $filenames, $overwrite);
    }

    protected function hasEntry(string $filename): bool
    {
        $bufferAll = '';
        $passwordArg = '-p'.$this->passwordArgument();
        $args = ['vt', $passwordArg, '-@', $this->filename, $filename];
        $process = $this->createProcess(...$args);
        $buffer = $this->createBuffer($process->start());

        // This can take some time on a large RAR file
        foreach ($buffer as $line) {
            $bufferAll .= $line;
        }

        $process->end();

        return str_contains($bufferAll, $filename);
    }

    protected function extractAndCount(
        string $outputDirectory, ?array $filenames, bool $overwrite): int
    {
        $overwriteArg = $overwrite ? '-o+' : '-o-';
        $passwordArg = '-p'.$this->passwordArgument();
        $args = ['x', '-y', $passwordArg, '-@', $overwriteArg, $this->filename];

        if ($filenames !== null) {
            $args = [...$args, ...$filenames];
        }

        $args[] = $outputDirectory.DIRECTORY_SEPARATOR;
        $process = $this->createProcess(...$args);
        $buffer = $this->createBuffer($process->start());
        $extractedCount = 0;

        foreach ($buffer as $line) {
            if ($this->matchExtracted($line)) {
                $extractedCount++;
            }
        }

        $process->end();

        return $extractedCount;
    }

    protected function passwordArgument(): string
    {
        return (is_null($this->password) || trim($this->password) === '')
            ? '0'
            : $this->password;
    }

    protected function matchExtracted(string $line): bool
    {
        if (preg_match('/(All OK)|(^Creating.+OK\s*$)/', $line) === 1) {
            return false;
        } elseif (preg_match('/^.+OK\s*$/', $line) === 1) {
            return true;
        }

        return false;
    }

    protected function createBuffer(Process $process): LineBufferedOutput
    {
        return (new LineBufferedOutput($process))
            ->beforeBuffer(function ($output, $fd) {
                $this->ensurePassword($output);
            });
    }

    /**
     * @return Generator<Entry>
     */
    protected function getParsedEntries(?string $filename = null): Generator
    {
        $args = ['vta', '-p'.$this->passwordArgument(), $this->filename];
        if ($filename) {
            $args[] = $filename;
        }

        $process = $this->createProcess(...$args);
        $buffer = $this->createBuffer($process->start());

        foreach ($buffer as $line) {
            if ($entry = $this->parseEntryFromLine($line)) {
                yield $entry;
            }
        }

        $process->end();
    }

    /**
     * @param  string[]  $lines
     * @return Generator<Entry>
     */
    protected function parseEntriesFromBuffer(array $lines = []): Generator
    {
        while (($line = array_shift($lines)) !== null) {
            if ($entry = $this->parseEntryFromLine($line)) {
                yield $entry;
            }
        }
    }

    protected function parseEntryFromLine(string $line): ?Entry
    {
        if (preg_match('/^\s*Name: (?P<name>.+)$/', $line, $matches) === 1) {
            $this->currentEntry = new Entry($this);
            $this->currentEntry->setPath(rtrim($matches['name'], '/'));
        }

        if (! isset($this->currentEntry)) {
            return null;
        }

        if (preg_match('/^\s*Type: (?P<type>.+)$/', $line, $matches) === 1) {
            $type = $matches['type'];
            if ($type === 'Service') {
                $this->currentEntry = null;

                return null;
            }
            $this->currentEntry->setDirectory($type === 'Directory');
        } elseif (preg_match(
            '/^\s*Packed size: (?P<packed_size>.+)$/', $line, $matches) === 1) {

            $this->currentEntry->setPackedSize(intval($matches['packed_size']));
        } elseif (preg_match('/^\s*Size: (?P<size>.+)$/', $line, $matches) === 1) {
            $this->currentEntry->setSize(intval($matches['size']));
        } elseif (preg_match('/^\s*mtime: (?P<mtime>.+),.+$/', $line, $matches) === 1) {
            $this->currentEntry->setModificationTime(strtotime($matches['mtime']));
        } elseif (preg_match('/^\s*CRC32: (?P<crc>.+)$/', $line, $matches) === 1) {
            $this->currentEntry->setCrc($matches['crc']);
        } elseif (preg_match('/^\s*Compression: RAR.+$/', $line, $matches) === 1) {
            $entry = $this->currentEntry;
            $this->currentEntry = null;

            return $entry;
        }

        return null;
    }

    protected function ensurePassword(string $buffer): void
    {
        if (preg_match('/^Incorrect password/m', $buffer) === 1) {
            throw new EncryptionPasswordRequiredException;
        }
    }
}
