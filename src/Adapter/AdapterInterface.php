<?php

namespace Dezull\Unarchiver\Adapter;

use Dezull\Unarchiver\Entry\EntryInterface;
use Iterator;

interface AdapterInterface
{
    /**
     * @return Iterator<EntryInterface>
     */
    public function getEntries(): Iterator;

    public function getEntry(string $filename): ?EntryInterface;

    public function extract(
        string $outputDirectory, ?array $filenames = null, bool $overwrite = true): int;

    public function setTimeout(int $seconds): static;

    public function cleanUp(): void;
}
