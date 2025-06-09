<?php

namespace Dezull\Unarchiver;

class Utils
{
    /**
     * @return string[]
     */
    public static function splitLines(string $str): array
    {
        return preg_split("/\r\n|\n|\r/", $str) ?: [];
    }
}
