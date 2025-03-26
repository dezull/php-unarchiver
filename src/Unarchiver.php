<?php

namespace Dezull\Unarchiver;

use Dezull\Unarchiver\Adapter\AdapterInterface;
use Dezull\Unarchiver\Entry\EntryInterface;
use Exception;
use Generator;

class Unarchiver
{
    protected string $filename;
    protected AdapterInterface $adapter;
    /** @var string */
    protected $password;

    public function __construct(string $filename, AdapterInterface $adapter, $password = null) {
        $this->filename = $filename;
        $this->password = $password;
        $this->adapter = $adapter;
    }

    public static function open($filename, $password = null): Unarchiver {
        $adapterClass = static::getAdapterFor(static::guessFileType($filename));
        $adapter = new $adapterClass($filename, $password);

        return new static($filename, $adapter, $password); 
    }

    protected static function guessFileType($filename): ?string {
        $parts = explode('.', $filename);
        $ext = $parts[array_key_last($parts)];

        if ($ext === 'rar' || mime_content_type($filename) === 'application/x-rar') {
            return 'rar';
        } else {
            return null;
        }
    }

    protected static function getAdapterFor($type): string {
        switch ($type) {
        case 'rar': return 'Dezull\Unarchiver\Adapter\Unrar';
        default: return 'Dezull\Unarchiver\Adapter\Bsdtar';
        }
    }

    public function getEntries(): Generator {
        return $this->adapter->getEntries();
    }

    public function getEntry(string $filename): EntryInterface {
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
     */
    public function extract($outputDirectory, $filenames = null, $overwrite = true): int {
        try {
            return $this->adapter->extract($outputDirectory, $filenames, $overwrite);
        } catch (Exception $e) {
            $this->adapter->cleanUp();

            throw $e;
        }
    }

    public function setTimeout(?int $seconds = null): static
    {
        $this->adapter->setTimeout($seconds);

        return $this;
    }
}
