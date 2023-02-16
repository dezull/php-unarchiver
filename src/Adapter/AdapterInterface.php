<?php

namespace Dezull\Unarchiver\Adapter;

use Dezull\Unarchiver\Entry\EntryInterface;
use Generator;

interface AdapterInterface
{
    public function getEntries(): Generator;
    public function getEntry(string $filename): EntryInterface;
    public function extract(
        string $outputDirectory, ?array $filenames = null, bool $overwrite = true): int;
    public function setTimeout(?int $seconds): static;
}
