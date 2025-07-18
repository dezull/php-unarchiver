<?php

namespace Dezull\Unarchiver\Adapter;

use DateTime;
use Dezull\Unarchiver\Entry\Entry;
use Dezull\Unarchiver\Entry\EntryInterface;
use Dezull\Unarchiver\Exception\EncryptionPasswordRequiredException;
use Dezull\Unarchiver\Process\LineBufferedOutput;
use Dezull\Unarchiver\Process\Process;
use Generator;
use Iterator;
use Override;

class SevenZip extends ExecutableAdapter
{
    private ?Entry $currentEntry;

    public function __construct(protected string $filename, protected ?string $password = null)
    {
        $this->setExecutable(
            '/usr/local/bin/7z',
            '/usr/bin/7z'
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
        return $this->getParsedEntries($filename)->current();
    }

    /**
     * @return int files extracted. 7z always includes parent folders, even when $overwrite is true.
     */
    #[Override]
    public function extract(string $outputDirectory, ?array $filenames = null, bool $overwrite = true): int
    {
        return $this->extractAndCount($outputDirectory, $filenames, $overwrite);
    }

    protected function extractAndCount(
        string $outputDirectory, ?array $filenames, bool $overwrite): int
    {
        $overwriteArg = $overwrite ? '-aoa' : '-aos';
        $passwordArg = '-p'.$this->passwordArgument();
        $outputArg = '-o'.$outputDirectory;
        $args = ['x', '-y', '-bb1', $outputArg, $overwriteArg, $passwordArg, $this->filename];

        if ($filenames !== null) {
            $args = [...$args, ...$filenames];
        }

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

    /**
     * @return Generator<Entry>
     */
    protected function getParsedEntries(?string $filename = null): Generator
    {
        $args = ['l', '-slt', '-p'.$this->passwordArgument(), $this->filename];
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

    protected function passwordArgument(): string
    {
        return (is_null($this->password) || trim($this->password) === '')
            ? ''
            : $this->password;
    }

    protected function matchExtracted(string $line): bool
    {
        if (preg_match('/^- .+$/', $line) === 1) {
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

    protected function parseEntryFromLine(string $line): ?Entry
    {
        if (preg_match('/^Path = (?P<name>.+)$/', $line, $matches) === 1) {
            $this->currentEntry = new Entry($this);
            $this->currentEntry->setPath(rtrim($matches['name'], '/'));
        }

        if (! isset($this->currentEntry)) {
            return null;
        }

        if (preg_match('/^\s*Type = (?P<type>.+)$/', $line, $matches) === 1) {
            // 7z header, ignore
            $this->currentEntry = null;

            return null;
        } elseif (preg_match('/^\s*Packed Size = (?P<packed_size>.+)$/', $line, $matches) === 1) {
            $this->currentEntry->setPackedSize(intval($matches['packed_size']));
        } elseif (preg_match('/^Size = (?P<size>.+)$/', $line, $matches) === 1) {
            $this->currentEntry->setSize(intval($matches['size']));
        } elseif (preg_match('/^Modified = (?P<mtime>.+)$/', $line, $matches) === 1) {
            if ($date = DateTime::createFromFormat('Y-m-d G:i:s+', $matches['mtime'])) {
                $this->currentEntry->setModificationTime($date);
            }
        } elseif (preg_match('/^Attributes = (?P<type>.).+$/', $line, $matches) === 1) {
            $this->currentEntry->setDirectory($matches['type'] === 'D');
        } elseif (preg_match('/^CRC = (?P<crc>.+)$/', $line, $matches) === 1) {
            $this->currentEntry->setCrc($matches['crc']);
        } elseif (preg_match('/^Block =.*$/', $line, $matches) === 1) {
            $entry = $this->currentEntry;
            $this->currentEntry = null;

            return $entry;
        }

        return null;
    }

    protected function ensurePassword(string $buffer): void
    {
        if (preg_match('/^ERROR: .+ Wrong password?/m', $buffer) === 1) {
            throw new EncryptionPasswordRequiredException;
        }
    }
}
