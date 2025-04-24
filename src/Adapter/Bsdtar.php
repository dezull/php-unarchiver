<?php

namespace Dezull\Unarchiver\Adapter;

use Dezull\Unarchiver\Entry\Entry;
use Dezull\Unarchiver\Entry\EntryInterface;
use Dezull\Unarchiver\Exception\EncryptionPasswordRequiredException;
use Dezull\Unarchiver\Exception\EntryNotFoundException;
use Dezull\Unarchiver\Process\LineBuffer;
use Dezull\Unarchiver\Utils;
use Generator;
use Symfony\Component\Process\Process;

class Bsdtar extends ExecutableAdapter
{
    protected string $filename;
    /** @var string */
    protected $password;

    private LineBuffer $lineBuffer;

    public function __construct($filename, $password) {
        $this->filename = $filename;
        $this->password = $password;
        $this->setExecutable(
            '/opt/homebrew/opt/libarchive/bin/bsdtar',
            '/usr/local/bin/bsdtar',
            '/usr/bin/bsdtar'
        );
    }

    public function getEntries(): Generator
    {
        yield from $this->getParsedEntries();
    }

    public function getEntry($filename): EntryInterface
    {
        if (! $this->hasEntry($filename)) throw new EntryNotFoundException();

        foreach ($this->getParsedEntries($filename) as $entry) {
            return $entry;
        }
    }

    public function extract(
        string $outputDirectory, ?array $filenames = null, bool $overwrite = true): int
    {
        return $this->extractAndCount($outputDirectory, $filenames, $overwrite);
    }

    protected function hasEntry(string $filename): bool {
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
            $this->password ?? ' ',
        ];
        if (! $overwrite) $args[] = '--keep-old-files';
        if ($filenames !== null) $args = array_merge($args, $filenames);
        if (! file_exists($outputDirectory)) mkdir($outputDirectory);

        $process = $this->createProcess(...$args);
        $extractAndCount = 0;

        foreach ($process->start()->getIterator() as $fd => $buffer) {
            if ($fd === Process::ERR) $this->ensurePassword($buffer);
            $extractAndCount += $this->countExtractedFromBuffer($buffer);
        }

        $process->end();

        return $extractAndCount;
    }

    protected function countExtractedFromBuffer(string $buffer): int
    {
        $lines = Utils::splitLines($buffer);
        $extractCount = 0;

        // ...
        // x -rw-r--r--  0 0      0           7 Sep 29 11:27 test.txt
        // x -rw-r--r--  0 0      0           7 Sep 29 11:43 three.txt
        // x -rw-r--r--  0 0      0           5 Apr  3  2021 two.txt.
        // ...
        while (($line = array_shift($lines)) !== null) {
            if (preg_match('/^x.+$/', $line) === 1) $extractCount++;
        }

        return $extractCount;
    }

    /**
     * @return Generator<Entry>
     */
    protected function getParsedEntries(?string $filename = null): Generator
    {
        $args = ["-vvtf", $this->filename];
        if ($filename) $args[] = $filename;

        $this->lineBuffer = new LineBuffer;
        $process = $this->createProcess(...$args);
        foreach ($process->start()->getIterator() as $fd => $buffer) {
            if ($fd === Process::ERR) $this->ensurePassword($buffer);
            foreach ($this->parseEntriesFromBuffer($buffer) as $entry) {
                yield $entry;
            }
        }

        $process->end();
    }

    /**
     * @return Generator<Entry>
     */
    protected function parseEntriesFromBuffer(string $buffer): Generator
    {
        $lines = $this->lineBuffer->merge($buffer);

        while (($line = array_shift($lines)) !== null) {
            yield from $this->parseEntryFromLine($line);
        }
    }

    /**
     * @return Generator<Entry>
     */
    protected function parseEntryFromLine($line): Generator
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

        if (preg_match($re, $line, $matches) !== 1) return;

        $entry = new Entry($this);
        $entry->setPath($matches['name']);
        $entry->setSize($matches['size']);
        $entry->setDirectory($matches['type'] === 'd');
        if (($time = strtotime($matches['time'])) !== false) {
            $entry->setModificationTime($time);
        }

        yield $entry;
    }

    protected function ensurePassword($buffer): void
    {
        if (str_contains($buffer, 'Incorrect passphrase:')) {
            throw new EncryptionPasswordRequiredException();
        }
    }
}
