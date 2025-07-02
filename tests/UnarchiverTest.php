<?php

namespace Tests;

use DateTime;
use Dezull\Unarchiver\Unarchiver;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\TemporaryDirectory\TemporaryDirectory;

#[CoversNothing]
class UnarchiverTest extends TestCase
{
    #[TestWith(['multi.zip', 'Bsdtar'], 'Bsdtar')]
    #[TestWith(['multi.rar', 'Unrar'], 'Unrar')]
    #[TestWith(['multi.7z', 'SevenZip'], 'SevenZip')]
    public function test_open_file_with_correct_adapter(string $fixture, string $adapter): void
    {
        $ua = Unarchiver::open($this->fixturePath($fixture));

        $this->assertSame(3, iterator_count($ua->getEntries()));
        $this->assertSame($adapter, $ua->getAdapter());
    }

    public function test_unsupported_file_has_no_entry(): void
    {
        $entries = Unarchiver::open(__FILE__)->getEntries();

        $this->assertSame(0, iterator_count($entries));
    }

    public function test_get_all_entries(): void
    {
        $ua = Unarchiver::open($this->fixturePath('dir.zip'));
        $entries = iterator_to_array($ua->getEntries());

        $this->assertCount(2, $entries);

        $this->assertTrue($entries[0]->isDirectory());
        $this->assertSame('hello', $entries[0]->getPath());
        $this->assertSame(0, $entries[0]->getSize());
        $this->assertNull($entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-14 06:37:00'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());

        $this->assertFalse($entries[1]->isDirectory());
        $this->assertSame('hello/world.txt', $entries[1]->getPath());
        $this->assertSame(4, $entries[1]->getSize());
        $this->assertNull($entries[1]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-14 06:38:00'),
            $entries[1]->getModificationTime());
        $this->assertNull($entries[1]->getCrc());
    }

    public function test_get_entry(): void
    {
        $ua = Unarchiver::open($this->fixturePath('dir.zip'));
        $entry = $ua->getEntry('hello/world.txt');

        $this->assertNotNull($entry);
        $this->assertFalse($entry->isDirectory());
        $this->assertSame('hello/world.txt', $entry->getPath());
        $this->assertSame(4, $entry->getSize());
        $this->assertNull($entry->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-14 06:38:00'),
            $entry->getModificationTime());
        $this->assertNull($entry->getCrc());
    }

    public function test_get_no_entry(): void
    {
        $ua = Unarchiver::open($this->fixturePath('dir.zip'));
        $entry = $ua->getEntry('bye/world.txt');

        $this->assertNull($entry);
    }

    public function test_extract_valid_archive(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $ua = Unarchiver::open($this->fixturePath('dir.zip'));
        $ua->extract($outDir->path());

        $this->assertSame("abc\n", @file_get_contents($outDir->path('hello/world.txt')));
    }

    public function test_extract_invalid_archive(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $ua = Unarchiver::open(__FILE__);
        $ua->extract($outDir->path());

        $this->assertSame([], glob($outDir->path().'/*'));
    }

    public function test_extract_filenames(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $ua = Unarchiver::open($this->fixturePath('multi.zip'));
        $ua->extract($outDir->path(), ['multi/second.txt']);

        $this->assertFileDoesNotExist($outDir->path('multi/first.txt'));
        $this->assertFileExists($outDir->path('multi/second.txt'));
        $this->assertSame("def\n", @file_get_contents($outDir->path('/multi/second.txt')));
    }

    public function test_extract_dont_overwrite(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $ua = Unarchiver::open($this->fixturePath('dir.zip'));
        mkdir($outDir->path('hello'));
        file_put_contents($outDir->path('hello/world.txt'), 'dont overwrite');
        $ua->extract($outDir->path(), overwrite: false);

        $this->assertSame('dont overwrite', file_get_contents($outDir->path('hello/world.txt')));
    }

    public function test_set_timeout(): void
    {
        $this->expectNotToPerformAssertions();

        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $ua = Unarchiver::open($this->fixturePath('dir.zip'));
        $ua->setTimeout(1);
    }

    public function test_cannot_set_negative_timeout(): void
    {
        $this->expectExceptionMessage('timeout must be > 0');

        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $ua = Unarchiver::open($this->fixturePath('dir.zip'));
        $ua->setTimeout(-1);
    }

    public function test_cannot_set_0_timeout(): void
    {
        $this->expectExceptionMessage('timeout must be > 0');

        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $ua = Unarchiver::open($this->fixturePath('dir.zip'));
        $ua->setTimeout(-1);
    }
}
