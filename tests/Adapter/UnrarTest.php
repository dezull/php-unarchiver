<?php

namespace Tests\Adapter;

use DateTime;
use Dezull\Unarchiver\Adapter\Unrar;
use Dezull\Unarchiver\Exception\EncryptionPasswordRequiredException;
use Dezull\Unarchiver\Exception\TimeoutException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\TestCase;

#[CoversClass(Unrar::class)]
class UnrarTest extends TestCase
{
    public function test_get_entries_for_invalid_file(): void
    {
        $unrar = new Unrar(__FILE__);

        $entries = iterator_to_array($unrar->getEntries());

        $this->assertCount(0, $entries);
    }

    public function test_get_entries_with_archive_with_no_password(): void
    {
        $unrar = new Unrar($this->fixturePath('multi.rar'));

        $entries = iterator_to_array($unrar->getEntries());

        $this->assertCount(3, $entries);

        $this->assertTrue($entries[2]->isDirectory());
        $this->assertSame('multi', $entries[2]->getPath());
        $this->assertNull($entries[2]->getSize());
        $this->assertNull($entries[2]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:09'),
            $entries[2]->getModificationTime());
        $this->assertSame('00000000', $entries[2]->getCrc());

        $this->assertFalse($entries[0]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[0]->getPath());
        $this->assertSame(4, $entries[0]->getSize());
        $this->assertSame(4, $entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
            $entries[0]->getModificationTime());
        $this->assertSame('4788814E', $entries[0]->getCrc());
    }

    #[TestWith(['verysecure'], 'valid password')]
    #[TestWith(['wrong-password'], 'invalid password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_get_entries_for_encrypted_archive(?string $password): void
    {
        $unrar = new Unrar($this->fixturePath('multi-password.rar'), $password);

        $entries = iterator_to_array($unrar->getEntries());

        $this->assertCount(3, $entries);

        $this->assertTrue($entries[2]->isDirectory());
        $this->assertSame('multi', $entries[2]->getPath());
        $this->assertNull($entries[2]->getSize());
        $this->assertNull($entries[2]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:09'),
            $entries[2]->getModificationTime());
        $this->assertSame('00000000', $entries[2]->getCrc());

        $this->assertFalse($entries[0]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[0]->getPath());
        $this->assertSame(4, $entries[0]->getSize());
        $this->assertSame(16, $entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());
    }

    public function test_get_entries_for_header_encrypted_archive_with_valid_password(): void
    {
        $unrar = new Unrar($this->fixturePath('multi-password-header.rar'), 'verysecure');

        $entries = iterator_to_array($unrar->getEntries());

        $this->assertCount(3, $entries);

        $this->assertTrue($entries[2]->isDirectory());
        $this->assertSame('multi', $entries[2]->getPath());
        $this->assertNull($entries[2]->getSize());
        $this->assertNull($entries[2]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:09'),
            $entries[2]->getModificationTime());
        $this->assertSame('00000000', $entries[2]->getCrc());

        $this->assertFalse($entries[0]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[0]->getPath());
        $this->assertSame(4, $entries[0]->getSize());
        $this->assertSame(16, $entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
            $entries[0]->getModificationTime());
        $this->assertSame('4788814E', $entries[0]->getCrc());
    }

    #[TestWith(['wrong-password'], 'invalid password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_get_entries_for_header_encrypted_archive(?string $password): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);
        $unrar = new Unrar($this->fixturePath('multi-password-header.rar'), $password);

