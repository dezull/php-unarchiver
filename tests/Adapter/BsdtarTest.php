<?php

namespace Tests\Adapter;

use DateTime;
use Dezull\Unarchiver\Adapter\Bsdtar;
use Dezull\Unarchiver\Exception\EncryptionPasswordRequiredException;
use Dezull\Unarchiver\Exception\TimeoutException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\TestCase;

#[CoversClass(Bsdtar::class)]
class BsdtarTest extends TestCase
{
    #[TestWith([null, 'multi.zip'], 'archive has no password')]
    #[TestWith(['verysecure', 'multi-password.zip'], 'encrypted archive with valid password')]
    #[TestWith(['wrong-password', 'multi-password.zip'], 'encrypted archive with invalid password')]
    #[TestWith(['', 'multi-password.zip'], 'encrypted archive with empty password')]
    #[TestWith([null, 'multi-password.zip'], 'encrypted archive with no password')]
    public function test_get_entries(?string $password, string $archiveFile): void
    {
        $tar = new Bsdtar($this->fixturePath($archiveFile), $password);

        $entries = iterator_to_array($tar->getEntries());

        $this->assertCount(3, $entries);

        $this->assertTrue($entries[0]->isDirectory());
        $this->assertSame('multi', $entries[0]->getPath());
        $this->assertSame(0, $entries[0]->getSize());
        $this->assertNull($entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:00'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());

        $this->assertFalse($entries[1]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[1]->getPath());
        $this->assertSame(4, $entries[1]->getSize());
        $this->assertNull($entries[1]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:00'),
            $entries[1]->getModificationTime());
        $this->assertNull($entries[1]->getCrc());
    }

    public function test_get_entries_for_invalid_file(): void
    {
        $tar = new Bsdtar(__FILE__);

        $entries = iterator_to_array($tar->getEntries());

        $this->assertCount(0, $entries);
    }

    public function test_get_entry_for_invalid_file(): void
    {
        $tar = new Bsdtar(__FILE__);

        $this->assertNull($tar->getEntry('hello/world.txt'));
    }

    #[TestWith([null, 'multi.zip'], 'archive has no password')]
    #[TestWith(['verysecure', 'multi-password.zip'], 'encrypted archive with valid password')]
    #[TestWith(['wrong-password', 'multi-password.zip'], 'encrypted archive with invalid password')]
    #[TestWith(['', 'multi-password.zip'], 'encrypted archive with empty password')]
    #[TestWith([null, 'multi-password.zip'], 'encrypted archive with no password')]
    public function test_get_entry(?string $password, string $archiveFile): void
    {
        $tar = new Bsdtar($this->fixturePath($archiveFile), $password);

        $entry = $tar->getEntry('multi/first.txt');

        $this->assertNotNull($entry);
        $this->assertFalse($entry->isDirectory());
        $this->assertSame('multi/first.txt', $entry->getPath());
        $this->assertSame(4, $entry->getSize());
        $this->assertNull($entry->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:00'),
            $entry->getModificationTime());
        $this->assertNull($entry->getCrc());
    }

    public function test_get_no_entry(): void
    {
        $tar = new Bsdtar($this->fixturePath('multi.zip'));

        $entry = $tar->getEntry('hello/world.txt');

        $this->assertNull($entry);
    }

    #[TestWith([null, 'multi.zip'], 'archive has no password')]
    #[TestWith(['verysecure', 'multi-password.zip'], 'encrypted archive with valid password')]
    public function test_extract_valid_archive(?string $password, string $archiveFile): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $tar = new Bsdtar($this->fixturePath($archiveFile), $password);

        $count = $tar->extract($outDir->path());

        $this->assertSame(3, $count);
        $this->assertSame("abc\n", @file_get_contents($outDir->path('multi/first.txt')));
    }

    #[TestWith(['wrong-password'], 'wrong password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_extract_archive_with_invalid_password(?string $password): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);

        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $tar = new Bsdtar($this->fixturePath('multi-password.zip'), $password);

        $tar->extract($outDir->path());
    }

    public function test_extract_invalid_archive(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $tar = new Bsdtar(__FILE__);

        $count = $tar->extract($outDir->path());

        $this->assertSame(0, $count);
        $this->assertSame([], glob($outDir->path().'/*'));
    }

    #[TestWith([null, 'multi.zip'], 'archive has no password')]
    #[TestWith(['verysecure', 'multi-password.zip'], 'encrypted archive with valid password')]
    public function test_extract_filenames(?string $password, string $archiveFile): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $tar = new Bsdtar($this->fixturePath($archiveFile), $password);

        $count = $tar->extract($outDir->path(), ['multi/second.txt']);

        $this->assertSame(1, $count);
        $this->assertFileDoesNotExist($outDir->path('multi/first.txt'));
        $this->assertFileExists($outDir->path('multi/second.txt'));
        $this->assertSame("def\n", @file_get_contents($outDir->path('/multi/second.txt')));
    }

    #[TestWith(['wrong-password'], 'wrong password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_extract_filenames_with_invalid_password(?string $password): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);

        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $tar = new Bsdtar($this->fixturePath('multi-password.zip'), $password);

        $tar->extract($outDir->path(), ['multi/second.txt']);
    }

    public function test_extract_dont_overwrite(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $tar = new Bsdtar($this->fixturePath('multi.zip'));
        mkdir($outDir->path('first.txt'));
        file_put_contents($outDir->path('multi/first.txt'), 'dont overwrite');

        $count = $tar->extract($outDir->path(), overwrite: false);

        $this->assertSame(3, $count);
        $this->assertSame('dont overwrite', file_get_contents($outDir->path('multi/first.txt')));
    }

    public function test_timeout_not_exceeded(): void
    {
        $this->expectNotToPerformAssertions();

        $tar = new Bsdtar($this->fixturePath('multi.zip'));

        $entries = $tar->setTimeout(1)->getEntries();

        iterator_to_array($entries);
    }

    public function test_timeout_exceeded(): void
    {
        $this->expectException(TimeoutException::class);

        $tar = new Bsdtar($this->fixturePath('multi.zip'));

        $entries = $tar->setTimeout(1)->getEntries();

        foreach ($entries as $entry) {
            sleep(1);
        }
    }
}
