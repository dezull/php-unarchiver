<?php

namespace Tests;

use Dezull\Unarchiver\Utils;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Utils::class)]
class UtilsTest extends TestCase
{
    public function test_split_line_lf(): void
    {
        $this->assertSame(['abc', 'def'], Utils::splitLines("abc\ndef"));
    }

    public function test_split_line_crlf(): void
    {
        $this->assertSame(['abc', 'def'], Utils::splitLines("abc\r\ndef"));
    }

    public function test_split_line_cr(): void
    {
        $this->assertSame(['abc', 'def'], Utils::splitLines("abc\rdef"));
    }

    public function test_split_unterminated_line(): void
    {
        $this->assertSame(['abc'], Utils::splitLines('abc'));
    }

    public function test_split_line_terminated_with_lf(): void
    {
        $this->assertSame(['abc', ''], Utils::splitLines("abc\n"));
    }

    public function test_split_line_terminated_with_crlf(): void
    {
        $this->assertSame(['abc', ''], Utils::splitLines("abc\r\n"));
    }

    public function test_split_line_terminated_with_cr(): void
    {
        $this->assertSame(['abc', ''], Utils::splitLines("abc\r"));
    }

    public function test_split_non_terminated_line(): void
    {
        $this->assertSame(['abc'], Utils::splitLines('abc'));
    }
}
