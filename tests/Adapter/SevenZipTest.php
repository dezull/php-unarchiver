<?php

namespace Tests\Adapter;

use DateTime;
use Dezull\Unarchiver\Adapter\SevenZip;
use Dezull\Unarchiver\Exception\EncryptionPasswordRequiredException;
use Dezull\Unarchiver\Exception\TimeoutException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\TestCase;

#[CoversClass(SevenZip::class)]
class SevenZipTest extends TestCase
{
    public function test_get_entries_for_invalid_file(): void
    {
        $sevenZ = new SevenZip(__FILE__);

        $entries = iterator_to_array($sevenZ->getEntries());

        $this->assertCount(0, $entries);
    }

    public function test_get_entries_with_archive_with_no_password(): void
    {
        $sevenZ = new SevenZip($this->fixturePath('multi.7z'));

        $entries = iterator_to_array($sevenZ->getEntries());

        $this->assertCount(3, $entries);

        $this->assertTrue($entries[0]->isDirectory());
        $this->assertSame('multi', $entries[0]->getPath());
        $this->assertSame(0, $entries[0]->getSize());
        $this->assertSame(0, $entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:09'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());

        $this->assertFalse($entries[1]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[1]->getPath());
        $this->assertSame(4, $entries[1]->getSize());
        $this->assertSame(12, $entries[1]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:03'),
            $entries[1]->getModificationTime());
        $this->assertSame('4788814E', $entries[1]->getCrc());
    }

    #[TestWith(['verysecure'], 'valid password')]
    #[TestWith(['wrong-password'], 'invalid password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_get_entries_for_encrypted_archive(?string $password): void
    {
        $sevenZ = new SevenZip($this->fixturePath('multi-password.7z'), $password);

        $entries = iterator_to_array($sevenZ->getEntries());

        $this->assertCount(3, $entries);

        $this->assertTrue($entries[0]->isDirectory());
        $this->assertSame('multi', $entries[0]->getPath());
        $this->assertSame(0, $entries[0]->getSize());
        $this->assertSame(0, $entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:09'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());

        $this->assertFalse($entries[1]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[1]->getPath());
        $this->assertSame(4, $entries[1]->getSize());
        $this->assertSame(16, $entries[1]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:03'),
            $entries[1]->getModificationTime());
        $this->assertSame('4788814E', $entries[1]->getCrc());
    }

    public function test_get_entries_for_header_encrypted_archive_with_valid_password(): void
    {
        $sevenZ = new SevenZip($this->fixturePath('multi-password-header.7z'), 'verysecure');

        $entries = iterator_to_array($sevenZ->getEntries());

        $this->assertCount(3, $entries);

        $this->assertTrue($entries[0]->isDirectory());
        $this->assertSame('multi', $entries[0]->getPath());
        $this->assertSame(0, $entries[0]->getSize());
        $this->assertSame(0, $entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:09'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());

        $this->assertFalse($entries[1]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[1]->getPath());
        $this->assertSame(4, $entries[1]->getSize());
        $this->assertSame(16, $entries[1]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:03'),
            $entries[1]->getModificationTime());
        $this->assertSame('4788814E', $entries[1]->getCrc());
    }

    #[TestWith(['wrong-password'], 'invalid password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_get_entries_for_header_encrypted_archive(?string $password): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);
        $sevenZ = new SevenZip($this->fixturePath('multi-password-header.7z'), $password);

        $entries = iterator_to_array($sevenZ->getEntries());
    }

    public function test_get_entry_for_invalid_file(): void
    {
        $sevenZ = new SevenZip(__FILE__);

        $this->assertNull($sevenZ->getEntry('hello/world.txt'));
    }

    public function test_get_entry_with_archive_with_no_password(): void
    {
        $sevenZ = new SevenZip($this->fixturePath('multi.7z'));

        $entry = $sevenZ->getEntry('multi/first.txt');

        $this->assertFalse($entry->isDirectory());
        $this->assertSame('multi/first.txt', $entry->getPath());
        $this->assertSame(4, $entry->getSize());
        $this->assertSame(12, $entry->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:03'),
            $entry->getModificationTime());
        $this->assertSame('4788814E', $entry->getCrc());
    }

    #[TestWith(['verysecure'], 'valid password')]
    #[TestWith(['wrong-password'], 'invalid password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_get_entry_for_encrypted_archive(?string $password): void
    {
        $sevenZ = new SevenZip($this->fixturePath('multi-password.7z'), $password);

        $entry = $sevenZ->getEntry('multi/first.txt');

        $this->assertFalse($entry->isDirectory());
        $this->assertSame('multi/first.txt', $entry->getPath());
        $this->assertSame(4, $entry->getSize());
        $this->assertSame(16, $entry->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:03'),
            $entry->getModificationTime());
        $this->assertSame('4788814E', $entry->getCrc());
    }

    public function test_get_entry_for_header_encrypted_archive_with_valid_password(): void
    {
        $sevenZ = new SevenZip($this->fixturePath('multi-password-header.7z'), 'verysecure');

        $entry = $sevenZ->getEntry('multi/first.txt');

        $this->assertFalse($entry->isDirectory());
        $this->assertSame('multi/first.txt', $entry->getPath());
        $this->assertSame(4, $entry->getSize());
        $this->assertSame(16, $entry->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-15 23:04:03'),
            $entry->getModificationTime());
        $this->assertSame('4788814E', $entry->getCrc());
    }

    #[TestWith(['wrong-password'], 'invalid password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_get_entry_for_header_encrypted_archive(?string $password): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);
        $sevenZ = new SevenZip($this->fixturePath('multi-password-header.7z'), $password);

        $sevenZ->getEntry('multi/first.txt');
    }

    public function test_get_no_entry(): void
    {
        $sevenZ = new SevenZip($this->fixturePath('multi.7z'));

        $entry = $sevenZ->getEntry('hello/world.txt');

        $this->assertNull($entry);
    }

    #[TestWith([null, 'multi.7z'], 'archive has no password')]
    #[TestWith(['verysecure', 'multi-password.7z'], 'encrypted archive with valid password')]
    #[TestWith(['verysecure', 'multi-password-header.7z'], 'header encrypted archive with valid password')]
    public function test_extract_valid_archive(?string $password, string $archiveFile): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $sevenZ = new SevenZip($this->fixturePath($archiveFile), $password);

        $count = $sevenZ->extract($outDir->path());

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
        $sevenZ = new SevenZip($this->fixturePath('multi-password.7z'), $password);

        $sevenZ->extract($outDir->path());
    }

