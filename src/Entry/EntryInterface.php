<?php

namespace Dezull\Unarchiver\Entry;

use DateTime;

interface EntryInterface
{
    public function isDirectory(): bool;
    public function getPath(): string;
    public function getSize(): ?int;
    public function getPackedSize(): ?int;
    public function getModificationTime(): ?DateTime;
    public function getCrc(): ?string;
    public function extract($outputDirectory = null, $overwrite = true): void;
}
