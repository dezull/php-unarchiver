<?php

namespace Dezull\Unarchiver\Exception;

use RuntimeException;

class ExecutableNotFoundException extends RuntimeException
{
    public function __construct($files)
    {
        parent::__construct(
            'At least any of these should be available: ['
            . \implode(', ', $files)
            . ']'
        );
    }
}
