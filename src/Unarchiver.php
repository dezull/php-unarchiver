<?php

namespace Dezull\Unarchiver;

use Dezull\Unarchiver\Adapter\AdapterInterface;
use Dezull\Unarchiver\Entry\EntryInterface;
use Exception;
use Generator;
use InvalidArgumentException;

final class Unarchiver
{
    public function __construct(protected string $filename, protected AdapterInterface $adapter, protected ?string $password = null) {}

    public static function open(string $filename, ?string $password = null): Unarchiver
    {
        $adapterClass = self::getAdapterFor(self::guessFileType($filename));
        $adapter = new $adapterClass($filename, $password);

        return new self($filename, $adapter, $password);
    }

    protected static function guessFileType(string $filename): ?string
    {
        $parts = explode('.', $filename);
        $ext = $parts[array_key_last($parts)];
        $mime = mime_content_type($filename);

        return match (true) {
            $ext === 'rar' || $mime === 'application/x-rar' => 'rar',
            $ext === '7z' || $mime === 'application/x-7z-compressed' => '7z',
            default => null,
        };
    }

    protected static function getAdapterFor(?string $type = null): string
    {
        switch ($type) {
            case 'rar': return 'Dezull\Unarchiver\Adapter\Unrar';
            case '7z': return 'Dezull\Unarchiver\Adapter\SevenZip';
            default: return 'Dezull\Unarchiver\Adapter\Bsdtar';
        }
    }

    /**
     * @return Generator<EntryInterface>
     */
    public function getEntries(): Generator
    {
        return $this->adapter->getEntries();
    }

    public function getEntry(string $filename): ?EntryInterface
    {
        return $this->adapter->getEntry($filename);
    }

    /**
     * Extract files.
     *
     * Extracting specific file(s) in a archive can be really slow, especially when the file
     * is near the end of a very large archive, as it needs to seek the file sequentially.
     *
     * FIXME bsdtar and unrar use pattern, not exact match, so the output could be wrong,
     *       if $filenames are specified.
     *
     * @param  string[]  $filenames
     */
    public function extract(string $outputDirectory, ?array $filenames = null, bool $overwrite = true): int
    {
        try {
            return $this->adapter->extract($outputDirectory, $filenames, $overwrite);
        } catch (Exception $e) {
            $this->adapter->cleanUp();

            throw $e;
        }
    }

    public function setTimeout(int $seconds): static
    {
        $this->adapter->setTimeout($this->validateTimeout($seconds));

        return $this;
    }

    public function getAdapter(): string
    {
        return preg_replace('/^.+\\\\/', '', $this->adapter::class);
    }

    private function validateTimeout(int $seconds): int
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('timeout must be > 0');
        }

        return $seconds;
    }
}
