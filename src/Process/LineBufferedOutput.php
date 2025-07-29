<?php

namespace Dezull\Unarchiver\Process;

use Closure;
use Dezull\Unarchiver\Utils;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int|string, string>
 */
class LineBufferedOutput implements IteratorAggregate
{
    protected ?Closure $applyBefore = null;

    protected ?Closure $applyAfter = null;

    protected array $unterminated = [];

    protected bool $started = false;

    /**
     * @param  Traversable<string,string>  $rawOutput
     */
    public function __construct(
        protected Traversable $rawOutput,
        protected bool $mergeBuffer = true) {}

    /**
     * @param  Closure(int|string, string): mixed  $callback
     */
    public function applyBefore(Closure $callback): LineBufferedOutput
    {
        $this->applyBefore = $callback;

        return $this;
    }

    public function applyAfter(Closure $callback): LineBufferedOutput
    {
        $this->applyAfter = $callback;

        return $this;
    }

    /**
     * @return Traversable<int|string, string>
     */
    public function getIterator(): Traversable
    {
        if ($this->started) {
            throw new \LogicException('LineBufferedOutput can only be iterated once.');
        }

        $this->started = true;

        foreach ($this->rawOutput as $key => $output) {
            $this->applyCallback('applyBefore', $output, $key);

            foreach ($this->merge($output, $key) as $line) {
                $this->applyCallback('applyAfter', $line, $key);
                $this->mergeBuffer ? yield $line : yield $key => $line;
            }
        }

        yield from $this->iterateBuffer();
    }

    public function toArray(): array
    {
        if ($this->mergeBuffer) {
            return iterator_to_array($this, false);
        }

        $arr = [];
        foreach ($this as $k => $v) {
            $arr[$k] ??= [];
            $arr[$k][] = $v;
        }

        return $arr;
    }

    protected function applyCallback(string $callback, string $output, string $key): void
    {
        if (isset($this->{$callback})) {
            ($this->{$callback})($output, $key);
        }
    }

    /**
     * @return Traversable<int|string, string>
     */
    protected function iterateBuffer(): Traversable
    {
        foreach (array_keys($this->unterminated) as $key) {
            if (! is_null($line = $this->pop($key))) {
                $this->applyCallback('applyAfter', $line, $key);
                $this->mergeBuffer ? yield $line : yield $key => $line;
            }
        }
    }

    /**
     * Get lines from $content.
     *
     * If the last line splitted from $content is not terminated by a line separator, buffer the
     * last line. Then, return an array of lines from the terminated part of $content, the first
     * line is prepended with the previously buffered content.
     */
    protected function merge(string $content, string $key): array
    {
        $key = $this->mergeBuffer ? '' : $key;
        $lines = Utils::splitLines($content);

        // Append the parse buffer (unterminated line) to the first line in the buffer
        if (isset($this->unterminated[$key]) && count($lines) > 0) {
            $lines[0] = $this->unterminated[$key].$lines[0];
        }

        // If the last line isn't terminated, move it to the parse buffer
        $lastLine = array_pop($lines);
        $this->unterminated[$key] = $lastLine === '' ? null : $lastLine;

        return $lines;
    }

    protected function pop(string $key): ?string
    {
        $key = $this->mergeBuffer ? '' : $key;
        $line = $this->unterminated[$key] ?? null;

        unset($this->unterminated[$key]);

        return $line;
    }
}
