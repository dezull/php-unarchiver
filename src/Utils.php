<?php

namespace Dezull\Unarchiver;

class Utils
{
    public static function splitLines(string $str): array
    {
        return preg_split("/\r\n|\n|\r/", $str) ?: [];
    }
}
