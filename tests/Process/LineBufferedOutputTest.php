<?php

namespace Tests\Process;

use Dezull\Unarchiver\Process\LineBuffer;
use Dezull\Unarchiver\Process\LineBufferedOutput;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;
use Traversable;

#[CoversClass(LineBuffer::class)]
class LineBufferedOutputTest extends TestCase
{
    public function test_buffer_empty_line(): void
    {
        $buffer = new LineBufferedOutput($this->makeIterateable(['']));

        $this->assertSame([], $buffer->toArray());
    }

    public function test_buffer_non_terminated_line(): void
    {
        $buffer = new LineBufferedOutput($this->makeIterateable(['hello']));

        $this->assertSame(['hello'], $buffer->toArray());
    }

    public function test_buffer_terminated_line(): void
    {
        $buffer = new LineBufferedOutput($this->makeIterateable(["hello\n"]));

        $this->assertSame(['hello'], $buffer->toArray());
    }

    public function test_buffer_non_terminated_lines(): void
    {
        $buffer = new LineBufferedOutput($this->makeIterateable(['hello', ' world']));

        $this->assertSame(['hello world'], $buffer->toArray());
    }

    public function test_buffer_non_terminated_line_with_terminated_line(): void
    {
        $buffer = new LineBufferedOutput($this->makeIterateable(['hello', " world\n"]));

        $this->assertSame(['hello world'], $buffer->toArray());
    }

    public function test_buffer_terminated_line_with_non_terminated_line(): void
    {
        $buffer = new LineBufferedOutput($this->makeIterateable(["hello\n", ' world']));

        $this->assertSame(['hello', ' world'], $buffer->toArray());
    }

    public function test_buffer_terminated_lines(): void
    {
        $buffer = new LineBufferedOutput($this->makeIterateable(["hello\n", " world\n"]));

        $this->assertSame(['hello', ' world'], $buffer->toArray());
    }

    public function test_iterate_unmerged_buffers_by_key(): void
    {
        $iterator = $this->makeIterateable([
            ['foo' => "hello\n"],
            ['bar' => 'hi'],
            ['bar' => ' all'],
            ['foo' => ' world'],
        ]);
        $buffer = new LineBufferedOutput($iterator, mergeBuffer: false);

        $this->assertSame(
            [
                'foo' => ['hello', ' world'],
                'bar' => ['hi all'],
            ],
            $buffer->toArray()
        );
    }

    public function test_iterate_merged_buffer(): void
    {
        $iterator = $this->makeIterateable([
            ['foo' => "hello\n"],
            ['bar' => 'hi'],
            ['bar' => ' all'],
            ['foo' => ' world'],
        ]);
        $buffer = new LineBufferedOutput($iterator);

        $this->assertSame(['hello', 'hi all world'], $buffer->toArray());
    }

    #[TestWith([true], 'merged buffer')]
    #[TestWith([false], 'unmerged buffers')]
    public function test_apply_before(bool $mergeBuffer): void
    {
        $iterator = $this->makeIterateable([
            ['foo' => "hello\n"],
            ['bar' => 'hi'],
            ['bar' => ' all'],
            ['foo' => ' world'],
        ]);
        $buffer = new LineBufferedOutput($iterator, mergeBuffer: $mergeBuffer);
        $all = [];

        $buffer->applyBefore(function ($v, $k) use (&$all) {
            $all[] = [$k, $v];
        });
        $buffer->toArray();

        $this->assertSame(
            [
                ['foo', "hello\n"],
                ['bar', 'hi'],
                ['bar', ' all'],
                ['foo', ' world'],
            ],
            $all
        );
    }

    public function test_apply_after_with_unmerged_buffer(): void
    {
        $iterator = $this->makeIterateable([
            ['foo' => "hello\n"],
            ['bar' => 'hi'],
            ['bar' => ' all'],
            ['foo' => ' world'],
        ]);
        $buffer = new LineBufferedOutput($iterator, mergeBuffer: false);
        $all = [];

        $buffer->applyAfter(function ($v, $k) use (&$all) {
            $all[] = [$k, $v];
        });
        $buffer->toArray();

        $this->assertSame(
            [
                ['foo', 'hello'],
                ['foo', ' world'],
                ['bar', 'hi all'],
            ],
            $all
        );
    }

    public function test_apply_after_with_merged_buffer(): void
    {
        $iterator = $this->makeIterateable([
            ['foo' => "hello\n"],
            ['bar' => 'hi'],
            ['bar' => ' all'],
            ['foo' => ' world'],
        ]);
        $buffer = new LineBufferedOutput($iterator, mergeBuffer: true);
        $all = [];

        $buffer->applyAfter(function ($v, $k) use (&$all) {
            $all[] = [$k, $v];
        });
        $buffer->toArray();

        $this->assertSame(
            [
                ['foo', 'hello'],
                ['', 'hi all world'],
            ],
            $all
        );
    }

    /**
     * @param  array<int,mixed>  $values
     * @return Generator<mixed,mixed>
     */
    private function makeIterateable(array $values = []): Traversable
    {
        foreach ($values as $value) {
            is_array($value) ? yield key($value) => current($value) : yield $value;
        }
    }
}
