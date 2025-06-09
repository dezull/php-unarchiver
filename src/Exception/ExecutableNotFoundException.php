<?php

namespace Dezull\Unarchiver\Exception;

use RuntimeException;

class ExecutableNotFoundException extends RuntimeException
{
    /**
     * @param  string[]  $files
     */
    public function __construct(array $files)
    {
        parent::__construct(
            'At least any of these should be available: ['
            .\implode(', ', $files)
            .']'
        );
    }
}
