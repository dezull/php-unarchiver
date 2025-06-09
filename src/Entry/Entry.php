<?php

namespace Dezull\Unarchiver\Entry;

use DateTime;
use Override;

class Entry extends AbstractEntry
{
    protected string $path;

    protected ?int $packedSize = null;

    protected ?int $size = null;

    protected bool $directory;

    protected ?string $crc = null;

    protected ?DateTime $modificationTime = null;

    #[Override]
    public function isDirectory(): bool
    {
        return $this->directory;
    }

    #[Override]
    public function getPath(): string
    {
        return $this->path;
    }

    #[Override]
    public function getSize(): ?int
    {
        return $this->size;
    }

    #[Override]
    public function getPackedSize(): ?int
    {
        return $this->packedSize;
    }

    #[Override]
    public function getCrc(): ?string
    {
        return $this->crc;
    }

    #[Override]
    public function getModificationTime(): ?DateTime
    {
        return $this->modificationTime;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function setPackedSize(int $packedSize): static
    {
        $this->packedSize = $packedSize;

        return $this;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function setDirectory(bool $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function setCrc(string $crc): static
    {
        $this->crc = $crc;

        return $this;
    }

    public function setModificationTime(DateTime|string $modificationTime): static
    {
        if ($modificationTime instanceof DateTime) {
            $this->modificationTime = $modificationTime;
        } else {
            $this->modificationTime = DateTime::createFromFormat('U', $modificationTime);
        }

        return $this;
    }
}
