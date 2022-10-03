<?php

namespace Dezull\Unarchiver\Entry;

use Dezull\Unarchiver\Adapter\AdapterInterface;

abstract class AbstractEntry implements EntryInterface
{
    protected AdapterInterface $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function extract($outputDirectory = null, $overwrite = true): void
    {
        $this->adapter->extract($outputDirectory, [$this->getPath()], $overwrite);
    }

    abstract public function getPath(): string;
}
