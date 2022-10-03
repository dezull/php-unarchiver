<?php

namespace Dezull\Unarchiver\Entry;

use DateTime;

class Entry extends AbstractEntry
{
    protected string $path;
    protected ?string $packedSize = null;
    protected ?string $size = null;
    protected string $directory;
    protected ?string $crc = null;
    protected ?DateTime $modificationTime = null;

    public function isDirectory(): bool
    {
        return $this->directory;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getPackedSize(): ?int
    {
        return $this->packedSize;
    }

    public function getCrc(): ?string
    {
        return $this->crc;
    }

    public function getModificationTime(): ?DateTime
    {
        return $this->modificationTime;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function setPackedSize(int $packedSize): void
    {
        $this->packedSize = $packedSize;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function setDirectory(bool $directory): void
    {
        $this->directory = $directory;
    }

    public function setCrc(string $crc): void
    {
        $this->crc = $crc;
    }

    public function setModificationTime(mixed $modificationTime): void
    {
        if ($modificationTime instanceof DateTime) {
            $this->modificationTime = $modificationTime;
        } else {
            $this->modificationTime = DateTime::createFromFormat('U', $modificationTime);
        }
    }
}
