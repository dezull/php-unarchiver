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

class Bsdtar extends ExecutableAdapter
{
    public function __construct(protected string $filename, protected ?string $password = null)
    {
        $this->setExecutable(
            '/opt/homebrew/opt/libarchive/bin/bsdtar',
            '/usr/local/bin/bsdtar',
            '/usr/bin/bsdtar'
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
        // This can take some time on a large archive file
        return $this->createProcess('-tf', $this->filename, $filename)
            ->start()
            ->end()
            ->isSuccessful();
    }

    protected function extractAndCount(
        string $outputDirectory, ?array $filenames, bool $overwrite): int
    {
        $args = [
            '-vxf',
            $this->filename,
            '-C',
            $outputDirectory,

            // Passphrase can't be empty (space is fine), and ignored if not encrypted
            '--passphrase',
            empty($this->password) ? ' ' : $this->password,
        ];

        if (! $overwrite) {
            $args[] = '--keep-old-files';
        }
        if ($filenames !== null) {
            $args = [...$args, ...$filenames];
        }
        if (! file_exists($outputDirectory)) {
            mkdir($outputDirectory);
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
     * @param  string[]  $lines
     */
    protected function countExtractedFromBuffer(array $lines = []): int
    {
        $extractCount = 0;

        // ...
        // x -rw-r--r--  0 0      0           7 Sep 29 11:27 test.txt
        // x -rw-r--r--  0 0      0           7 Sep 29 11:43 three.txt
        // x -rw-r--r--  0 0      0           5 Apr  3  2021 two.txt.
        // ...
        while (($line = array_shift($lines)) !== null) {
            if (preg_match('/^x.+$/', $line) === 1) {
                $extractCount++;
            }
        }

        return $extractCount;
    }

    protected function matchExtracted(string $line): bool
    {
        return preg_match('/^x.+$/', $line) === 1;
    }

    protected function createBuffer(Process $process): LineBufferedOutput
    {
        return (new LineBufferedOutput($process))
            ->beforeBuffer(function ($output, $fd) {
                if ($fd === Process::ERR) {
                    $this->ensurePassword($output);
                }
            });
    }

    /**
     * @return Generator<Entry>
     */
    protected function getParsedEntries(?string $filename = null): Generator
    {
        $args = ['-vvtf', $this->filename];
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
        // Adapted from https://github.com/alchemy-fr/Zippy/blob/master/src/Parser/BSDTarOutputParser.php
        // -rw-r--r--  0 0      0           7 Sep 29 11:27 test.txt
        // -rw-r--r--  0 0      0           7 Sep 29 11:43 three.txt
        // -rw-r--r--  0 0      0           5 Apr  3  2021 two.txt
        $re = <<<'RE'
            /
            ^(?P<type>.).{9}                                # type and permission
            \s+
            \d+                                             # links
            \s+
            [-a-z0-9]+                                      # owner
            \s+
            [-a-z0-9]+                                      # group
            \s+
            (?P<size>\d*)                                   # size
            \s+
            (?P<time>[a-zA-Z0-9]+\s+[a-z0-9]+\s+[a-z0-9:]+) # date
            \s+
            (?P<name>.+)                                    # filename
            $
            /x
            RE;

        if (preg_match($re, $line, $matches) !== 1) {
            return null;
        }

        $entry = new Entry($this);
        $entry->setPath(rtrim($matches['name'], '/'));
        $entry->setSize(intval($matches['size']));
        $entry->setDirectory($matches['type'] === 'd');
        if (($time = strtotime($matches['time'])) !== false) {
            $entry->setModificationTime($time);
        }

        return $entry;
    }

    protected function ensurePassword(string $buffer): void
    {
        if (str_contains($buffer, 'Incorrect passphrase:')) {
            throw new EncryptionPasswordRequiredException;
        }
    }
}
