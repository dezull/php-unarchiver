<?php

namespace Dezull\Unarchiver\Process;

use Generator;
use RuntimeException;
use Dezull\Unarchiver\Utils;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Buffers unterminated line.
 */
class LineBuffer
{
    /**
     * @var string
     */
    private $unterminated;

    /**
     * Get a line terminated content from $content.
     *
     * If the last line of $content is not terminated by a line separator, buffer the last
     * line. Then, return an array of lines from the terminated part of $content, the first line
     * is prepended with the previously buffered content.
     */
    public function merge(string $content): array
    {
        $lines = Utils::splitLines($content);

        // Append the parse buffer (unterminated line) to the first line in the buffer
        if (isset($this->unterminated) && count($lines) > 0) {
            $lines[0] = $this->unterminated . $lines[0];
        }

        // If the last line isn't terminated, move it to the parse buffer
        $this->unterminated = end($lines) !== '' ? array_pop($lines) : null;

        return $lines;
    }
}
