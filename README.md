# Unarchiver

This PHP library, as the name implies, only supports archive listing and extraction.

## Why?

I created this specifically for my need to list the content of very large archives. Instead of returning a large array, this library returns a `Generator` instead. This keeps the memory usage constantly low.

Since other libraries already offer archiving and other great features, I decided not to add them here.

## Installation

1.  Install dependencies (eg: Ubuntu):

```bash
sudo apt install libarchive-tools p7zip-full unrar
```

2.  Install the package via Composer:

```bash
composer require dezull/unarchiver
```

## Usage

Create an instance of the unarchiver:

```php
use Dezull\Unarchiver\Unarchiver;

$ua = Unarchiver::open('/path/to/file.zip’);
```

If the archive is encrypted, pass the password as the second argument.

### Extraction

```php
$ua->extract('/path/to/output-dir');
```

### Listing Content

```php
foreach ($ua->getEntries() as $entry) {
    // @var Dezull\Unarchiver\Entry\EntryInterface
    echo $entry->getPath().PHP_EOL;
}
```

## API

### Unarchiver

| Method | Return | Usage |
|:----|:---:|:---:|
| `getEntries()` | `Generator<EntryInterface>` | |
| `getEntry($filename)` | `?EntryInterface` | |
| `extract(string $outputDirectory, ?array $filenames = null, bool $overwrite = true)`| `string[]` (filenames) | |
| `setTimeout(int $seconds)` | `Unarchiver` | Kill the unarchiver process after this timeout |

### EntryInterface

| Method | Return | Usage |
|:----|:---:|:---:|
| `isDirectory()` | `bool` | |
| `getPath()` | `string` | |
| `getSize()` | `?int` | |
| `getPackedSize()` | `?int` | |
| `getModificationTime()` | `?DateTime` | |
| `getCrc()` | `?string` | |
| `extract(string $outputDirectory, bool $overwrite = true)` | `void` | |

## Supported Archive Formats

Currently, this library relies on `bsdtar`, `7z`, and `unrar` binaries. It supports formats handled by these binaries.

## Caveats

The `EntryInterface#getEntry()` and `EntryInterface#extract()` methods use the underlying binary to pass on the specific filename. The binary may treat the **filename as a pattern**.
