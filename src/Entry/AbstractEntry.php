<?php

namespace Dezull\Unarchiver\Entry;

use Dezull\Unarchiver\Adapter\AdapterInterface;
use Override;

abstract class AbstractEntry implements EntryInterface
{
    public function __construct(protected AdapterInterface $adapter) {}

    #[Override]
    public function extract(string $outputDirectory, bool $overwrite = true): void
    {
        $this->adapter->extract($outputDirectory, [$this->getPath()], $overwrite);
    }

    #[Override]
    abstract public function getPath(): string;
}