    #[TestWith(['wrong-password'], 'wrong password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_extract_header_encrypted_archive_with_invalid_password(?string $password): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);

        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $sevenZ = new SevenZip($this->fixturePath('multi-password-header.7z'), $password);

        $sevenZ->extract($outDir->path());
    }

    public function test_extract_invalid_archive(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $sevenZ = new SevenZip(__FILE__);

        $count = $sevenZ->extract($outDir->path());

        $this->assertSame(0, $count);
        $this->assertSame([], glob($outDir->path().'/*'));
    }

    #[TestWith([null, 'multi.7z'], 'archive has no password')]
    #[TestWith(['verysecure', 'multi-password.7z'], 'encrypted archive with valid password')]
    #[TestWith(['verysecure', 'multi-password-header.7z'], 'header encrypted archive with valid password')]
    public function test_extract_filenames(?string $password, string $archiveFile): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $sevenZ = new SevenZip($this->fixturePath($archiveFile), $password);

        $count = $sevenZ->extract($outDir->path(), ['multi/second.txt']);

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
        $sevenZ = new SevenZip($this->fixturePath('multi-password.7z'), $password);

        $sevenZ->extract($outDir->path(), ['multi/second.txt']);
    }

    public function test_extract_dont_overwrite(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $sevenZ = new SevenZip($this->fixturePath('multi.7z'));
        mkdir($outDir->path('first.txt'));
        file_put_contents($outDir->path('multi/first.txt'), 'dont overwrite');

        $count = $sevenZ->extract($outDir->path(), overwrite: false);

        $this->assertSame(2, $count);
        $this->assertSame('dont overwrite', file_get_contents($outDir->path('multi/first.txt')));
    }

    public function test_timeout_not_exceeded(): void
    {
        $this->expectNotToPerformAssertions();

        $sevenZ = new SevenZip($this->fixturePath('multi.7z'));

        $entries = $sevenZ->setTimeout(1)->getEntries();

        iterator_to_array($entries);
    }

    public function test_timeout_exceeded(): void
    {
        $this->expectException(TimeoutException::class);

        $sevenZ = new SevenZip($this->fixturePath('multi.7z'));

        $entries = $sevenZ->setTimeout(1)->getEntries();

        foreach ($entries as $entry) {
            sleep(1);
        }
    }
}
