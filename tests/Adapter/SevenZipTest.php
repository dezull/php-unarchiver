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
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:09'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());

        $this->assertFalse($entries[1]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[1]->getPath());
        $this->assertSame(4, $entries[1]->getSize());
        $this->assertSame(12, $entries[1]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
            $entries[1]->getModificationTime());
        $this->assertSame('4788814E', $entries[1]->getCrc());
    }

    #[TestWith(['verysecure', 'multi-password.7z'], 'valid password')]
    #[TestWith(['wrong-password', 'multi-password.7z'], 'invalid password')]
    #[TestWith(['', 'multi-password.7z'], 'empty password')]
    #[TestWith([null, 'multi-password.7z'], 'no password')]
    public function test_get_entries_for_encrypted_archive(?string $password, string $archiveFile): void
    {
        $sevenZ = new SevenZip($this->fixturePath($archiveFile), $password);

        $entries = iterator_to_array($sevenZ->getEntries());

        $this->assertCount(3, $entries);

        $this->assertTrue($entries[0]->isDirectory());
        $this->assertSame('multi', $entries[0]->getPath());
        $this->assertSame(0, $entries[0]->getSize());
        $this->assertSame(0, $entries[0]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:09'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());

        $this->assertFalse($entries[1]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[1]->getPath());
        $this->assertSame(4, $entries[1]->getSize());
        $this->assertSame(16, $entries[1]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
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
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:09'),
            $entries[0]->getModificationTime());
        $this->assertNull($entries[0]->getCrc());

        $this->assertFalse($entries[1]->isDirectory());
        $this->assertSame('multi/first.txt', $entries[1]->getPath());
        $this->assertSame(4, $entries[1]->getSize());
        $this->assertSame(16, $entries[1]->getPackedSize());
        $this->assertEquals(
            DateTime::createFromFormat('Y-m-d G:i:s', '2025-05-16 07:04:03'),
            $entries[1]->getModificationTime());
        $this->assertSame('4788814E', $entries[1]->getCrc());
    }

    #[TestWith(['wrong-password', 'multi-password-header.7z'], 'invalid password')]
    #[TestWith(['', 'multi-password-header.7z'], 'empty password')]
    #[TestWith([null, 'multi-password-header.7z'], 'no password')]
    public function test_get_entries_for_header_encrypted_archive(?string $password, string $archiveFile): void
    {
        $this->expectException(EncryptionPasswordRequiredException::class);
        $seven7 = new SevenZip($this->fixturePath($archiveFile), $password);

        $entries = iterator_to_array($seven7->getEntries());
    }

    public function test_extract_dont_overwrite(): void
    {
        $outDir = (new TemporaryDirectory)->deleteWhenDestroyed()->create();
        $seven7 = new SevenZip($this->fixturePath('multi.7z'));
        mkdir($outDir->path('first.txt'));
        file_put_contents($outDir->path('multi/first.txt'), 'dont overwrite');

        $count = $seven7->extract($outDir->path(), overwrite: false);

        $this->assertSame(2, $count);
        $this->assertSame('dont overwrite', file_get_contents($outDir->path('multi/first.txt')));
    }

    public function test_timeout_not_exceeded(): void
    {
        $this->expectNotToPerformAssertions();

        $seven7 = new SevenZip($this->fixturePath('multi.7z'));

        $entries = $seven7->setTimeout(1)->getEntries();

        iterator_to_array($entries);
    }

    public function test_timeout_exceeded(): void
    {
        $this->expectException(TimeoutException::class);

        $seven7 = new SevenZip($this->fixturePath('multi.7z'));

        $entries = $seven7->setTimeout(1)->getEntries();

        foreach ($entries as $entry) {
            sleep(1);
        }
    }
}
