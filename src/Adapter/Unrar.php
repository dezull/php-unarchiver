<?php

namespace Dezull\Unarchiver\Adapter;

use Dezull\Unarchiver\Entry\Entry;
use Dezull\Unarchiver\Entry\EntryInterface;
use Dezull\Unarchiver\Exception\EncryptionPasswordRequiredException;
use Dezull\Unarchiver\Exception\EntryNotFoundException;
use Dezull\Unarchiver\Process\LineBuffer;
use Dezull\Unarchiver\Process\Process;
use Dezull\Unarchiver\Utils;
use Generator;

class Unrar extends ExecutableAdapter
{
    protected string $filename;
    /** @var string */
    protected $password;

    private LineBuffer $lineBuffer;

    /** @var EntryInterface */
    private $currentEntry;

    public function __construct($filename, $password) {
        $this->filename = $filename;
        $this->password = $password;
        $this->setExecutable(
            '/usr/local/bin/unrar',
            '/usr/bin/unrar'
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
        $bufferAll = '';
        // This can take some time on a large RAR file
        $process = $this->createProcess('vt', '-@', $this->filename, $filename);
        foreach ($process->start()->getIterator() as $buffer) {
            $bufferAll .= $buffer;
        }
        $process->end();

        return str_contains($bufferAll, $filename);
    }

    protected function extractAndCount(
        string $outputDirectory, ?array $filenames, bool $overwrite): int
    {
        $overwriteArg = $overwrite ? "-o+" : "-o-";
        $passwordArg = '-p'.($this->password ?? '0');
        $args = ['x', '-y', $passwordArg, '-@', $overwriteArg, $this->filename];
        if ($filenames !== null) $args = array_merge($args, $filenames);
        $args[] = $outputDirectory . DIRECTORY_SEPARATOR;

        $process = $this->createProcess(...$args);
        $extractAndCount = 0;

        foreach ($process->start()->getIterator() as $buffer) {
            $this->ensurePassword($buffer);

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
        // Extracting from nopassword.rar
        // 
        // Extracting  out/test.txt                                              OK
        // Extracting  out/three.txt                                             OK
        // Extracting  out/two.txt                                               OK
        // ...
        // * The output maybe re-written over *
        while (($line = array_shift($lines)) !== null) {
            if (preg_match('/(All OK)|(^Creating.+OK\s*$)/', $line) === 1) {
                continue;
            } else if (preg_match('/^.+OK\s*$/', $line) === 1) {
                $extractCount++;
            }
        }

        return $extractCount;
    }

    /**
     * @return Generator<Entry>
     */
    protected function getParsedEntries(?string $filename = null): Generator
    {
        $passwordArg = '-p'.($this->password ?? '0');
        $args = ["vta", $passwordArg, $this->filename];
        if ($filename) $args[] = $filename;

        $this->lineBuffer = new LineBuffer;
        $process = $this->createProcess(...$args);
        foreach ($process->start()->getIterator() as $buffer) {
            $this->ensurePassword($buffer);
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
        if (preg_match('/^\s*Name: (?P<name>.+)$/', $line, $matches) === 1) {
            $this->currentEntry = new Entry($this);
            $this->currentEntry->setPath($matches["name"]);
        }

        if ($this->currentEntry) {
            if (preg_match('/^\s*Type: (?P<type>.+)$/', $line, $matches) === 1) {
                $type = $matches['type'];
                if ($type === 'Service') {
                    $this->currentEntry = null;
                    return;
                }
                $this->currentEntry->setDirectory($type === 'Directory');
            } else if (preg_match(
                '/^\s*Packed size: (?P<packed_size>.+)$/', $line, $matches) === 1) {

                $this->currentEntry->setPackedSize(intval($matches["packed_size"]));
            } else if (preg_match('/^\s*Size: (?P<size>.+)$/', $line, $matches) === 1) {
                $this->currentEntry->setSize(intval($matches["size"]));
            } else if (preg_match('/^\s*mtime: (?P<mtime>.+),.+$/', $line, $matches) === 1) {
                $this->currentEntry->setModificationTime(strtotime($matches["mtime"]));
            } else if (preg_match('/^\s*CRC32: (?P<crc>.+)$/', $line, $matches) === 1) {
                $this->currentEntry->setCrc($matches["crc"]);
            } else if (preg_match('/^\s*Compression: RAR.+$/', $line, $matches) === 1) {
                yield $this->currentEntry;

                $this->currentEntry = null;
            }
        }
    }

    protected function ensurePassword($buffer): void
    {
        if (preg_match('/^Incorrect password/m', $buffer) === 1) {
            throw new EncryptionPasswordRequiredException();
        }
    }
}