        $entries = iterator_to_array($unrar->getEntries());
    }

    public function test_get_entry_for_invalid_file(): void
    {
        $unrar = new Unrar(__FILE__);

        $this->assertNull($unrar->getEntry('hello/world.txt'));
    }

    public function test_get_entry_with_archive_with_no_password(): void
    {
        $unrar = new Unrar($this->fixturePath('multi.rar'));

        $entry = $unrar->getEntry('multi/first.txt');

        $this->assertNotNull($entry);
        $this->assertFalse($entry->isDirectory());
        $this->assertSame('multi/first.txt', $entry->getPath());
        $this->assertSame(4, $entry->getSize());
        $this->assertSame(4, $entry->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
            $entry->getModificationTime());
        $this->assertSame('4788814E', $entry->getCrc());
    }

    #[TestWith(['verysecure'], 'encrypted archive with valid password')]
    #[TestWith(['wrong-password'], 'encrypted archive with invalid password')]
    #[TestWith([null], 'no password')]
    #[TestWith([null], 'encrypted archive with no password')]
    public function test_get_entry_for_encrypted_archive(?string $password): void
    {
        $unrar = new Unrar($this->fixturePath('multi-password.rar'), $password);

        $entry = $unrar->getEntry('multi/first.txt');

        $this->assertNotNull($entry);
        $this->assertFalse($entry->isDirectory());
        $this->assertSame('multi/first.txt', $entry->getPath());
        $this->assertSame(4, $entry->getSize());
        $this->assertSame(16, $entry->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
            $entry->getModificationTime());
        $this->assertNull($entry->getCrc());
    }

    public function test_get_entry_for_header_encrypted_archive_with_valid_password(): void
    {
        $unrar = new Unrar($this->fixturePath('multi-password-header.rar'), 'verysecure');

        $entry = $unrar->getEntry('multi/first.txt');

        $this->assertNotNull($entry);
        $this->assertFalse($entry->isDirectory());
        $this->assertSame('multi/first.txt', $entry->getPath());
        $this->assertSame(4, $entry->getSize());
        $this->assertSame(16, $entry->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
            $entry->getModificationTime());
        $this->assertSame('4788814E', $entry->getCrc());
    }

    #[TestWith(['wrong-password'], 'invalid password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_get_entry_for_header_encrypted_archive(?string $password): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);
        $unrar = new Unrar($this->fixturePath('multi-password-header.rar'), $password);

        $unrar->getEntry('multi/first.txt');
    }

    public function test_get_no_entry(): void
    {
        $unrar = new Unrar($this->fixturePath('multi.rar'));

        $entry = $unrar->getEntry('hello/world.txt');

        $this->assertNull($entry);
    }

    #[TestWith([null, 'multi.rar'], 'archive has no password')]
    #[TestWith(['verysecure', 'multi-password.rar'], 'encrypted archive with valid password')]
    public function test_extract_valid_archive(?string $password, string $archiveFile): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $unrar = new Unrar($this->fixturePath($archiveFile), $password);

        $count = $unrar->extract($outDir->path());

        $this->assertSame(2, $count);
        $this->assertSame("abc\n", @file_get_contents($outDir->path('multi/first.txt')));
    }

    #[TestWith(['wrong-password'], 'wrong password')]
    #[TestWith([''], 'empty password')]
    #[TestWith([null], 'no password')]
    public function test_extract_archive_with_invalid_password(?string $password): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);

        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $unrar = new Unrar($this->fixturePath('multi-password.rar'), $password);

        $unrar->extract($outDir->path());
    }

    public function test_extract_invalid_archive(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $unrar = new Unrar(__FILE__);

        $count = $unrar->extract($outDir->path());

        $this->assertSame(0, $count);
        $this->assertSame([], glob($outDir->path().'/*'));
    }

    #[TestWith([null, 'multi.rar'], 'archive has no password')]
    #[TestWith(['verysecure', 'multi-password.rar'], 'encrypted archive with valid password')]
    public function test_extract_filenames(?string $password, string $archiveFile): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $unrar = new Unrar($this->fixturePath($archiveFile), $password);

        $count = $unrar->extract($outDir->path(), ['multi/second.txt']);

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
        $unrar = new Unrar($this->fixturePath('multi-password.rar'), $password);

        $unrar->extract($outDir->path(), ['multi/second.txt']);
    }

    public function test_extract_dont_overwrite(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $unrar = new Unrar($this->fixturePath('multi.rar'));
        mkdir($outDir->path('first.txt'));
        file_put_contents($outDir->path('multi/first.txt'), 'dont overwrite');

        $count = $unrar->extract($outDir->path(), overwrite: false);

        $this->assertSame(1, $count);
        $this->assertSame('dont overwrite', file_get_contents($outDir->path('multi/first.txt')));
    }

    public function test_timeout_not_exceeded(): void
    {
        $this->expectNotToPerformAssertions();

        $unrar = new Unrar($this->fixturePath('multi.rar'));

        $entries = $unrar->setTimeout(1)->getEntries();

        iterator_to_array($entries);
    }

    public function test_timeout_exceeded(): void
    {
        $this->expectException(TimeoutException::class);

        $unrar = new Unrar($this->fixturePath('multi.rar'));

        $entries = $unrar->setTimeout(1)->getEntries();

        foreach ($entries as $entry) {
            sleep(1);
        }
    }
}
